"""Inventario lógico y de evidencias del perfil ficticio actual, sin reparar datos."""
import json
import re
from pathlib import Path
from h72_crypto import RecoveryError, digest

DATABASES = ('emisor_h1', 'emisor_h2_test')
EPHEMERAL = ('storage/framework/sessions/', 'storage/framework/views/', 'storage/framework/cache/', 'bootstrap/cache/')
GATE = 'storage/framework/h72-state.json'
ARTIFACTS = {
    'invoices': ('h2', 'invoice-test.zip'), 'h4_boletas': ('h4', 'document.zip'),
    'h4_summaries': ('h4', 'document.zip'), 'h5_notes': ('h5', 'document.zip'),
    'h5b_notes': ('h5b', 'document.zip'), 'h5b_summaries': ('h5b', 'document.zip'),
    'h5c1_batches': ('h5c1', 'document.zip'),
}


def canonical_schema(schema):
    text = '\n'.join(line for line in schema.decode().splitlines() if not line.startswith(('\\restrict ', '\\unrestrict ')))
    # PG16 redistribuye el cast de arrays varchar sin límite al volver a parsear el dump.
    # Normalizar únicamente constantes simples varchar→text; no quitar checks, valores ni condiciones.
    literal = r"'[A-Za-z0-9_-]*'"
    source = r'ANY \(\(ARRAY\[((' + literal + r'::character varying)(, ' + literal + r'::character varying)*)\]\)::text\[\]\)'
    target = r'ANY \(ARRAY\[((\(' + literal + r'::character varying\)::text)(, \(' + literal + r'::character varying\)::text)*)\]\)'
    def constants(match):
        values = re.findall(literal, match.group(1))
        return 'ANY (ARRAY[' + ', '.join(value + '::text' for value in values) + '])'
    table = False
    lines = []
    for line in text.split('\n'):
        if line.startswith('-- Name: '):
            table = '; Type: TABLE; Schema: public;' in line
        if table and line.startswith('    CONSTRAINT ') and ' CHECK ' in line:
            line = re.sub(target, constants, re.sub(source, constants, line))
        lines.append(line)
    return '\n'.join(lines).encode()


def valid_name(name):
    if not re.fullmatch(r'[a-z_][a-z_0-9]*', name):
        raise RecoveryError('Identificador SQL fuera del perfil.')
    return '"' + name + '"'


def safe_file(root, relative):
    p = Path(relative)
    if p.is_absolute() or '\\' in relative or ':' in relative or '\x00' in relative or any(s in ('', '..', '.') or s.endswith((' ', '.')) for s in relative.split('/')):
        raise RecoveryError('Ruta de archivo no admitida.')
    current = root
    for part in p.parts:
        current = current / part
        if current.is_symlink():
            raise RecoveryError('No se admiten rutas enlazadas.')
    if not current.resolve().is_relative_to(root.resolve()):
        raise RecoveryError('Ruta fuera de la instalación.')
    return current


def persistent_files(root):
    result = {}
    for directory in ('storage',):
        for p in sorted((root / directory).rglob('*')):
            rel = p.relative_to(root).as_posix()
            if rel == GATE or rel.startswith(EPHEMERAL):
                continue
            if p.is_symlink():
                raise RecoveryError('No se respaldan enlaces de almacenamiento.')
            if p.is_file():
                safe_file(root, rel)
                result[rel] = p.read_bytes()
    return result


