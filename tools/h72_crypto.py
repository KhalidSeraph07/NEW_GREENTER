"""CMS estándar con OpenSSL de la imagen existente; sin criptografía propia."""
import hashlib
import json
import os
import re
import subprocess
import uuid
from pathlib import Path

IMAGE = 'emisor-h1-php:local'
LIMIT = 64 * 1024 * 1024  # Ensayo acotado en memoria; no escribir un respaldo plano intermedio.


class RecoveryError(RuntimeError):
    pass


def run(argv, data=None, cwd=None, timeout=180):
    result = subprocess.run([str(x) for x in argv], input=data, stdout=subprocess.PIPE,
                            stderr=subprocess.PIPE, cwd=cwd, timeout=timeout)
    if result.returncode:
        # Ni stderr de SQL/OpenSSL ni argumentos/entrada pueden reflejar secretos.
        operation = next((name for name in ('pg_restore', 'pg_dump', 'psql', 'openssl', 'artisan', 'icacls') if name in argv), 'docker')
        raise RecoveryError(f'Operación {operation} no confirmada (salida {result.returncode}); conservar el bloqueo y revisar el ensayo privado.')
    return result.stdout


def digest(data):
    return hashlib.sha256(data).hexdigest()


def private_dir(path):
    path = Path(path).resolve()
    path.mkdir(parents=True, exist_ok=False, mode=0o700)
    if os.name == 'nt':
        sid = re.search(r'S-1-\d+(?:-\d+)+', run(['whoami', '/user', '/fo', 'csv', '/nh']).decode(errors='replace'))[0]
        run(['icacls', path, '/inheritance:r', '/grant:r', '*' + sid + ':(OI)(CI)F'])
    else:
        path.chmod(0o700)
    return path


def json_file(path, value):
    with Path(path).open('x', encoding='utf-8') as handle:
        json.dump(value, handle, ensure_ascii=False, indent=2)
        handle.flush(); os.fsync(handle.fileno())
    if os.name != 'nt':
        Path(path).chmod(0o600)


def container(keys, writable=False):
    return ['docker', 'run', '--rm', '-i', '--network', 'none', '--read-only', '--cap-drop=ALL',
            '--security-opt=no-new-privileges:true', '--memory=512m', '--tmpfs', '/tmp:mode=1777',
            '--user', f'{os.getuid()}:{os.getgid()}' if os.name != 'nt' else '1000:1000',
            '--mount', f'type=bind,source={keys},target=/keys' + ('' if writable else ',readonly'), IMAGE]


def openssl_version():
    version = run(['docker', 'run', '--rm', '--network', 'none', '--read-only', IMAGE, 'openssl', 'version']).decode().strip()
    match = re.match(r'OpenSSL (\d+)\.(\d+)\.(\d+)', version)
    if not match or tuple(map(int, match.groups()))[:2] != (3, 5) or int(match[3]) < 9:
        raise RecoveryError('El perfil requiere OpenSSL 3.5.9 o posterior de la rama 3.5, con correcciones CMS.')
    return version


def keygen(keys, password):
    openssl_version()
    keys = private_dir(keys)
    script = ('umask 077; openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out /tmp/raw.key'
              ' && openssl pkcs8 -topk8 -in /tmp/raw.key -v2 aes-256-cbc -iter 600000 -passout stdin -out /keys/recovery.key'
              ' && openssl req -new -x509 -sha256 -days 30 -set_serial 1 -key /tmp/raw.key'
              ' -subj /CN=H72-FICTITIOUS-BACKUP-ONLY -out /keys/recovery.crt')
    run(container(keys, True) + ['sh', '-c', script], password + b'\n')
    return keys


def seal(payload, keys, password):
    if len(payload) > LIMIT:
        raise RecoveryError('Conjunto supera el límite del ensayo en memoria.')
    # La copia plana vive solo en tmpfs del contenedor efímero, nunca en el directorio del respaldo.
    name = 'emisor-h72-crypto-' + uuid.uuid4().hex[:12]
    args = container(keys)
    args.remove('--rm')
    args[args.index('-i')] = '-d'
    run(args[:2] + ['--name', name] + args[2:] + ['sleep', '600'])
    try:
        run(['docker', 'exec', '-i', name, 'sh', '-c', 'umask 077; cat > /tmp/payload.tar'], payload)
        signed = run(['docker', 'exec', '-i', name, 'openssl', 'cms', '-sign', '-binary', '-nodetach', '-md', 'sha256',
                      '-in', '/tmp/payload.tar', '-signer', '/keys/recovery.crt', '-inkey', '/keys/recovery.key',
                      '-passin', 'stdin', '-outform', 'DER'], password + b'\n')
        return run(container(keys) + ['openssl', 'cms', '-encrypt', '-binary', '-aes-256-gcm', '-outform', 'DER',
                                     '/keys/recovery.crt'], signed)
    finally:
        # Solo retirar el contenedor criptográfico creado por esta llamada, sin volúmenes ni evidencias de instalación.
        run(['docker', 'rm', '-f', name])


def unseal(backup, keys, password):
    openssl_version()
    backup = Path(backup)
    if backup.is_symlink() or not 0 < backup.stat().st_size <= LIMIT + 1024 * 1024:
        raise RecoveryError('Contenedor de respaldo no admitido.')
    backup = backup.resolve()
    args = container(keys)
    args.insert(args.index(IMAGE), '--mount')
    args.insert(args.index(IMAGE), f'type=bind,source={backup},target=/backup.cms,readonly')
    signed = run(args + ['openssl', 'cms', '-decrypt', '-binary', '-inform', 'DER', '-in', '/backup.cms',
                         '-recip', '/keys/recovery.crt', '-inkey', '/keys/recovery.key', '-passin', 'stdin'], password + b'\n')
    # El certificado de confianza procede del almacén separado, no de lo que el contenedor quiera declarar.
    return run(container(keys) + ['openssl', 'cms', '-verify', '-binary', '-inform', 'DER', '-nointern', '-no_check_time',
                                  '-certfile', '/keys/recovery.crt', '-CAfile', '/keys/recovery.crt', '-purpose', 'any'], signed)
