"""Cuentas y permisos ficticios por HTTP loopback; no comunica con SUNAT ni imprime contraseñas."""
import argparse
import hashlib
from http.cookiejar import CookieJar
import io
import json
import runpy
import secrets
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid
import zipfile
from html.parser import HTMLParser
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--output-dir',type=Path,required=True);p.add_argument('--focused',action='store_true');args=p.parse_args()
args.output_dir.mkdir(parents=True,exist_ok=False)
if args.focused:
    sys.argv=[str(ROOT/'tests/http-smoke.py'),'--report',str(args.output_dir/'base.json')]
    legacy=runpy.run_path(str(ROOT/'tests/http-smoke.py'));previous=13
else:
    sys.argv=[str(ROOT/'tests/h63a-http-smoke.py'),'--output-dir',str(args.output_dir/'legacy')]
    legacy=runpy.run_path(str(ROOT/'tests/h63a-http-smoke.py'));previous=174
request=legacy['request'];BASE=request.__globals__['BASE'];checks={}

class Forms(HTMLParser):
    def __init__(self,body):
        super().__init__();self.forms={};self.current=None;self.feed(body.decode())
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='form':
            self.current=urllib.parse.urlsplit(a.get('action','')).path if a.get('method','get').lower()=='post' else None
            if self.current is not None:self.forms[self.current]={}
        elif self.current is not None and tag=='input' and a.get('name'):
            if a.get('type')!='checkbox' or 'checked' in a:self.forms[self.current][a['name']]=a.get('value','')
    def handle_endtag(self,tag):
        if tag=='form':self.current=None

def check(name,condition):
    assert condition,name
    checks[name]=True

def client():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))
def http(c,path,data=None,json_body=False):
    headers={'Accept':'application/json'} if json_body else {}
    if json_body:headers['Content-Type']='application/json'
    payload=(json.dumps(data) if json_body else urllib.parse.urlencode(data)).encode() if data is not None else None
    try:r=c.open(urllib.request.Request(BASE+path,payload,headers),timeout=30)
    except urllib.error.HTTPError as e:return e.code,e.read(),e.geturl()
    return r.status,r.read(),r.geturl()
def login(c,account):
    status,body,_=http(c,'/login');assert status==200
    return http(c,'/login',Forms(body).forms['/login']|{'email':account['email'],'password':account['password']})

# Solo una cuenta ADMIN nueva de ensayo; nunca promover ni sobrescribir cuentas existentes.
admin={'email':str(uuid.uuid4())+'@example.invalid','name':'ADMIN ensayo HTTP H64','password':'H64-Aa-'+secrets.token_hex(20)}
php=r'''require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!app()->environment('local') || config('h2.mode')!=='fictitious' || config('database.connections.pgsql.database')!=='emisor_h1' || config('database.connections.pgsql.host')!=='postgres') { exit(1); }
$v=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);$v['role']='ADMIN';$v['password_confirmation']=$v['password'];
$u=app(App\Accounts\LocalAccounts::class)->create($v);echo json_encode(['id'=>$u->id]);'''
seed=subprocess.run(['docker','compose','run','--rm','-T','php','php','-r',php],input=json.dumps(admin),capture_output=True,text=True,cwd=ROOT)
assert seed.returncode==0,'No se pudo crear la cuenta nueva de ensayo; revisar entorno privado.'
admin['id']=json.loads(seed.stdout)['id'];cadmin=client();status,body,url=login(cadmin,admin)
check('admin_login_and_existing_area',status==200 and urllib.parse.urlsplit(url).path=='/')
accounts={'admin':admin}
for name in ['A','B']:
    account={'email':str(uuid.uuid4())+'@example.invalid','name':'Receptor '+name+' HTTP H64','password':'H64-Aa-'+secrets.token_hex(20)}
    status,body,_=http(cadmin,'/accounts');assert status==200
    f=Forms(body).forms['/accounts']|account|{'role':'RECEPTOR','password_confirmation':account['password']}
    check('admin_creates_receiver_'+name,http(cadmin,'/accounts',f)[0]==200)
    # La pantalla contiene el id en la acción de desactivación de esa cuenta, sin consultar PostgreSQL.
    _,body,_=http(cadmin,'/accounts');parser=Forms(body);needle=account['email'].encode()
    row=body.split(needle,1)[1].split(b'</tr>',1)[0]
    import re
    account['id']=int(re.search(rb'/accounts/(\d+)/deactivate',row)[1]);accounts[name]=account
(args.output_dir/'accounts.private.json').write_text(json.dumps(accounts),encoding='utf-8')

