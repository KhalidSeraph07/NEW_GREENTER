"""Procedimiento H7-2 acotado a instalaciones ficticias identificadas; nunca emisor-h1."""
import argparse
import base64
import datetime
import getpass
import io
import ipaddress
import json
import os
import re
import secrets
import subprocess
import tarfile
import time
import uuid
import sys
import warnings
from pathlib import Path
from h72_crypto import RecoveryError, LIMIT, digest, json_file, keygen, openssl_version, private_dir, run, seal, unseal
from h72_snapshot import DATABASES, EPHEMERAL, GATE, correlate, logical_snapshot, persistent_files, safe_file

REPOSITORY = Path(__file__).resolve().parents[1]
MARKER = '.h72-installation.json'
OVERRIDE = '.h72-compose.json'


def environment(root):
    pairs = (root / '.env').read_text(encoding='utf-8-sig').splitlines()
    result = dict(line.split('=', 1) for line in pairs if '=' in line and not line.startswith('#'))
    if (result.get('APP_ENV') != 'local' or result.get('APP_DEBUG') != 'false'
            or result.get('DB_HOST') != 'postgres' or result.get('DB_DATABASE') != 'emisor_h1'
            or result.get('DB_USERNAME') != 'emisor_h1'):
        raise RecoveryError('Configuración fuera del perfil ficticio local aprobado.')
    key = result.get('APP_KEY', '')
    try:
        if not key.startswith('base64:') or len(base64.b64decode(key[7:], validate=True)) != 32:
            raise ValueError()
    except ValueError as error:
        raise RecoveryError('Material APP_KEY no recuperable; no declarar instalación válida.') from error
    return result


def state(root, status, **extra):
    path = safe_file(root, GATE)
    path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
    temporary = path.with_name(path.name + '.' + uuid.uuid4().hex)
    json_file(temporary, {'status': status, **extra})
    os.replace(temporary, path)


def profile(root, project, vendor, role, code_files):
    if not re.fullmatch(r'emisor-h72-[a-z0-9-]{5,64}', project):
        raise RecoveryError('Nombre de proyecto fuera del ensayo H7-2.')
    vendor = vendor.resolve()
    if vendor != (REPOSITORY / 'vendor').resolve() or not (vendor / 'autoload.php').is_file():
        raise RecoveryError('Reutilizar únicamente vendor aprobado en lectura; sin datos de otra instalación.')
    json_file(root / MARKER, {'id': uuid.uuid4().hex, 'project': project, 'role': role,
                             'vendor': str(vendor), 'code_files': code_files})
    override = {'services': {name: {'volumes': [{'type': 'bind', 'source': str(vendor), 'target': '/workspace/vendor', 'read_only': True}]}
                             for name in ('php', 'web')}}
    # Dos redes existentes por copia, con subredes pequeñas; no consumir otro /16 del pool global ni podar redes ajenas.
    network_ids = run(['docker', 'network', 'ls', '-q']).decode().splitlines()
    occupied = []
    if network_ids:
        for network in json.loads(run(['docker', 'network', 'inspect', *network_ids])):
            for item in network.get('IPAM', {}).get('Config') or []:
                if item.get('Subnet'):
                    occupied.append(ipaddress.ip_network(item['Subnet']))
    candidates = list(ipaddress.ip_network('10.253.0.0/16').subnets(new_prefix=28))
    start = int(digest(project.encode())[:8], 16) % len(candidates)
    chosen = []
    for subnet in candidates[start:] + candidates[:start]:
        if not any(subnet.version == prior.version and subnet.overlaps(prior) for prior in occupied):
            chosen.append(str(subnet)); occupied.append(subnet)
            if len(chosen) == 2: break
    if len(chosen) != 2:
        raise RecoveryError('No hay subredes libres para este ensayo; no modificar infraestructura global.')
    override['networks'] = {name: {'ipam': {'config': [{'subnet': subnet}]}} for name, subnet in zip(('local', 'browser'), chosen)}
    json_file(root / OVERRIDE, override)
    (root / 'vendor').mkdir(exist_ok=True)
    (root / 'bootstrap/cache').mkdir(parents=True, exist_ok=True, mode=0o700)
    for directory in ('sessions', 'views', 'cache/data'):
        (root / 'storage/framework' / directory).mkdir(parents=True, exist_ok=True, mode=0o700)