def logical_snapshot(installation):
    databases = installation.sql('postgres', "SELECT datname FROM pg_database WHERE NOT datistemplate ORDER BY datname").decode().splitlines()
    if set(databases) != {'postgres', *DATABASES}:
        raise RecoveryError('El ensayo requiere ambas bases conocidas y ninguna base de negocio adicional.')
    roles = installation.sql('postgres', "SELECT rolname FROM pg_roles WHERE rolname NOT LIKE 'pg_%' ORDER BY rolname").decode().splitlines()
    if roles != ['emisor_h1']:
        raise RecoveryError('Roles PostgreSQL adicionales requieren un procedimiento revisado.')
    snapshot = {'cluster': {
        'roles': installation.sql('postgres', "SELECT rolname,rolsuper,rolinherit,rolcreaterole,rolcreatedb,rolcanlogin,rolreplication,rolbypassrls,rolconnlimit FROM pg_roles ORDER BY rolname").decode(),
        'memberships': installation.sql('postgres', "SELECT r.rolname,m.rolname,a.admin_option FROM pg_auth_members a JOIN pg_roles r ON r.oid=a.roleid JOIN pg_roles m ON m.oid=a.member ORDER BY 1,2").decode(),
    }}
    if installation.sql('postgres', 'SELECT count(*) FROM pg_db_role_setting').strip() != b'0':
        raise RecoveryError('Configuración de roles/bases adicional requiere procedimiento revisado.')
    for db in DATABASES:
        tables = installation.sql(db, "SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename").decode().splitlines()
        schema = installation.compose(['exec', '-T', 'postgres', 'pg_dump', '-U', 'emisor_h1', '-d', db, '--schema-only'])
        schema = canonical_schema(schema)
        settings = installation.sql('postgres', f"SELECT pg_encoding_to_char(encoding),datcollate,datctype,datconnlimit FROM pg_database WHERE datname='{db}'").decode()
        current = {'schema_sha256': digest(schema), 'schema_canonicalization': 'PG16-varchar-text-constants-1',
                   'database_settings': settings, 'tables': {}, 'sequences': {}, 'states': {}}
        seqs = installation.sql(db, "SELECT sequencename FROM pg_sequences WHERE schemaname='public' ORDER BY sequencename").decode().splitlines()
        statements = [f"SELECT '{table}|'||to_jsonb(t)::text FROM {valid_name(table)} t ORDER BY to_jsonb(t)::text;" for table in tables]
        statements += [f"SELECT '__seq_{seq}|'||last_value||'|'||is_called FROM {valid_name(seq)};" for seq in seqs]
        collected = {table: [] for table in tables}
        for line in installation.sql(db, '\n'.join(statements)).splitlines():
            name, value = line.split(b'|', 1); name = name.decode()
            if name.startswith('__seq_'):
                current['sequences'][name[6:]] = value.decode()
            else:
                collected[name].append(value)
        for table in tables:
            rows = collected[table]
            current['tables'][table] = {'count': len(rows), 'sha256': digest(b'\n'.join(rows))}
            if table in ARTIFACTS or table.endswith(('_outbox', '_members')) or table in ('local_access_recoveries', 'recipient_document_access'):
                # Distribución de estados, sin publicar identificaciones, contraseñas ni hashes de usuarios.
                values = [json.loads(line) for line in rows]
                for field in ('status', 'transport_status', 'outcome', 'state', 'ticket'):
                    counts = {}
                    for row in values:
                        if field in row:
                            key = 'present' if field == 'ticket' and row[field] else str(row[field])
                            counts[key] = counts.get(key, 0) + 1
                    if counts:
                        current['states'][table + '.' + field] = counts
        snapshot[db] = current
    return snapshot


def correlate(installation, files):
    referenced, counts = set(), {}
    names = {'unsigned': 'unsigned.xml', 'xml': 'signed.xml', 'certificate': 'certificate-test.pem'}
    for db in DATABASES:
        commands = [f"SELECT '{table}|'||id||'|'||artifacts::text FROM {valid_name(table)} ORDER BY id;" for table in ARTIFACTS]
        grouped = {table: [] for table in ARTIFACTS}
        for line in installation.sql(db, '\n'.join(commands)).decode().splitlines():
            table, record = line.split('|', 1); grouped[table].append(record)
        for table, (family, zip_name) in ARTIFACTS.items():
            rows = grouped[table]
            counts[db + '.' + table] = len(rows)
            for line in rows:
                identity, serialized = line.split('|', 1)
                if not re.fullmatch(r'[0-9a-f-]{36}', identity):
                    raise RecoveryError('Identidad de evidencia inválida.')
                base = f'storage/app/private/{family}' if db == 'emisor_h1' else f'storage/framework/testing/{family}'
                for kind, entry in json.loads(serialized).items():
                    if kind not in (*names, 'zip'):
                        raise RecoveryError('Clase de evidencia desconocida.')
                    relative = f'{base}/{identity}/{zip_name if kind == "zip" else names[kind]}'
                    data = files.get(relative)
                    if data is None or len(data) != entry['bytes'] or digest(data) != entry['sha256']:
                        raise RecoveryError('Referencia PostgreSQL sin evidencia exacta; no declarar respaldo íntegro.')
                    referenced.add(relative)
    # Los archivos no referenciados se conservan: el inventario no autoriza eliminarlos ni reparar fixtures.
    unreferenced = sorted(p for p in files if p.startswith(('storage/app/private/', 'storage/framework/testing/')) and p not in referenced)
    return {'referenced_files': len(referenced), 'documents': counts, 'unreferenced_preserved': unreferenced}
