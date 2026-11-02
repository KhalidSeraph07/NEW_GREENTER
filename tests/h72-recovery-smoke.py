"""Ensayo integral H7-2. Solo crea instalaciones nuevas; no toca emisor-h1 local."""
import argparse
import hashlib
import io
import json
import os
import secrets
import subprocess
import sys
import tarfile
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from html.parser import HTMLParser
from http.cookiejar import CookieJar
from pathlib import Path
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tools'))
from h72_crypto import RecoveryError, container, digest, json_file, keygen, private_dir, run, seal, unseal
from h72_recovery import Installation, MARKER, OVERRIDE, backup, environment, profile, restore, unpack
from h72_snapshot import GATE, canonical_schema, logical_snapshot, persistent_files


class Forms(HTMLParser):
    def __init__(self, body):
        super().__init__(); self.forms = {}; self.action = None; self.feed(body.decode())
    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'form':
            self.action = urllib.parse.urlsplit(values.get('action', '')).path if values.get('method', 'get').lower() == 'post' else None
            if self.action is not None: self.forms[self.action] = {}
        elif self.action is not None and tag == 'input' and values.get('name'):
            self.forms[self.action][values['name']] = values.get('value', '')
    def handle_endtag(self, tag):
        if tag == 'form': self.action = None


def request(client, base, path, data=None, headers=None):
    encoded = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
    try:
        response = client.open(urllib.request.Request(base + path, data=encoded, headers=headers or {}), timeout=25)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, response.read(), urllib.parse.urlsplit(response.url).path


def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))


def login(c, base, email, password):
    status, body, _ = request(c, base, '/login'); assert status == 200
    return request(c, base, '/login', Forms(body).forms['/login'] | {'email': email, 'password': password})


def wait_http(base):
    deadline = time.monotonic() + 30
    while time.monotonic() < deadline:
        try:
            if request(client(), base, '/login')[0] == 200: return
        except (OSError, urllib.error.URLError): pass
        time.sleep(0.3)
    raise RecoveryError('La interfaz de ensayo no estuvo disponible.')


def prepare(root, project):
    source = private_dir(root / 'source')
    names = run(['git', 'ls-files', '--cached', '--others', '--exclude-standard'], cwd=ROOT).decode().splitlines()
    for name in names:
        original = ROOT / name
        if not original.is_file() or original.is_symlink(): raise RecoveryError('Fuente versionada no regular.')
        destination = source / name; destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_bytes(original.read_bytes())
    env = dict(line.split('=', 1) for line in (ROOT / '.env.example').read_text().splitlines() if '=' in line)
    env.update({'APP_KEY': 'base64:' + __import__('base64').b64encode(secrets.token_bytes(32)).decode(),
                'DB_PASSWORD': secrets.token_hex(24), 'H2_OPERATOR_PASSWORD': 'H72-Aa-' + secrets.token_hex(20),
                'H2_HOST_PORT': '8188', 'SESSION_COOKIE': 'h72_source_' + uuid.uuid4().hex,
                'LOCAL_UID': str(os.getuid() if os.name != 'nt' else 1000), 'LOCAL_GID': str(os.getgid() if os.name != 'nt' else 1000)})
    (source / '.env').write_text(''.join(k + '=' + v + '\n' for k, v in env.items()), encoding='utf-8')
    profile(source, project, ROOT / 'vendor', 'source', names)
    installation = Installation(source)
    installation.compose(['up', '-d', '--wait', 'postgres'])
    installation.compose(['run', '--rm', 'php', 'php', 'artisan', 'poc:invoice', '--database'])
    installation.compose(['run', '--rm', 'php', 'php', 'artisan', 'h2:setup'])
    installation.compose(['run', '--rm', 'php', 'php', 'artisan', 'h2:setup', '--testing'])
    installation.compose(['up', '-d', 'web']); wait_http('http://127.0.0.1:8188')
    passwords = {key: 'H72-Aa-' + secrets.token_hex(24) for key in ('initial', 'admin', 'B')}
    result = json.loads(installation.compose(['run', '--rm', '-T', 'php', 'php', 'tests/h72-fixture.php'], json.dumps(passwords).encode()))
    fixture_dir = private_dir(source / 'storage/audit/h72-seed')
    json_file(fixture_dir / 'accounts.private.json', result | {'new_passwords': {'admin': passwords['admin'], 'receiver': passwords['B']}})
    return installation