class Installation:
    def __init__(self, root):
        self.root = Path(root).resolve()
        if self.root == REPOSITORY or not (self.root / MARKER).is_file() or (self.root / MARKER).is_symlink():
            raise RecoveryError('La instalación original y las instalaciones sin marcador están excluidas.')
        self.marker = json.loads((self.root / MARKER).read_text())
        self.project = self.marker['project']
        if not re.fullmatch(r'emisor-h72-[a-z0-9-]{5,64}', self.project):
            raise RecoveryError('Proyecto no autorizado para este procedimiento.')
        environment(self.root)
        config = json.loads(self.compose(['config', '--format', 'json']))
        if set(config['services']) != {'postgres', 'php', 'web'} or not config['networks']['local']['internal']:
            raise RecoveryError('Servicios o red fuera del perfil existente.')
        volume = config['volumes']['postgres_data']['name']
        if volume != self.project + '_postgres_data':
            raise RecoveryError('Volumen PostgreSQL ajeno o compartido.')
        for service, definition in config['services'].items():
            if definition['image'] != ('emisor-h1-postgres:local' if service == 'postgres' else 'emisor-h1-php:local'):
                raise RecoveryError('Imagen fuera del perfil aprobado.')
            for mount in definition.get('volumes', []):
                if mount['type'] == 'volume':
                    if service != 'postgres' or mount['source'] != 'postgres_data':
                        raise RecoveryError('Volumen inesperado.')
                else:
                    source = Path(mount['source']).resolve()
                    if not source.is_relative_to(self.root) and not (str(source) == self.marker['vendor'] and mount.get('read_only')):
                        raise RecoveryError('Montaje cruza el aislamiento.')
            for port in definition.get('ports', []):
                if port.get('host_ip') != '127.0.0.1':
                    raise RecoveryError('Solo publicación loopback.')

    def compose(self, arguments, data=None):
        return run(['docker', 'compose', '-p', self.project, '-f', 'compose.yaml', '-f', OVERRIDE, *arguments], data, self.root)

    def sql(self, database, query):
        if database not in (*DATABASES, 'postgres'):
            raise RecoveryError('Base no admitida.')
        return self.compose(['exec', '-T', 'postgres', 'psql', '-X', '-A', '-t', '-v', 'ON_ERROR_STOP=1',
                             '-U', 'emisor_h1', '-d', database], query.encode())

    def quiesce(self):
        if self.marker['role'] != 'source':
            raise RecoveryError('Solo la fuente identificada puede capturarse.')
        state(self.root, 'capture')
        self.compose(['stop', '-t', '10', 'web', 'php'])
        active = run(['docker', 'ps', '--filter', 'label=com.docker.compose.project=' + self.project,
                      '--format', '{{.Label "com.docker.compose.service"}}']).decode().splitlines()
        if active != ['postgres']:
            raise RecoveryError('Quedan procesos de aplicación; no capturar.')
        count = self.sql('postgres', "SELECT count(*) FROM pg_stat_activity WHERE backend_type='client backend' AND pid<>pg_backend_pid()").decode().strip()
        if count != '0':
            raise RecoveryError('Hay conexiones sin drenar; no capturar.')


def pack(files, manifest):
    manifest['entries'] = {name: {'bytes': len(data), 'sha256': digest(data)} for name, data in sorted(files.items())}
    files = files | {'manifest.json': json.dumps(manifest, sort_keys=True).encode()}
    output = io.BytesIO()
    with tarfile.open(fileobj=output, mode='w') as tar:
        for name, data in sorted(files.items()):
            if output.tell() + len(data) > LIMIT:
                raise RecoveryError('El ensayo en memoria no admite este tamaño.')
            info = tarfile.TarInfo(name); info.size = len(data); info.mode = 0o600
            tar.addfile(info, io.BytesIO(data))
    return output.getvalue()


def unpack(payload):
    if len(payload) > LIMIT:
        raise RecoveryError('Contenido fuera del límite del ensayo.')
    files = {}
    with tarfile.open(fileobj=io.BytesIO(payload), mode='r:') as tar:
        for member in tar:
            name = member.name
            if not member.isfile() or member.size > LIMIT or name in files or '\\' in name or ':' in name or '\x00' in name or name.startswith('/') or any(p in ('', '.', '..') or p.endswith((' ', '.')) for p in name.split('/')):
                raise RecoveryError('Entrada de respaldo no admitida.')
            files[name] = tar.extractfile(member).read()
    try:
        manifest = json.loads(files.pop('manifest.json'))
        if manifest['format'] != 'NEW_GREENTER-H72-1' or set(files) != set(manifest['entries']):
            raise RecoveryError('Conjunto incompleto o versión no admitida.')
        required = {f'databases/{d}.dump' for d in DATABASES} | {'files/.env', 'files/bootstrap/app.php', 'files/composer.lock'}
        if not required <= set(files) or set(manifest['snapshot']) != {'cluster', *DATABASES}:
            raise RecoveryError('Faltan componentes imprescindibles del conjunto.')
        for name, entry in manifest['entries'].items():
            if len(files[name]) != entry['bytes'] or digest(files[name]) != entry['sha256']:
                raise RecoveryError('Manifiesto no coincide.')
            if not (name.startswith('files/') or name in [f'databases/{d}.dump' for d in DATABASES]):
                raise RecoveryError('Miembro fuera del formato.')
    except (KeyError, ValueError) as error:
        raise RecoveryError('Manifiesto no válido.') from error
    return files, manifest