_,body,_=request('/invoices/create');f=Forms(body).forms['/invoices/review']|{'description':'COMPROBANTE NUEVO RECEPTOR HTTP H64','quantity':'2','unit_price':'12.50'}
assert request('/invoices/review',f)[0]==200
status,body,docurl=request('/invoices',f);docid=docurl.rsplit('/',1)[1];assert status==200
_,body,_=request('/recipient-access');grantform=Forms(body).forms['/recipient-access']|{'document':'invoice:'+docid,'recipient_id':str(accounts['A']['id']),'confirmed':'1'}
check('grant_requires_csrf',request('/recipient-access',{k:v for k,v in grantform.items() if k!='_token'},json_body=True)[0]==419)
check('grant_requires_explicit_confirmation',request('/recipient-access',{k:v for k,v in grantform.items() if k!='confirmed'},json_body=True)[0]==422)
status,body,_=request('/recipient-access',grantform);assert status==200
revoke=next(path for path in Forms(body).forms if path.startswith('/recipient-access/') and path.endswith('/revoke'));accessid=revoke.split('/')[2];private='/recipient/'+accessid
status,replay,_=request('/recipient-access',grantform)
check('owner_grant_is_idempotent',status==200 and set(Forms(replay).forms)==set(Forms(body).forms))
ca=client();cb=client();status,body,url=login(ca,accounts['A'])
check('receiver_redirect_and_assigned_list',status==200 and urllib.parse.urlsplit(url).path=='/recipient' and accessid.encode() in body)
check('receiver_has_no_operator_navigation',all(x not in body for x in [b'/accounts',b'/invoices/create',b'/voids',b'/summaries']))
status,body,_=http(ca,private)
check('detail_uses_conserved_data_and_fake_marker',status==200 and b'COMPROBANTE NUEVO RECEPTOR HTTP H64' in body and b'29.50' in body and b'SIN VALIDEZ FISCAL' in body)
status,body,_=http(ca,private+'/print')
check('html_print_is_not_fiscal_pdf_or_cdr',status==200 and b'Ctrl+P' in body and b'29.50' in body and b'@media print' in body and 'no es una representación impresa fiscalmente validada' in body.decode())
status,xml,_=http(ca,private+'/evidence/xml');assert status==200
status,data,_=http(ca,private+'/evidence/zip')
with zipfile.ZipFile(io.BytesIO(data)) as z:check('xml_and_zip_are_exact_private_evidence',status==200 and z.read(z.namelist()[0])==xml)
check('receiver_cannot_download_other_artifacts',all(http(ca,private+'/evidence/'+file)[0]==404 for file in ['certificate','unsigned','cdr','pdf']))
check('receiver_operator_and_admin_routes_forbidden',all(http(ca,path,json_body=True)[0]==403 for path in ['/','/invoices/create','/boletas','/invoice-notes','/receipt-notes','/voids','/accounts','/settings','/recipient-access']))
check('receiver_write_routes_forbidden',all(http(ca,path,{'_token':Forms(body).forms['/logout']['_token']},json_body=True)[0]==403 for path in ['/invoices','/boletas','/invoice-notes','/receipt-notes','/summaries','/voids','/recipient-access','/accounts']))
status,body,url=login(cb,accounts['B'])
check('same_synthetic_fiscal_identity_does_not_assign_B',status==200 and accessid.encode() not in body)
check('copied_and_modified_urls_denied',all(http(cb,path)[0]==404 for path in [private,private+'/print',private+'/evidence/xml',private+'/evidence/zip','/recipient/'+docid,'/recipient/'+str(uuid.uuid4())]))
check('recipient_csrf_required',http(ca,'/logout',{},json_body=True)[0]==419)
_,body,_=request('/recipient-access');rf=Forms(body).forms[revoke]
check('owner_revokes',request(revoke,rf)[0]==200)
check('revocation_blocks_existing_session',all(http(ca,private+suffix)[0]==404 for suffix in ['','/print','/evidence/xml','/evidence/zip']))
check('revocation_removes_list_entry',accessid.encode() not in http(ca,'/recipient')[1])
assert request('/recipient-access',grantform)[0]==200
_,body,_=http(cadmin,'/accounts');deactivate='/accounts/'+str(accounts['A']['id'])+'/deactivate'
check('admin_deactivates_receiver',http(cadmin,deactivate,Forms(body).forms[deactivate])[0]==200)
check('deactivation_blocks_existing_session',http(ca,'/recipient',json_body=True)[0]==401)
check('deactivated_login_denied',urllib.parse.urlsplit(login(ca,accounts['A'])[2]).path=='/login')
for name,c in [('B',cb),('ADMIN',cadmin)]:
    area='/recipient' if name=='B' else '/accounts';_,body,_=http(c,area)
    check('logout_'+name,urllib.parse.urlsplit(http(c,'/logout',Forms(body).forms['/logout'])[2]).path=='/login')
result={'scope':'H6-4 RECEPTOR FICTICIO LOCAL SIN SUNAT','legacy_checks':previous,'checks':checks,'document':docurl,'revoked_access':accessid,'xml_sha256':hashlib.sha256(xml).hexdigest(),'account_ids':{key:v['id'] for key,v in accounts.items()}}
(args.output_dir/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
