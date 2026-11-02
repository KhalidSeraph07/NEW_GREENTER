"""Dos instalaciones nuevas H7-3. No usa restauración H7-2 ni modifica emisor-h1."""
import argparse
import base64
import copy
import csv
import hashlib
import ipaddress
import json
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from html.parser import HTMLParser
from http.cookiejar import Cookie, CookieJar

ROOT = Path(__file__).resolve().parents[1]
DIAGNOSTICS = None


def run(command, *, cwd=ROOT, env=None, data=None):
    r = subprocess.run(command, cwd=cwd, env=env, input=data, capture_output=True, text=True, encoding='utf-8')
    if r.returncode:
        # Compose puede incluir valores interpolados; no volcar stdout/stderr en informes públicos.
        if DIAGNOSTICS is not None:
            save(DIAGNOSTICS/('process-failure-'+secrets.token_hex(4)+'.private.json'),
                 {'program':command[0], 'exit':r.returncode, 'stdout':r.stdout, 'stderr':r.stderr})
        raise RuntimeError('Fallo de proceso del ensayo: '+command[0]+'; revisar recursos privados conservados.')
    return r.stdout


def private_root(path):
    path.mkdir(mode=0o700, parents=True, exist_ok=False)
    if os.name == 'nt':
        sid = next(csv.reader([run(['whoami', '/user', '/fo', 'csv', '/nh']).strip()]))[1]
        run(['icacls', str(path), '/inheritance:r', '/grant:r', '*'+sid+':(OI)(CI)F'])


def save(path, value):
    with path.open('x', encoding='utf-8') as f:
        json.dump(value, f, indent=2)
    path.chmod(0o600)