def separated(path, installations, keys=None):
    path = Path(path).resolve()
    if any(path.is_relative_to(p.resolve()) or p.resolve().is_relative_to(path) for p in installations):
        raise RecoveryError('Respaldo, claves e instalaciones deben ocupar directorios separados.')
    if keys and (path.is_relative_to(keys.resolve()) or keys.resolve().is_relative_to(path)):
        raise RecoveryError('No almacenar claves en el directorio del respaldo.')


def backup(source, destination, keys, password):
    started = time.monotonic(); source = Installation(source); keys = Path(keys).resolve()
    separated(keys, [source.root, REPOSITORY])
    separated(Path(destination), [source.root, REPOSITORY], keys)
    output = private_dir(destination)
    version = openssl_version()
    maintenance_started = datetime.datetime.now(datetime.timezone.utc).isoformat()
    source.quiesce()
    before = logical_snapshot(source)
    contents = persistent_files(source.root)
    correlation = correlate(source, contents)
    files = {'files/' + name: data for name, data in contents.items()}
    for relative in source.marker['code_files'] + ['.env']:
        if relative == GATE or relative.startswith(EPHEMERAL):
            continue
        path = safe_file(source.root, relative)
        files['files/' + relative] = path.read_bytes()
    for db in DATABASES:
        files['databases/' + db + '.dump'] = source.compose(['exec', '-T', 'postgres', 'pg_dump', '-U', 'emisor_h1', '-d', db, '-Fc'])
    if logical_snapshot(source) != before or persistent_files(source.root) != contents:
        raise RecoveryError('La fuente cambió durante la ventana; copia inválida.')
    for relative in source.marker['code_files'] + ['.env']:
        if 'files/' + relative in files and safe_file(source.root, relative).read_bytes() != files['files/' + relative]:
            raise RecoveryError('Código o configuración cambiaron durante la captura; copia inválida.')
    manifest = {'format': 'NEW_GREENTER-H72-1', 'id': str(uuid.uuid4()), 'maintenance_started_utc': maintenance_started,
                'captured_utc': datetime.datetime.now(datetime.timezone.utc).isoformat(),
                'source_project': source.project, 'code_files': source.marker['code_files'], 'snapshot': before,
                'correlation': correlation, 'openssl': version,
                'excluded': ['sesiones autenticadas', 'caché/vistas compiladas', 'vendor regenerable desde composer.lock',
                             '.git', 'postgres_data físico/WAL y bases de sistema regenerables', 'claves externas de descifrado',
                             'marcadores operativos H72 y override de montajes regenerado por destino']}
    payload = pack(files, manifest)
    ciphertext = seal(payload, keys, password)
    partial = output / 'backup.cms.partial'
    with partial.open('xb') as handle:
        handle.write(ciphertext); handle.flush(); os.fsync(handle.fileno())
    if os.name != 'nt': partial.chmod(0o600)
    restored, readback = unpack(unseal(partial, keys, password))
    if readback != manifest or restored != files:
        raise RecoveryError('Verificación posterior al cifrado fallida.')
    os.replace(partial, output / 'backup.cms')
    receipt = {'id': manifest['id'], 'cipher_sha256': digest(ciphertext), 'bytes': len(ciphertext),
               'elapsed_seconds': round(time.monotonic() - started, 3), 'valid': True,
               'files': len(files), 'source_project': source.project, 'openssl': version}
    json_file(output / 'receipt.json', receipt)
    return receipt


