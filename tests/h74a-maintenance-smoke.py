"""Ensayo H7-4A: solo diagnóstico existente y stop/start supervisado de una copia nueva."""
import argparse
import base64
import copy
import hashlib
import importlib.util
import ipaddress
import json
import os
from pathlib import Path
import secrets
import shutil
import socket
import subprocess
import time
import urllib.request
from http.cookiejar import CookieJar
from datetime import datetime, timezone

ROOT=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('h73',ROOT/'tests/h73-isolation-smoke.py')
base=importlib.util.module_from_spec(spec);spec.loader.exec_module(base)

SNAPSHOT_SQL="""BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY;
SET LOCAL TIME ZONE 'UTC'; SET LOCAL statement_timeout='20s';
SELECT jsonb_build_object('read_only',current_setting('transaction_read_only'),
 'tables',(SELECT jsonb_object_agg(tablename,encode(sha256(convert_to(query_to_xml(
 format('SELECT * FROM public.%I AS t ORDER BY to_jsonb(t)::text',tablename),true,false,'')::text,'UTF8')),'hex'))
 FROM pg_tables WHERE schemaname='public'),
 'sequences',(SELECT jsonb_agg(to_jsonb(s) ORDER BY sequencename) FROM pg_sequences s WHERE schemaname='public'));
COMMIT;"""
DIAGNOSIS_SQL="""BEGIN READ ONLY;
SELECT jsonb_build_object('outboxes',(SELECT jsonb_agg(to_jsonb(x) ORDER BY family,status) FROM (
 SELECT 'invoice' family,status,count(*) total FROM invoice_outbox GROUP BY status
 UNION ALL SELECT 'boleta_rc',status,count(*) FROM h4_outbox GROUP BY status
 UNION ALL SELECT 'invoice_note',status,count(*) FROM h5_outbox GROUP BY status
 UNION ALL SELECT 'note_rc',status,count(*) FROM h5b_outbox GROUP BY status
 UNION ALL SELECT 'void',status,count(*) FROM h5c1_outbox GROUP BY status) x),
 'invoices',(SELECT jsonb_agg(jsonb_build_object('id',id,'state',transport_status) ORDER BY id) FROM invoices),
 'note_rc',(SELECT jsonb_agg(jsonb_build_object('id',id,'state',state,'has_ticket',ticket IS NOT NULL) ORDER BY id) FROM h5b_summaries),
 'voids',(SELECT jsonb_agg(jsonb_build_object('id',id,'state',state,'has_ticket',ticket IS NOT NULL) ORDER BY id) FROM h5c1_batches),
 'member_reservations',jsonb_build_object('boleta_rc',(SELECT count(*) FROM h4_members),
 'note_rc',(SELECT count(*) FROM h5b_members),'void',(SELECT count(*) FROM h5c1_members)));
COMMIT;"""