def exercise(source, root):
    checks = {}
    def check(name, condition):
        if not condition: raise AssertionError(name)
        checks[name] = True
    def refuse(name, operation):
        try: operation()
        except (Exception, KeyboardInterrupt): checks[name] = True; return
        raise AssertionError(name)
    source_check = b"-- Name: users; Type: TABLE; Schema: public; Owner: emisor_h1\n    CONSTRAINT users_role CHECK (((role)::text = ANY ((ARRAY['ADMIN'::character varying, 'OPERADOR'::character varying])::text[])))"
    restored_check = b"-- Name: users; Type: TABLE; Schema: public; Owner: emisor_h1\n    CONSTRAINT users_role CHECK (((role)::text = ANY (ARRAY[('ADMIN'::character varying)::text, ('OPERADOR'::character varying)::text])))"
    check('only_lossless_pg16_array_casts_canonicalized', canonical_schema(source_check) == canonical_schema(restored_check))
    check('changed_role_or_removed_constraint_still_changes_schema', canonical_schema(source_check) != canonical_schema(restored_check.replace(b'OPERADOR', b'RECEPTOR'))
          and canonical_schema(source_check) != canonical_schema(b''))
    check('function_bodies_never_normalized', canonical_schema(source_check.replace(b'Type: TABLE;', b'Type: FUNCTION;'))
          != canonical_schema(restored_check.replace(b'Type: TABLE;', b'Type: FUNCTION;')))
    unattended_keys = root / 'no-tty-keys'
    unattended = subprocess.run([sys.executable, str(ROOT / 'tools/h72_recovery.py'), 'keygen', '--keys', str(unattended_keys)],
                               input=b'', stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    check('operator_cli_refuses_visible_or_unattended_password_input', unattended.returncode != 0
          and b'requiere TTY' in unattended.stdout and not unattended_keys.exists())
    env = environment(source.root); base = 'http://127.0.0.1:' + env['H2_HOST_PORT']
    c = client(); assert login(c, base, 'operador.h2@example.invalid', env['H2_OPERATOR_PASSWORD'])[0] == 200
    status, body, _ = request(c, base, '/invoices/create')
    form = Forms(body).forms['/invoices/review'] | {'description': 'FACTURA PENDIENTE FICTICIA H72', 'quantity': '1', 'unit_price': '50.00'}
    assert request(c, base, '/invoices/review', form)[0] == 200
    status, _, document = request(c, base, '/invoices', form); assert status == 200
    before_xml = request(c, base, document + '/evidence/xml')[1]
    before_zip = request(c, base, document + '/evidence/zip')[1]
    fixture = json.loads((source.root / 'storage/audit/h72-seed/accounts.private.json').read_text())
    receiver = fixture['accounts']['B']; receiver_password = fixture['new_passwords']['receiver']
    grant = ("require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();"
             "$v=json_decode(stream_get_contents(STDIN),true);$r=app(App\\Recipients\\Access::class)->grant(1,$v['recipient'],'invoice',$v['id']);echo json_encode(['access'=>$r->id]);")
    result = source.compose(['run', '--rm', '-T', 'php', 'php', '-r', grant], json.dumps({'recipient': receiver['id'], 'id': document.rsplit('/', 1)[1]}).encode())
    access = json.loads(result)['access']
    orphan = source.root / 'storage/app/private/h72-orphan-intentional.txt'
    orphan.write_text('HUERFANO FICTICIO NUEVO H72, CONSERVAR SIN LIMPIAR\n', encoding='utf-8')
    json_file(source.root / 'storage/audit/h72-fixture.json', {'orphan': orphan.name, 'origin': 'Declaración explícita del ensayo H72, no incidente histórico reconstruido'})
    source_files = persistent_files(source.root); before = logical_snapshot(source)
    check('source_has_pending_and_uncertain_operations', before['emisor_h1']['states']['invoices.transport_status'].get('simulation_uncertain', 0) > 0
          and before['emisor_h1']['states']['invoices.transport_status'].get('pending_simulation', 0) > 0)
    check('source_has_voids_notes_members_and_recovery_audit', all(before['emisor_h1']['tables'][t]['count'] > 0 for t in
          ['h5c1_batches', 'h5_notes', 'h5b_notes', 'h4_members', 'local_access_recoveries', 'recipient_document_access']))
    check('source_has_authenticated_file_sessions', any((source.root / 'storage/framework/sessions').iterdir()))
    password = ('H72-Aa-' + secrets.token_hex(24)).encode(); keys = keygen(root / 'keys', password)
    operator_secrets = private_dir(root / 'operator-secrets')
    json_file(operator_secrets / 'fixture.private.json', {'scope': 'CONTRASEÑA SINTÉTICA SOLO PARA REPETIR ESTE ENSAYO LOCAL',
                                                        'password': password.decode()})
    refuse('original_installation_refused', lambda: Installation(ROOT))
    receipt = backup(source.root, root / 'backups', keys, password)
    refuse('source_capture_blocks_cli_dispatch', lambda: source.compose(['run', '--rm', 'php', 'php', 'artisan', 'h2:dispatch']))
    archive = root / 'backups/backup.cms'
    check('backup_is_authenticated_ciphertext', receipt['valid'] and digest(archive.read_bytes()) == receipt['cipher_sha256'])
    files, manifest = unpack(unseal(archive, keys, password))
    check('no_sessions_or_decryption_keys_in_archive', not any('framework/sessions/' in p or p.endswith('recovery.key') for p in files))
    check('configuration_and_verification_certificates_are_encrypted_members', 'files/.env' in files and any('certificate-test.pem' in p for p in files))
    check('orphan_preserved_and_identified', 'storage/app/private/h72-orphan-intentional.txt' in manifest['correlation']['unreferenced_preserved'])
    source.compose(['stop', 'postgres'])  # Pérdida simulada: origen de datos fuera de servicio durante toda restauración.
    wrong_target = root / 'wrong-password'
    refuse('wrong_password_blocks_restore', lambda: restore(archive, wrong_target, source.project + '-wrong', 8192, keys, b'incorrecta-H72'))
    check('wrong_password_has_no_verified_marker', json.loads((wrong_target / GATE).read_text())['status'] == 'restoring')
    wrong_keys = keygen(root / 'wrong-keys', password)
    refuse('wrong_key_blocks_restore', lambda: restore(archive, root / 'wrong-key', source.project + '-key', 8193, wrong_keys, password))
    damaged = bytearray(archive.read_bytes()); damaged[-20] ^= 1
    altered = root / 'backups/altered.cms'; altered.write_bytes(damaged)
    refuse('altered_ciphertext_blocks_restore', lambda: restore(altered, root / 'altered', source.project + '-altered', 8194, keys, password))
    original_tar = tarfile.open(fileobj=io.BytesIO(unseal(archive, keys, password)), mode='r:')
    stream = io.BytesIO()
    with tarfile.open(fileobj=stream, mode='w') as tar:
        for member in original_tar:
            if member.name != 'databases/emisor_h2_test.dump': tar.addfile(member, original_tar.extractfile(member))
    incomplete = root / 'backups/incomplete.cms'; incomplete.write_bytes(seal(stream.getvalue(), keys, password))
    refuse('authenticated_but_incomplete_set_refused', lambda: restore(incomplete, root / 'incomplete', source.project + '-incomplete', 8195, keys, password))
    forged = run(container(keys) + ['openssl', 'cms', '-encrypt', '-binary', '-aes-256-gcm', '-outform', 'DER', '/keys/recovery.crt'], b'Not signed by the pinned recovery key')
    forged_path = root / 'backups/unsigned.cms'; forged_path.write_bytes(forged)
    refuse('public_key_encryption_alone_cannot_forge_signed_backup', lambda: restore(forged_path, root / 'unsigned', source.project + '-unsigned', 8196, keys, password))
    interrupted = root / 'interrupted'; checkpoint = root / 'interrupted.checkpoint'
    worker = subprocess.Popen([sys.executable, str(ROOT / 'tests/h72-restore-worker.py'), str(archive), str(interrupted),
                               source.project + '-interrupted', str(keys), str(checkpoint)], stdin=subprocess.PIPE,
                              stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    worker.stdin.write(password + b'\n'); worker.stdin.close()
    deadline = time.monotonic() + 90
    while not checkpoint.is_file():
        if worker.poll() is not None or time.monotonic() > deadline: raise RecoveryError('No se alcanzó interrupción controlada; conservar destino parcial.')
        time.sleep(0.1)
    worker.kill(); worker.wait(timeout=20)
    check('interrupted_restore_remains_blocked', worker.returncode != 0 and json.loads((interrupted / GATE).read_text())['status'] == 'restoring')
    partial = Installation(interrupted)
    refuse('partial_destination_cli_cannot_dispatch', lambda: partial.compose(['run', '--rm', 'php', 'php', 'artisan', 'h2:dispatch']))
    partial_web = partial.compose(['run', '-d', '--rm', '--service-ports', 'web', 'php', '-S', '0.0.0.0:8080', '-t', 'public']).decode().strip()
    try:
        deadline = time.monotonic() + 20
        while True:
            try:
                status, _, _ = request(client(), 'http://127.0.0.1:8197', '/login')
                break
            except (OSError, urllib.error.URLError):
                if time.monotonic() > deadline: raise
                time.sleep(0.2)
        check('partial_destination_http_consultation_blocked', status == 503)
    finally:
        run(['docker', 'stop', partial_web])
    refuse('partial_destination_not_overwritten_on_retry', lambda: restore(archive, interrupted, partial.project, 8197, keys, password))
    destination = root / 'destination'; destination_project = source.project + '-restored'
    restored = restore(archive, destination, destination_project, 8189, keys, password)
    target = Installation(destination)
    check('empty_destination_restored_from_bundle_only', restored['verified'] and restored['snapshot'] == before)
    check('all_files_and_references_restored', restored['correlation'] == manifest['correlation'])
    check('no_authenticated_sessions_restored', not any((destination / 'storage/framework/sessions').iterdir()))
    check('distinct_volume_and_network_identity', destination_project != source.project and environment(destination)['DB_PASSWORD'] != env['DB_PASSWORD'])
    check('application_key_recovered_cookie_namespace_independent', environment(destination)['APP_KEY'] == env['APP_KEY']
          and environment(destination)['SESSION_COOKIE'] != env['SESSION_COOKIE'])
    refuse('verified_destination_cli_transport_still_blocked', lambda: target.compose(['run', '--rm', 'php', 'php', 'artisan', 'h2:dispatch']))
    target.compose(['up', '-d', 'web']); target_base = 'http://127.0.0.1:8189'; wait_http(target_base)
    check('anonymous_document_download_denied', request(client(), target_base, document + '/evidence/xml')[2] == '/login')
    old_cookie = next(cookie.value for handler in c.handlers if hasattr(handler, 'cookiejar') for cookie in handler.cookiejar if cookie.name == env['SESSION_COOKIE'])
    check('copied_source_cookie_cannot_authenticate', request(client(), target_base, '/', headers={'Cookie': environment(destination)['SESSION_COOKIE'] + '=' + old_cookie})[2] == '/login')
    restored_client = client(); check('restored_user_can_login', login(restored_client, target_base, 'operador.h2@example.invalid', env['H2_OPERATOR_PASSWORD'])[0] == 200)
    check('restored_document_exact_xml', request(restored_client, target_base, document + '/evidence/xml')[1] == before_xml)
    check('restored_document_exact_zip', request(restored_client, target_base, document + '/evidence/zip')[1] == before_zip)
    status, body, _ = request(restored_client, target_base, document)
    token = Forms(body).forms['/logout']['_token']
    check('http_transport_and_fiscal_writes_blocked', all(request(restored_client, target_base, path, {'_token': token})[0] == 423 for path in
          [document + '/simulate', '/invoices/review', '/summaries', '/voids', '/receipt-notes']))
    rc = client(); check('receiver_login_and_explicit_grant_preserved', login(rc, target_base, receiver['email'], receiver_password)[0] == 200
                         and request(rc, target_base, '/recipient/' + access + '/evidence/xml')[1] == before_xml)
    check('receiver_admin_access_denied', request(rc, target_base, '/accounts')[0] == 403)
    administrator = fixture['accounts']['admin']; ac = client()
    check('admin_credentials_role_and_ownership_preserved', login(ac, target_base, administrator['email'], fixture['new_passwords']['admin'])[0] == 200
          and request(ac, target_base, '/accounts')[0] == 200 and request(ac, target_base, document)[0] == 404)
    check('no_automatic_outbox_or_audit_changes_after_queries', logical_snapshot(target) == before)
    source.compose(['up', '-d', '--wait', 'postgres'])
    check('source_unchanged_after_destination_activity', logical_snapshot(source) == before and persistent_files(source.root) == source_files)
    import h72_recovery
    persist = h72_recovery.json_file; injected = False
    def fail_verification_report(path, value):
        nonlocal injected
        if Path(path).name == '.h72-restored.json':
            injected = True
            raise OSError('Fallo de persistencia H72 exclusivamente simulado')
        return persist(path, value)
    report_failure = root / 'report-failure'
    with patch('h72_recovery.json_file', fail_verification_report):
        refuse('verification_report_failure_refuses_success', lambda: restore(archive, report_failure, source.project + '-report-failed', 8198, keys, password))
    check('verification_report_failure_keeps_queries_blocked', injected and json.loads((report_failure / GATE).read_text())['status'] == 'restoring')
    refuse('verification_report_failure_keeps_cli_blocked', lambda: Installation(report_failure).compose(['run', '--rm', 'php', 'php', 'artisan', 'h2:dispatch']))
    report = {'scope': 'H7-2 RESPALDO Y RESTAURACION FICTICIOS LOCALES SIN SUNAT', 'checks': checks,
              'backup': receipt, 'restoration_seconds': restored['elapsed_seconds'], 'correlation': manifest['correlation'],
              'snapshot_before': before, 'snapshot_after': restored['snapshot'], 'projects': [source.project, destination_project],
              'limitations': ['Separación local, no respaldo externo de VPS', 'Límite 64 MiB y memoria del ensayo',
                              'Custodia comercial de claves e infraestructura pendientes; destino solo consulta',
                              'Orígenes/destinos parciales y huérfanos conservados, sin limpieza automática']}
    json_file(root / 'result.json', report)
    print(json.dumps({'scope': report['scope'], 'checks': checks, 'backup_seconds': receipt['elapsed_seconds'],
                      'restoration_seconds': restored['elapsed_seconds'], 'referenced_files': manifest['correlation']['referenced_files']}))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(); parser.add_argument('--root', type=Path, required=True)
    args = parser.parse_args(); rehearsal = private_dir(args.root)
    try:
        installation = prepare(rehearsal, 'emisor-h72-' + uuid.uuid4().hex[:10] + '-source')
        exercise(installation, rehearsal)
    except Exception as error:
        json_file(rehearsal / 'failure.json', {'type': type(error).__name__, 'message': str(error) if isinstance(error, (RecoveryError, AssertionError)) else 'Fallo del ensayo; revisar informes privados sin secretos.'})
        raise