def fingerprint(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_files(image, before=None):
    # Docker interpreta los enlaces Linux de las fixtures; Windows no siempre puede hacer stat sobre ellos.
    php = r'''$root='/protected/';$before=json_decode(stream_get_contents(STDIN),true);
    $read=static function($p){if(is_link($p)){return ['link'=>readlink($p)];}
        if(!is_file($p)||!is_readable($p)){throw new RuntimeException('Archivo protegido ilegible');}
        return ['sha256'=>hash_file('sha256',$p)];};
    if($before!==null){foreach($before as $p=>$expected){
        if(str_contains($p,'..')||str_starts_with($p,'/')){exit(2);}
        if($read($root.$p)!==$expected){exit(3);}}
        echo json_encode(['preserved'=>true,'count'=>count($before)]);
    }else{$files=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $f){
        if($f->isLink()||$f->isFile()){$files[substr($f->getPathname(),strlen($root))]=$read($f->getPathname());}}
        ksort($files);echo json_encode($files,JSON_THROW_ON_ERROR);}'''
    return json.loads(run(['docker','run','--rm','-i','--network','none','--read-only','--user','0:0',
                          '--cap-drop','ALL','--cap-add','DAC_OVERRIDE','--security-opt','no-new-privileges:true',
                          '--mount','type=bind,source='+str(ROOT/'storage')+',target=/protected,readonly',
                          image,'php','-r',php], data=json.dumps(before)))


class Inputs(HTMLParser):
    def __init__(self, body):
        super().__init__(); self.values = {}; self.feed(body.decode())

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'input' and a.get('name'):
            self.values[a['name']] = a.get('value', '')


def http(opener, base, path, data=None, headers=None):
    assert urllib.parse.urlsplit(base).hostname == '127.0.0.1', 'Solo loopback'
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    try:
        r = opener.open(urllib.request.Request(base+path, payload, headers or {}), timeout=30)
    except urllib.error.HTTPError as e:
        r = e
    return r.status, r.read(), urllib.parse.urlsplit(r.url).path


def worker(v):
    jar = CookieJar(); opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    base = v['base']
    if v['mode'] == 'create':
        def login(password):
            _, body, _ = http(opener, base, '/login')
            return http(opener, base, '/login', {'_token':Inputs(body).values['_token'], 'email':v['email'], 'password':password})
        assert login(v['peer_password'])[2] == '/login', 'Credencial ajena aceptada'
        assert login(v['password'])[2] == '/', 'Login propio fallido'
        _, body, _ = http(opener, base, '/invoices/create')
        fields = Inputs(body).values
        fields = {k:fields[k] for k in ['_token', 'idempotency_key']} | {
            'description':'PRODUCTO FICTICIO H73 '+v['tag'], 'quantity':'1', 'unit_price':v['price']}
        assert http(opener, base, '/invoices/review', fields)[0] == 200
        status, body, path = http(opener, base, '/invoices', fields)
        assert status == 200 and path.startswith('/invoices/') and 'SIN' in body.decode()
        assert http(opener, base, '/invoices', fields)[2] == path, 'Confirmación no idempotente'
        status, xml, _ = http(opener, base, path+'/evidence/xml')
        assert status == 200 and ('PRODUCTO FICTICIO H73 '+v['tag']).encode() in xml
        return {'id':path.rsplit('/', 1)[1], 'xml_sha256':hashlib.sha256(xml).hexdigest(),
                'cookies':{c.name:c.value for c in jar}, 'pid':os.getpid(), 'idempotent':True}
    headers = {'Accept':'application/json', 'Cookie':'; '.join(k+'='+s for k, s in v['cookies'].items())}
    if v['mode'] == 'cookie':
        return {'denied':http(opener, base, '/', headers=headers)[0] == 401}
    status, _, _ = http(opener, base, '/', headers=headers)
    result = {'authenticated':status == 200, 'pid':os.getpid()}
    if v.get('id'):
        status, xml, _ = http(opener, base, '/invoices/'+v['id']+'/evidence/xml', headers=headers)
        result['xml_preserved'] = status == 200 and hashlib.sha256(xml).hexdigest() == v['xml_sha256']
    if v.get('foreign_id'):
        result['foreign_denied'] = all(http(opener, base, p, headers=headers)[0] == 404 for p in [
            '/invoices/'+v['foreign_id'], '/invoices/'+v['foreign_id']+'/evidence/xml',
            '/storage/app/private/h2/'+v['foreign_id']+'/signed.xml', '/.env'])
    return result


def worker_start(v):
    p = subprocess.Popen([sys.executable, str(Path(__file__).resolve()), '--http-worker'],
                         stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, encoding='utf-8')
    return p, json.dumps(v)


def worker_end(job):
    p, data = job; out, error = p.communicate(data, timeout=150)
    if p.returncode:
        if DIAGNOSTICS is not None:
            save(DIAGNOSTICS/('http-failure-'+secrets.token_hex(4)+'.private.json'), {'exit':p.returncode, 'stdout':out, 'stderr':error})
        raise RuntimeError('Cliente HTTP independiente fallido; revisar el ensayo conservado, sin revelar sesión.')
    return json.loads(out)


def compose(install, *args, data=None):
    return run(['docker', 'compose', '--env-file', str(install['path']/'.env'), '-f', 'compose.yaml', '-f', 'h73-compose.json', *args],
               cwd=install['path'], env=install['env'], data=data)


def preflight(installs, configs):
    names, ports, volumes, networks, binds = set(), set(), set(), set(), set()
    for i, c in zip(installs, configs):
        assert c['name'] == i['project'] and c['name'] not in names, 'Colisión de proyecto'
        names.add(c['name'])
        assert set(c['services']) == {'php', 'web', 'postgres'}
        volume = c['volumes']['postgres_data']['name']
        assert volume == i['project']+'_postgres_data' and volume not in volumes, 'Colisión de volumen'
        volumes.add(volume)
        for key in ['local', 'browser']:
            name = c['networks'][key]['name']
            assert name == i['project']+'_'+key and name not in networks, 'Colisión de red'
            networks.add(name)
        assert c['networks']['local']['internal'] is True
        allowed = {str(i['path'].resolve()):('/workspace', True), str((i['path']/'storage').resolve()):('/workspace/storage', False),
                   str((i['path']/'bootstrap/cache').resolve()):('/workspace/bootstrap/cache', False),
                   str((ROOT/'vendor').resolve()):('/workspace/vendor', True)}
        for name, service in c['services'].items():
            assert 'container_name' not in service and service['image'] == i['images']['postgres' if name == 'postgres' else 'php']
            assert set(service['networks']) == ({'local','browser'} if name == 'web' else {'local'})
            if name == 'web':
                p = service['ports']; assert len(p) == 1 and p[0]['host_ip'] == '127.0.0.1' and p[0]['target'] == 8080
                port = int(p[0]['published']); assert port == i['port'] and port not in ports, 'Colisión de puerto'
                ports.add(port)
            else:
                assert not service.get('ports'), 'Puerto innecesario'
            if name == 'postgres':
                assert service['volumes'] == [{'type':'volume', 'source':'postgres_data', 'target':'/var/lib/postgresql/data', 'volume':{}}]
            else:
                actual = {str(Path(m['source']).resolve()):(m['target'], m.get('read_only', False)) for m in service['volumes']}
                assert len(service['volumes']) == 4 and actual == allowed, 'Montaje ajeno o inesperado'
        private = {str((i['path']/p).resolve()) for p in ['storage', 'bootstrap/cache']}
        assert not private & binds, 'Almacenamiento compartido'
        binds |= private


def probe(i, mode, **data):
    return json.loads(compose(i, 'run', '--rm', '--no-deps', '--pull', 'never', '-T', 'php', 'php', 'tests/h73-probe.php',
                              data=json.dumps({'mode':mode, **data})))


def ready(i):
    for attempt in range(30):
        try:
            if http(urllib.request.build_opener(), i['base'], '/login')[0] == 200: return
        except OSError: pass
        time.sleep(1)
    raise RuntimeError('HTTP local no disponible')


def exercise(args):
    global DIAGNOSTICS
    assert len(set(args.ports)) == 2 and all(1024 <= p <= 65535 for p in args.ports)
    cidrs = [ipaddress.ip_network(c) for c in args.cidrs]
    assert all(c.version == 4 and c.is_private and c.prefixlen == 28 for c in cidrs)
    assert all(not a.overlaps(b) for n, a in enumerate(cidrs) for b in cidrs[n+1:])
    for port in args.ports:
        with socket.socket() as s:
            s.bind(('127.0.0.1', port))
    target = args.root.resolve(); assert not target.is_relative_to(ROOT) and not ROOT.is_relative_to(target)
    private_root(target); DIAGNOSTICS = target; checks = {}
    def check(name, value):
        assert value, name
        checks[name] = True
    check('new_external_private_root', target.is_dir())
    tracked = run(['git', 'ls-files', '-z']).split('\0')
    images = {name:json.loads(run(['docker','image','inspect','emisor-h1-'+name+':local']))[0]['Id'] for name in ['php','postgres']}
    original = source_files(images['php'])
    original_env = fingerprint(ROOT/'.env') if (ROOT/'.env').exists() else None
    save(target/'preservation-before.json', original)
    def original_containers():
        found = run(['docker','ps','-aq','--filter','label=com.docker.compose.project=emisor-h1']).split()
        return sorted([{'Id':c['Id'], 'Mounts':c['Mounts'], 'State':{k:c['State'][k] for k in ['Running','StartedAt','FinishedAt','Pid','ExitCode']}}
                       for c in json.loads(run(['docker','inspect',*found]))], key=lambda c:c['Id']) if found else []
    source_containers = original_containers()
    assert (ROOT/'vendor/autoload.php').is_file(), 'Reutilizar dependencias aprobadas antes del ensayo'
    installs = []; token = secrets.token_hex(5)
    for n, tag in enumerate(['a', 'b']):
        path = target/tag; path.mkdir(mode=0o700)
        for relative in filter(None, tracked):
            if relative.startswith(('storage/', 'bootstrap/cache/')): continue
            source = ROOT/relative; assert source.is_file() and not source.is_symlink()
            dest = path/relative; dest.parent.mkdir(parents=True, exist_ok=True); shutil.copyfile(source, dest)
        # Este archivo puede ser nuevo y aún no estar indexado durante la primera ejecución local.
        shutil.copyfile(ROOT/'tests/h73-probe.php', path/'tests/h73-probe.php')
        for p in ['storage/app', 'storage/framework', 'bootstrap/cache', 'vendor']:
            (path/p).mkdir(parents=True, exist_ok=True)
        project = 'emisor-h73-'+token+'-'+tag
        values = dict(line.split('=',1) for line in (ROOT/'.env.example').read_text().splitlines() if '=' in line and not line.startswith('#'))
        values |= {'APP_NAME':'EmisorH73'+tag.upper(), 'APP_KEY':'base64:'+base64.b64encode(secrets.token_bytes(32)).decode(),
                   'DB_PASSWORD':secrets.token_hex(32), 'H2_OPERATOR_PASSWORD':'H73-Aa-'+secrets.token_hex(24),
                   'COMPOSE_PROJECT_NAME':project, 'SESSION_COOKIE':'emisor_h73_'+token+'_'+tag,
                   'H2_HOST_PORT':str(args.ports[n]), 'LOCAL_UID':str(os.getuid() if hasattr(os,'getuid') else 1000),
                   'LOCAL_GID':str(os.getgid() if hasattr(os,'getgid') else 1000)}
        with (path/'.env').open('x', encoding='utf-8') as f:
            f.write('\n'.join(k+'='+v for k,v in values.items())+'\n')
        (path/'.env').chmod(0o600)
        env = {k:v for k,v in os.environ.items() if k not in values and not k.startswith('COMPOSE_')}
        env['COMPOSE_PROJECT_NAME'] = project
        account = {'email':'operador-'+tag+'-'+token+'@example.invalid', 'password':'H73-Aa-'+secrets.token_hex(24)}
        i = {'path':path, 'project':project, 'port':args.ports[n], 'base':'http://127.0.0.1:'+str(args.ports[n]),
             'env':env, 'values':values, 'account':account, 'images':images, 'tag':tag.upper()}
        override = {'services':{s:{'image':images['postgres' if s == 'postgres' else 'php']} for s in ['postgres','php','web']},
                    'networks':{k:{'ipam':{'config':[{'subnet':args.cidrs[n*2+j]}]}} for j,k in enumerate(['local','browser'])}}
        for s in ['php','web']:
            override['services'][s]['volumes'] = [{'type':'bind','source':str(ROOT/'vendor'),'target':'/workspace/vendor','read_only':True}]
        save(path/'h73-compose.json', override); save(path/'storage/framework/h73-test.json', {'project':project})
        save(path/'accounts.private.json', account)
        assert not run(['docker','ps','-aq','--filter','label=com.docker.compose.project='+project]).strip(), 'Proyecto existente'
        assert not run(['docker','volume','ls','-q','--filter','name='+project+'_postgres_data']).strip(), 'Volumen existente'
        installs.append(i)
    configs = [json.loads(compose(i, 'config', '--format', 'json')) for i in installs]
    preflight(installs, configs); check('effective_compose_preflight_before_start', True)
    for what in ['name','port','mount']:
        invalid = copy.deepcopy(configs)
        if what == 'name': invalid[1]['name'] = invalid[0]['name']
        elif what == 'port': invalid[1]['services']['web']['ports'][0]['published'] = invalid[0]['services']['web']['ports'][0]['published']
        else: invalid[1]['services']['web']['volumes'][0]['source'] = str(installs[0]['path']/'storage')
        try: preflight(installs, invalid)
        except AssertionError: check('preflight_rejects_'+what+'_collision', True)
        else: raise AssertionError('Colisión no detectada')
    for i, config in zip(installs, configs):
        safe = copy.deepcopy(config); safe['services']['postgres']['environment']['POSTGRES_PASSWORD'] = '<redacted>'
        save(i['path']/'compose.sanitized.json', safe)
        compose(i, 'up', '-d', '--no-build', '--pull', 'never', '--wait', 'postgres')
        compose(i, 'run', '--rm', '--no-deps', '--pull', 'never', '-T', 'php', 'php', 'artisan', 'h2:setup')
        probe(i, 'seed', **i['account'])
        compose(i, 'up', '-d', '--no-build', '--pull', 'never', 'web')
        ready(i)
    check('both_installations_started', True)
    for key in ['APP_KEY','DB_PASSWORD','H2_OPERATOR_PASSWORD','SESSION_COOKIE']:
        check('independent_'+key.lower(), installs[0]['values'][key] != installs[1]['values'][key])
    resources = []
    def mount_path(value):
        value = value.replace('\\','/').lower()
        for prefix in ['/run/desktop/mnt/host/', '/host_mnt/']:
            if value.startswith(prefix): value = value[len(prefix):]
        return value.replace(':/','/').rstrip('/')
    for n,i in enumerate(installs):
        ids = compose(i, 'ps', '-q', 'postgres', 'web').split()
        inspected = json.loads(run(['docker','inspect',*ids])); assert len(inspected) == 2
        networks = set(); mounts = []
        for c in inspected:
            service = c['Config']['Labels']['com.docker.compose.service']
            assert c['Image'] == images['postgres' if service == 'postgres' else 'php']
            assert set(c['NetworkSettings']['Networks']) == {i['project']+'_'+k for k in (['local','browser'] if service == 'web' else ['local'])}
            networks |= set(c['NetworkSettings']['Networks'])
            mounts.extend({k:m.get(k) for k in ['Type','Name','Source','Destination','RW']} for m in c['Mounts'])
            if service == 'postgres':
                i['db_ip'] = c['NetworkSettings']['Networks'][i['project']+'_local']['IPAddress']
                assert c['Mounts'][0]['Name'] == i['project']+'_postgres_data'
            else:
                expected = {'/workspace':(i['path'],False), '/workspace/storage':(i['path']/'storage',True),
                            '/workspace/bootstrap/cache':(i['path']/'bootstrap/cache',True), '/workspace/vendor':(ROOT/'vendor',False)}
                assert len(c['Mounts']) == 4
                assert all(m['Type']=='bind' and (mount_path(m['Source']),m['RW']) == (mount_path(str(expected[m['Destination']][0])),expected[m['Destination']][1]) for m in c['Mounts'])
        assert networks == {i['project']+'_local',i['project']+'_browser'}
        network_details = json.loads(run(['docker','network','inspect',*sorted(networks)]))
        for net in network_details:
            k = 0 if net['Name'].endswith('_local') else 1
            assert net['Internal'] == (k == 0) and net['IPAM']['Config'][0]['Subnet'] == args.cidrs[n*2+k]
        resources.append({'project':i['project'], 'directory':str(i['path']), 'url':i['base'], 'networks':sorted(networks), 'mounts':mounts,
                          'images':images, 'container_ids':ids})
    check('actual_resources_match_separate_projects', True)
    check('actual_bind_sources_do_not_overlap_except_readonly_vendor', not
          ({m['Source'] for m in resources[0]['mounts'] if m['Type']=='bind' and m['Destination']!='/workspace/vendor'} &
           {m['Source'] for m in resources[1]['mounts'] if m['Type']=='bind' and m['Destination']!='/workspace/vendor'}))
    jobs = [worker_start({'mode':'create', 'base':i['base'], **i['account'], 'peer_password':installs[1-n]['account']['password'],
                          'tag':i['tag'], 'price':str(10+n)}) for n,i in enumerate(installs)]
    docs = [worker_end(job) for job in jobs]
    check('independent_http_processes_create_distinct_documents', docs[0]['pid'] != docs[1]['pid'] and docs[0]['id'] != docs[1]['id'])
    check('both_confirmations_idempotent', all(d['idempotent'] for d in docs))
    snapshots = []
    for n, (i, d) in enumerate(zip(installs, docs)):
        peer = installs[1-n]; other = docs[1-n]
        result = worker_end(worker_start({'mode':'verify','base':i['base'], **d, 'foreign_id':other['id']}))
        check(i['tag']+'_own_session_and_evidence', result['authenticated'] and result['xml_preserved'])
        check(i['tag']+'_foreign_document_and_static_files_denied', result['foreign_denied'])
        for renamed in [False, True]:
            cookies = other['cookies'] if not renamed else {i['values']['SESSION_COOKIE']:other['cookies'][peer['values']['SESSION_COOKIE']]}
            check(i['tag']+'_peer_cookie_denied_'+str(renamed), worker_end(worker_start({'mode':'cookie','base':i['base'],'cookies':cookies}))['denied'])
        bounds = probe(i, 'boundaries', peer_password=peer['values']['DB_PASSWORD'], peer_ip=peer['db_ip'])
        check(i['tag']+'_peer_database_password_rejected', bounds['peer_password_rejected'])
        check(i['tag']+'_peer_database_network_blocked', bounds['peer_database_unreachable'])
        state = probe(i, 'snapshot'); save(i['path']/'state-before.json', state); snapshots.append(state)
        check(i['tag']+'_accounts_and_document_only_local', len(state['invoices']) == 1 and state['invoices'][0]['id'] == d['id']
              and i['account']['email'] in [u['email'] for u in state['users']] and peer['account']['email'] not in [u['email'] for u in state['users']])
    a,b = installs
    compose(a, 'stop')
    check('A_stopped_without_removing_volume', not compose(a, 'ps', '-q').strip() and bool(run(['docker','volume','ls','-q','--filter','name='+a['project']+'_postgres_data']).strip()))
    alive = worker_end(worker_start({'mode':'verify','base':b['base'], **docs[1]}))
    check('B_session_and_xml_work_while_A_stopped', alive['authenticated'] and alive['xml_preserved'])
    check('B_complete_data_unchanged_while_A_stopped', probe(b, 'snapshot') == snapshots[1])
    compose(a, 'up', '-d', '--no-build', '--pull', 'never', '--wait', 'postgres', 'web')
    ready(a)
    for n,i in enumerate(installs):
        result = worker_end(worker_start({'mode':'verify','base':i['base'], **docs[n]}))
        check(i['tag']+'_session_and_xml_after_restart_A', result['authenticated'] and result['xml_preserved'])
        current = probe(i, 'snapshot'); save(i['path']/'state-after.json', current)
        check(i['tag']+'_all_rows_sequences_and_evidence_preserved', current == snapshots[n])
    check('original_files_and_links_preserved', source_files(images['php'], original)['preserved'])
    check('original_environment_preserved', (fingerprint(ROOT/'.env') if (ROOT/'.env').exists() else None) == original_env)
    check('original_containers_untouched', original_containers() == source_containers)
    check('shared_image_tags_not_rebuilt', all(json.loads(run(['docker','image','inspect','emisor-h1-'+name+':local']))[0]['Id'] == image for name,image in images.items()))
    if not args.keep_running:
        for i in installs: compose(i, 'stop')
    result = {'scope':'H7-3 INSTALACIONES A/B FICTICIAS LOCALES SIN SUNAT', 'checks':checks, 'resources':resources,
              'documents':[{k:v for k,v in d.items() if k!='cookies'} for d in docs], 'cidrs':args.cidrs,
              'protected_source_files':len(original), 'kept_running':args.keep_running,
              'limitations':['mismo host y administrador Docker', 'sin HTTPS/exposición externa', 'sin aislamiento ante pérdida del host',
                             'vendor compartido solo lectura e imágenes comunes fijadas por ID; sin independencia de actualizaciones demostrada']}
    save(target/'result.json', result); print(json.dumps(result))


if __name__ == '__main__':
    p = argparse.ArgumentParser(); p.add_argument('--root', type=Path); p.add_argument('--keep-running', action='store_true')
    p.add_argument('--ports', type=int, nargs=2, default=[8193,8194])
    p.add_argument('--cidrs', nargs=4, default=['10.254.73.0/28','10.254.73.16/28','10.254.73.32/28','10.254.73.48/28'])
    p.add_argument('--http-worker', action='store_true'); args = p.parse_args()
    if args.http_worker:
        print(json.dumps(worker(json.load(sys.stdin))))
    else:
        assert args.root is not None, 'Indicar raíz nueva externa'
        exercise(args)
