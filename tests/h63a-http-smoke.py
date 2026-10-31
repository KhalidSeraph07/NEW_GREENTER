"""Bajas ficticias desde HTTP loopback; reutiliza la regresión existente sin SUNAT."""
import argparse
import hashlib
import io
import json
import runpy
import sys
import time
import urllib.parse
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
    sys.argv=[str(ROOT/'tests/h62b-notes-http-smoke.py'),'--output-dir',str(args.output_dir/'legacy')]
    legacy=runpy.run_path(str(ROOT/'tests/h62b-notes-http-smoke.py'));previous=143
request=legacy['request'];checks={}

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

def check(name,ok):
    assert ok,name
    checks[name]=True

def path(url):return urllib.parse.urlsplit(url).path

def original(kind):
    if kind=='invoice':
        status,body,_=request('/invoices/create');f=Forms(body).forms['/invoices/review']|{'description':'ORIGINAL FICTICIO HTTP H63A F','quantity':'1','unit_price':'100.00'}
        assert request('/invoices/review',f)[0]==200
        status,body,url=request('/invoices',f);assert status==200
        assert request(path(url)+'/simulate',{'_token':f['_token'],'scenario':'accepted'})[0]==200
    else:
        status,body,_=request('/boletas');f=Forms(body).forms['/boletas']|{'description':'ORIGINAL FICTICIO HTTP H63A B','quantity':'1','unit_price':'100.00'}
        status,body,url=request('/boletas',f);assert status==200
        rc={'_token':f['_token'],'idempotency_key':str(uuid.uuid4()),'members':[url.rsplit('/',1)[1]]}
        assert request('/summaries/review',rc,json_body=True)[0]==200
        status,body,summary=request('/summaries',rc,json_body=True);assert status==200
        assert request(path(summary)+'/simulate',{'_token':f['_token'],'action':'send'})[0]==200
        assert request(path(summary)+'/simulate',{'_token':f['_token'],'action':'poll','scenario':'0'})[0]==200
    return kind+':'+url.rsplit('/',1)[1]

def fact(token):
    status,body,_=request('/voids');assert status==200
    return Forms(body).forms['/voids/grants']|{'document':token,'state':'not_granted','provenance':'DECLARACION EXPLICITA FICTICIA HTTP H63A','confirmed':'1'}

def selection(token,csrf):return {'_token':csrf,'idempotency_key':str(uuid.uuid4()),'members':[token],'reason':'BAJA FICTICIA NO OTORGADO H63A HTTP'}

def exact_zip(url):
    status,xml,_=request(url+'/evidence/xml');assert status==200
    status,data,_=request(url+'/evidence/zip')
    with zipfile.ZipFile(io.BytesIO(data)) as z:assert status==200 and z.read(z.namelist()[0])==xml
    return hashlib.sha256(xml).hexdigest()

result_documents={}
for kind,route in [('invoice','RA'),('boleta','RC3')]:
    token=original(kind);g=fact(token);csrf=g['_token'];f=selection(token,csrf)
    check(route+'_unknown_grant_blocks_review',request('/voids/review',f,json_body=True)[0]==409)
    check(route+'_grant_csrf_required',request('/voids/grants',{k:v for k,v in g.items() if k!='_token'},json_body=True)[0]==419)
    status,body,_=request('/voids/grants',g|{'provenance':'corta'})
    check(route+'_grant_validation_preserves_key_and_document',status==200 and Forms(body).forms['/voids/grants']['idempotency_key']==g['idempotency_key'] and token.encode() in body)
    check(route+'_explicit_grant_replay',request('/voids/grants',g)[0]==200 and request('/voids/grants',g)[0]==200)
    check(route+'_freeze_requires_review',request('/voids',f,json_body=True)[0]==409)
    status,body,_=request('/voids/review',f,json_body=True)
    check(route+'_review_explicit_members_dates_and_reason',status==200 and token.rsplit(':',1)[1].encode() in body and 'no reserva numeración' in body.decode() and b'118.00' in body)
    status,body,_=request('/voids/edit-review',f,json_body=True)
    check(route+'_edit_preserves_selection_key_reason',status==200 and f['idempotency_key'].encode() in body and b'checked' in body and f['reason'].encode() in body)
    status,body,url=request('/voids',f,json_body=True);vpath=path(url)
    check(route+'_double_confirmation_same_identity',status==200 and request('/voids',f,json_body=True)[2]==url)
    digest=exact_zip(vpath)
    check(route+'_private_signed_xml_exact_zip',bool(digest) and b'H6-3A' in body)
    check(route+'_sensitive_actions_csrf',all(request(u,{},json_body=True)[0]==419 for u in ['/voids/review','/voids',vpath+'/simulate']))
    status,body,_=request(vpath+'/simulate',{'_token':csrf,'action':'send'})
    check(route+'_ticket_is_not_acceptance',status==200 and b'SIM-H5C1-' in body and 'Sin efecto de baja confirmado' in body.decode())
    if kind=='boleta':
        status,body,_=request(vpath+'/simulate',{'_token':csrf,'action':'poll','scenario':'98'});assert status==200
        time.sleep(5.2)
    status,body,_=request(vpath+'/simulate',{'_token':csrf,'action':'poll','scenario':'timeout'})
    check(route+'_uncertain_resend_denied',status==200 and request(vpath+'/simulate',{'_token':csrf,'action':'send'},json_body=True)[0]==409)
    assert request(vpath+'/simulate',{'_token':csrf,'action':'recover'})[0]==200
    status,body,_=request(vpath+'/simulate',{'_token':csrf,'action':'poll','scenario':'0'})
    check(route+'_manual_recovery_history_and_fake_effect',status==200 and 'Baja confirmada únicamente en simulación' in body.decode() and b'manual_query_rearmed' in body and b'result_saved' in body)
    check(route+'_evidence_preserved',exact_zip(vpath)==digest)
    check(route+'_terminal_actions_denied',all(request(vpath+'/simulate',{'_token':csrf,'action':a},json_body=True)[0]==409 for a in ['send','poll','recover']))
    result_documents[route]={'original':token,'communication':url,'xml_sha256':digest}

token=original('invoice');g=fact(token);assert request('/voids/grants',g)[0]==200;f=selection(token,g['_token']);assert request('/voids/review',f,json_body=True)[0]==200
status,body,url=request('/voids',f,json_body=True);assert status==200
status,body,_=request(path(url)+'/simulate',{'_token':g['_token'],'action':'send','scenario':'lost_ticket'})
check('lost_ticket_has_no_uuid_recovery_or_resend',status==200 and all(request(path(url)+'/simulate',{'_token':g['_token'],'action':a},json_body=True)[0]==409 for a in ['send','poll','recover']) and b'Sin ticket conservado' in body)
result={'scope':'H6-3A BAJAS FICTICIAS SIN SUNAT','legacy_checks':previous,'checks':checks,'documents':result_documents,'lost_ticket_communication':url}
(args.output_dir/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