def exercise(args):
    root=args.root.resolve();assert not root.is_relative_to(ROOT) and not ROOT.is_relative_to(root)
    assert args.responsible.startswith('ENSAYO-') and 7<=len(args.responsible)<=80
    assert 3<=len(args.reason)<=160 and not any(ord(c)<32 for c in args.reason)
    nets=[ipaddress.ip_network(c) for c in args.cidrs]
    assert all(c.is_private and c.version==4 and c.prefixlen==28 for c in nets) and not nets[0].overlaps(nets[1])
    assert 1024<=args.port<=65535
    with socket.socket() as s:s.bind(('127.0.0.1',args.port))
    base.private_root(root);base.DIAGNOSTICS=root;checks={};audit=root/'audit';audit.mkdir()
    def save(name,value):base.save(audit/(name+'.json'),value)
    def check(name,value):
        assert value,name
        checks[name]=True
    def stamp():return datetime.now(timezone.utc).isoformat()
    path=root/'installation';path.mkdir();project='emisor-h74a-'+secrets.token_hex(5)
    for rel in filter(None,base.run(['git','ls-files','-z']).split('\0')):
        if rel.startswith(('storage/','bootstrap/cache/')):continue
        src=ROOT/rel;assert src.is_file() and not src.is_symlink()
        dest=path/rel;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(src,dest)
    shutil.copyfile(ROOT/'tests/h74a-fixture.php',path/'tests/h74a-fixture.php')
    for d in ['storage/app','storage/framework','bootstrap/cache','vendor']:(path/d).mkdir(parents=True,exist_ok=True)
    values=dict(line.split('=',1) for line in (ROOT/'.env.example').read_text(encoding='utf-8').splitlines() if '=' in line and not line.startswith('#'))
    values|={'APP_NAME':'EmisorH74AFicticio','COMPOSE_PROJECT_NAME':project,'SESSION_COOKIE':project.replace('-','_'),
             'APP_KEY':'base64:'+base64.b64encode(secrets.token_bytes(32)).decode(),'DB_PASSWORD':secrets.token_hex(32),
             'H2_OPERATOR_PASSWORD':'H74A-Aa-'+secrets.token_hex(24),'H2_HOST_PORT':str(args.port),
             'LOCAL_UID':str(os.getuid() if hasattr(os,'getuid') else 1000),'LOCAL_GID':str(os.getgid() if hasattr(os,'getgid') else 1000)}
    with (path/'.env').open('x',encoding='utf-8') as f:f.write('\n'.join(k+'='+v for k,v in values.items())+'\n')
    (path/'.env').chmod(0o600)
    env={k:v for k,v in os.environ.items() if k not in values and not k.startswith('COMPOSE_')};env['COMPOSE_PROJECT_NAME']=project
    images={s:json.loads(base.run(['docker','image','inspect','emisor-h1-'+s+':local']))[0]['Id'] for s in ['php','postgres']}
    override={'services':{s:{'image':images['postgres' if s=='postgres' else 'php']} for s in ['php','web','postgres']},
              'networks':{k:{'ipam':{'config':[{'subnet':str(nets[n])}]}} for n,k in enumerate(['local','browser'])}}
    for s in ['php','web']:override['services'][s]['volumes']=[{'type':'bind','source':str(ROOT/'vendor'),'target':'/workspace/vendor','read_only':True}]
    base.save(path/'maintenance-compose.json',override);base.save(path/'.h74a-fixture.json',{'project':project})
    def dc(*cmd,data=None):
        return base.run(['docker','compose','--env-file',str(path/'.env'),'-f','compose.yaml','-f','maintenance-compose.json',*cmd],cwd=path,env=env,data=data)
    def sql(query):
        return dc('exec','-T','postgres','psql','-X','-qAt','-v','ON_ERROR_STOP=1','-U','emisor_h1','-d','emisor_h1',data=query)
    def snapshot():
        db=json.loads(sql(SNAPSHOT_SQL));assert db['read_only']=='on'
        files={f.relative_to(path/'storage').as_posix():base.fingerprint(f) for f in (path/'storage/app').rglob('*') if f.is_file()}
        return {'db':db,'files':files}
    def drained():
        for _ in range(15):
            running=base.run(['docker','ps','-q','--filter','label=com.docker.compose.project='+project]).split()
            actual=json.loads(base.run(['docker','inspect',*running])) if running else []
            assert all(c['Config']['Labels']['com.docker.compose.service'] in ['web','postgres'] for c in actual), 'CLI activo: examen supervisado'
            count=int(sql("BEGIN READ ONLY; SELECT count(*) FROM pg_stat_activity WHERE datname='emisor_h1' AND backend_type='client backend' AND pid<>pg_backend_pid(); COMMIT;"))
            if count==0:return
            time.sleep(1)
        raise RuntimeError('Conexiones sin drenar; no terminar procesos ajenos a ciegas.')
    cfg=json.loads(dc('config','--format','json'))
    base.preflight([{'path':path,'project':project,'port':args.port,'images':images}],[cfg])
    assert not base.run(['docker','ps','-aq','--filter','label=com.docker.compose.project='+project]).strip()
    assert not base.run(['docker','volume','ls','-q','--filter','name='+project+'_postgres_data']).strip()
    check('fresh_project_effective_compose_and_mounts_checked',True)
    sanitized=copy.deepcopy(cfg);sanitized['services']['postgres']['environment']['POSTGRES_PASSWORD']='<redacted>';save('compose-sanitized',sanitized)
    dc('up','-d','--no-build','--pull','never','--wait','postgres')
    dc('run','--rm','--no-deps','--pull','never','-T','php','php','artisan','h2:setup')
    credentials={k:'H74A-Aa-'+secrets.token_hex(24) for k in ['other','admin','receiver']};base.save(root/'accounts.private.json',credentials)
    fixture=json.loads(dc('run','--rm','--no-deps','--pull','never','-T','php','php','tests/h74a-fixture.php',data=json.dumps(credentials)));save('fixture',fixture)
    dc('up','-d','--no-build','--pull','never','web');url='http://127.0.0.1:'+str(args.port);base.ready({'base':url})
    sessions={};started=time.monotonic();cases=fixture['cases']
    for key,account in {'owner':fixture['owner'],**fixture['accounts']}.items():
        client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()));sessions[key]=client
        _,body,_=base.http(client,url,'/login');password=values['H2_OPERATOR_PASSWORD'] if key=='owner' else credentials[key]
        status,_,dest=base.http(client,url,'/login',{'_token':base.Inputs(body).values['_token'],'email':account['email'],'password':password})
        check(key+'_login',status==200 and dest==('/recipient' if key=='receiver' else '/'))
    def accesses(label):
        def get(key,p):return base.http(sessions[key],url,p,headers={'Accept':'application/json'})[0]
        check(label+'_owner_reads_all_existing_families',all(get('owner',p)==200 for p in ['/','/boletas','/invoice-notes','/receipt-notes','/voids','/invoices/'+cases['invoice_uncertain']]))
        check(label+'_horizontal_access_denied_even_ADMIN',all(get(k,'/invoices/'+cases['invoice_pending'])==404 for k in ['other','admin']))
        check(label+'_roles_and_recipient_grant_preserved',get('owner','/accounts')==403 and get('admin','/accounts')==200
              and get('receiver','/recipient/'+fixture['recipient_access'])==200 and get('receiver','/')==403)
    accesses('before');drained();before=snapshot();save('before',before)
    diagnosis_before=json.loads(sql(DIAGNOSIS_SQL));save('diagnosis-before',diagnosis_before)
    states={i['id']:i['state'] for i in diagnosis_before['invoices']}
    check('pending_uncertain_and_terminal_are_distinct',all(states[cases['invoice_'+k]]==v for k,v in {
        'pending':'pending_simulation','uncertain':'simulation_uncertain','accepted':'simulated_accepted','rejected':'simulated_rejected'}.items()))
    ra=next(r for r in diagnosis_before['voids'] if r['id']==cases['ra_uncertain_without_ticket'])
    check('lost_ticket_is_not_success',ra['state']=='uncertain' and ra['has_ticket'] is False)
    inspect={}
    commands=[['h3:inspect','--evidence'],['h4:process','inspect'],['h5:process','inspect'],['h5b:process','inspect'],['h5c1:process','inspect'],['h5c2:process','inspect']]
    for cmd in commands:
        r=subprocess.run(['docker','compose','--env-file',str(path/'.env'),'-f','compose.yaml','-f','maintenance-compose.json','run','--rm','--no-deps','--pull','never','-T','php','php','artisan',*cmd],cwd=path,env=env,capture_output=True,text=True,encoding='utf-8')
        assert r.returncode in [0,1], 'Error técnico en inspect'
        report=json.loads(r.stdout);assert report['simulated'] is True
        assert not report.get('evidence',{}).get('issues')
        assert not any(x['issue'] in ['evidence_or_source_changed','evidence_or_original_mismatch','evidence_missing_or_changed','orphan_candidate','orphan_or_unexpected','causal_evidence_changed'] for x in report.get('issues',[]))
        inspect[cmd[0]]={'exit':r.returncode,'report':report}
    save('inspect-before',inspect)
    check('existing_inspect_reports_attention_without_mutating_operations',inspect['h3:inspect']['exit']==1 and inspect['h3:inspect']['report']['attention_count']==1 and snapshot()==before)
    record={'project':project,'responsible_declared':args.responsible,'reason':args.reason,'started_at':stamp(),
            'scope':'ENSAYO FICTICIO; PARADA Y REINICIO HTTP SIN DESPACHAR TRANSPORTE','before_sha256':hashlib.sha256(json.dumps(before,sort_keys=True).encode()).hexdigest()}
    save('intervention-started',record)
    dc('stop','--timeout','30','web');drained();check('web_stopped_and_clients_drained',not dc('ps','-q','web').strip())
    check('postgres_evidence_counters_audits_and_reservations_unchanged_before_stop',snapshot()==before)
    dc('stop','--timeout','30','postgres');check('project_stopped_without_removing_volume',not dc('ps','-q').strip()
        and bool(base.run(['docker','volume','ls','-q','--filter','name='+project+'_postgres_data']).strip()))
    save('stopped',{'project':project,'at':stamp(),'volume_preserved':project+'_postgres_data'})
    check('effective_configuration_not_changed',json.loads(dc('config','--format','json'))==cfg)
    dc('up','-d','--no-build','--pull','never','--wait','postgres')
    check('postgres_restarted_same_data',snapshot()==before)
    current=json.loads(sql(DIAGNOSIS_SQL));check('pending_states_and_absence_of_ticket_unchanged',current==diagnosis_before)
    save('restart-decision',{'at':stamp(),'responsible_declared':args.responsible,'decision':'REINICIAR SOLO HTTP; NO DESPACHAR NI RECUPERAR',
         'conditions':['datos/evidencias íntegros','sin procesos CLI ni conexiones sin drenar','incertidumbres conocidas conservadas',
                       'arranque web no consume outbox'],'unresolved_ra':cases['ra_uncertain_without_ticket']})
    dc('up','-d','--no-build','--pull','never','web');base.ready({'base':url});accesses('after')
    after=snapshot();save('after',after)
    check('all_public_tables_sequences_and_private_evidence_preserved',after==before)
    check('querying_did_not_dispatch_or_resolve_operations',json.loads(sql(DIAGNOSIS_SQL))==diagnosis_before)
    save('intervention-completed',record|{'finished_at':stamp(),'result':'MANTENIMIENTO TÉCNICO ENSAYADO; INCIDENCIAS FISCALES SIN RESOLVER'})
    result={'scope':'H7-4A MANTENIMIENTO SUPERVISADO FICTICIO LOCAL','checks':checks,'project':project,'installation':str(path),'url':url,
            'tables_checked':len(before['db']['tables']),'private_files_checked':len(before['files']),'session_interval_seconds':round(time.monotonic()-started,3),
            'inspect_exit_codes':{k:v['exit'] for k,v in inspect.items()},'diagnosis':current,'images':images,
            'limitations':['responsable declarado, no identidad acreditada','sesión vigente solo dentro de límites H6/H7-1',
                           'sin bloqueo operativo adicional: exige supervisión y ventana sin otros operadores',
                           'no CDR oficial ni conciliación tributaria; inciertos y reservas intactos','no se usa ni habilita destino H7-2']}
    base.save(root/'result.json',result);print(json.dumps(result))


if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('--root',type=Path,required=True);p.add_argument('--responsible',required=True);p.add_argument('--reason',required=True)
    p.add_argument('--port',type=int,default=8211);p.add_argument('--cidrs',nargs=2,default=['10.254.80.0/28','10.254.80.16/28']);exercise(p.parse_args())