def restore(backup_path, target, project, port, keys, password, progress=lambda stage: None):
    started = time.monotonic(); target = Path(target).resolve(); keys = Path(keys).resolve()
    separated(Path(backup_path).parent, [target, REPOSITORY], keys)
    separated(keys, [target])
    if target.exists() or run(['docker', 'volume', 'ls', '-q', '--filter', 'name=^' + project + '_postgres_data$']).strip():
        raise RecoveryError('Destino o volumen ya existen; no sobrescribir ni limpiar.')
    target = private_dir(target); state(target, 'restoring')
    files, manifest = unpack(unseal(backup_path, keys, password))
    if project == manifest['source_project'] or not re.fullmatch(r'emisor-h72-[a-z0-9-]{5,64}', project) or not 1024 <= port <= 65535:
        raise RecoveryError('Identidad o puerto de destino no admitido.')
    for name, data in files.items():
        if not name.startswith('files/'):
            continue
        relative = name[6:]
        if relative == GATE or relative.startswith(EPHEMERAL) or relative.startswith(('.git/', 'vendor/')) or relative in (MARKER, OVERRIDE):
            raise RecoveryError('Intento de restaurar estado operativo o sesión no permitido.')
        path = safe_file(target, relative); path.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
        with path.open('xb') as handle: handle.write(data)
        if os.name != 'nt': path.chmod(0o600)
    env = environment(target)
    env.update({'DB_PASSWORD': secrets.token_hex(24), 'H2_HOST_PORT': str(port),
                'SESSION_COOKIE': 'emisor_h72_' + uuid.uuid4().hex,
                'LOCAL_UID': str(os.getuid() if os.name != 'nt' else 1000), 'LOCAL_GID': str(os.getgid() if os.name != 'nt' else 1000)})
    (target / '.env').write_text(''.join(k + '=' + v + '\n' for k, v in env.items()), encoding='utf-8')
    profile(target, project, REPOSITORY / 'vendor', 'destination', manifest['code_files'])
    destination = Installation(target)
    destination.compose(['up', '-d', '--wait', 'postgres'])
    for db in DATABASES:
        if db == 'emisor_h2_test': destination.sql('postgres', 'CREATE DATABASE emisor_h2_test')
        if destination.sql(db, "SELECT count(*) FROM pg_tables WHERE schemaname='public'").strip() != b'0':
            raise RecoveryError('Base de destino no vacía.')
        destination.compose(['exec', '-T', 'postgres', 'pg_restore', '-U', 'emisor_h1', '-d', db,
                             '--exit-on-error', '--single-transaction'], files['databases/' + db + '.dump'])
        progress('restored_' + db)
    actual = logical_snapshot(destination)
    if actual != manifest['snapshot'] or correlate(destination, persistent_files(target)) != manifest['correlation']:
        raise RecoveryError('Conciliación de bases o referencias fallida; destino bloqueado.')
    for name, data in files.items():
        if name.startswith('files/') and name != 'files/.env' and safe_file(target, name[6:]).read_bytes() != data:
            raise RecoveryError('Archivo restaurado no coincide; destino bloqueado.')
    if any((target / 'storage/framework/sessions').iterdir()):
        raise RecoveryError('Sesión restaurada no admitida.')
    result = {'id': manifest['id'], 'integrity_verified': True, 'read_only': True, 'elapsed_seconds': round(time.monotonic() - started, 3),
              'project': project, 'files': len(files), 'correlation': manifest['correlation'], 'snapshot': actual}
    json_file(target / '.h72-restored.json', result)
    # El desbloqueo de consultas es el último commit: si falla el informe, mantener restoring.
    state(target, 'verified', backup_id=manifest['id'], cipher_sha256=digest(Path(backup_path).read_bytes()), read_only=True)
    return result | {'verified': True, 'elapsed_seconds': round(time.monotonic() - started, 3)}


def main():
    class SafeParser(argparse.ArgumentParser):
        def error(self, message):
            raise RecoveryError('Argumentos no admitidos; consultar --help sin secretos.')
    parser = SafeParser(description=__doc__)
    commands = parser.add_subparsers(dest='command', required=True)
    k = commands.add_parser('keygen'); k.add_argument('--keys', type=Path, required=True)
    b = commands.add_parser('backup'); b.add_argument('--source', type=Path, required=True); b.add_argument('--output', type=Path, required=True); b.add_argument('--keys', type=Path, required=True)
    r = commands.add_parser('restore'); r.add_argument('--backup', type=Path, required=True); r.add_argument('--target', type=Path, required=True); r.add_argument('--project', required=True); r.add_argument('--port', type=int, required=True); r.add_argument('--keys', type=Path, required=True)
    args = parser.parse_args()
    if not sys.stdin.isatty():
        raise RecoveryError('La interfaz del operador requiere TTY y entrada oculta.')
    warnings.simplefilter('error', getpass.GetPassWarning)
    password = getpass.getpass('Contraseña del almacén de recuperación de ensayo (oculta): ').encode()
    if args.command == 'keygen':
        if len(password) < 20 or password != getpass.getpass('Confirmar contraseña: ').encode():
            raise RecoveryError('Confirmación o longitud no admitida (mínimo 20 bytes).')
        keygen(args.keys, password); print('Almacén de claves de ensayo separado creado.'); return
    if args.command == 'backup':
        result = backup(args.source, args.output, args.keys, password)
    else:
        result = restore(args.backup, args.target, args.project, args.port, args.keys, password)
        result = {k: result[k] for k in ('id', 'verified', 'read_only', 'elapsed_seconds', 'project', 'files')}
    print(json.dumps(result))


if __name__ == '__main__':
    try: main()
    except RecoveryError as error:
        print(str(error)); raise SystemExit(1)
    except (Exception, KeyboardInterrupt):
        print('Operación no confirmada. Conservar fuente/destino bloqueados y revisar el ensayo privado; no reintentar sobre un destino parcial.')
        raise SystemExit(1)
