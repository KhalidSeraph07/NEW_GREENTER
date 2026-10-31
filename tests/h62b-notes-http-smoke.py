"""Recorridos de notas ficticias por loopback; CI conserva la regresión HTTP completa."""
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
    sys.argv=[str(ROOT/'tests/h62b-http-smoke.py'),'--output-dir',str(args.output_dir/'legacy')]
    legacy=runpy.run_path(str(ROOT/'tests/h62b-http-smoke.py'));previous=118
request,Inputs=legacy['request'],legacy['Inputs'];checks={}
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

def exact_zip(url):
    status,xml,_=request(url+'/evidence/xml');assert status==200
    status,data,_=request(url+'/evidence/zip')
    with zipfile.ZipFile(io.BytesIO(data)) as z:assert status==200 and z.read(z.namelist()[0])==xml
    return hashlib.sha256(xml).hexdigest()

status,body,_=request('/invoices/create');f=Forms(body).forms['/invoices/review'];csrf=f['_token']
f=f|{'description':'ORIGINAL FICTICIO HTTP H62B NOTAS A','quantity':'2','unit_price':'12.50'}
request('/invoices/review',f);status,body,original=request('/invoices',f);request(path(original)+'/simulate',{'_token':csrf,'scenario':'accepted'})
status,body,_=request('/invoice-notes?operation=ND02');form=Forms(body).forms['/invoice-notes']|{'original_id':original.rsplit('/',1)[1],'reason':'INCREMENTO FICTICIO H62B HTTP','increment_unit_price':'5.00','original_granted_test':'1'}
check('nd_only_increment_field',status==200 and b'name="increment_unit_price"' in body)
status,body,_=request('/invoice-notes',form|{'increment_unit_price':'0'})
check('validation_keeps_note_key_original_and_values',status==200 and Forms(body).forms['/invoice-notes']['idempotency_key']==form['idempotency_key'] and form['original_id'].encode() in body and b'INCREMENTO FICTICIO' in body)
check('note_csrf_required',request('/invoice-notes',{k:v for k,v in form.items() if k!='_token'})[0]==419)
status,body,note=request('/invoice-notes',form);npath=path(note)
check('nd_server_total_and_original_link',status==200 and b'11.80' in body and path(original).encode() in body)
check('note_double_submit_same_identity',request('/invoice-notes',form)[2]==note)
check('incompatible_key_reuse_denied',request('/invoice-notes',form|{'reason':'OTRO SUSTENTO FICTICIO'},json_body=True)[0]==409)
digest=exact_zip(npath)
status,body,_=request(npath+'/simulate',{'_token':csrf,'action':'dispatch','scenario':'timeout'})
check('invoice_note_uncertain_no_false_result',status==200 and b'<strong>uncertain</strong>' in body and b'Sin resultado definitivo confirmado' in body)
check('invoice_uncertain_resend_denied',request(npath+'/simulate',{'_token':csrf,'action':'dispatch'},json_body=True)[0]==409)
status,body,_=request(npath+'/simulate',{'_token':csrf,'action':'recover'})
check('invoice_manual_fake_recovery_history',status==200 and b'recovered_local_fake' in body and b'H5A_LOCAL_FAKE' in body and b'Sin CDR oficial' in body)
check('invoice_evidence_preserved',exact_zip(npath)==digest)
check('invoice_terminal_recovery_denied',request(npath+'/simulate',{'_token':csrf,'action':'recover'},json_body=True)[0]==409)

status,body,_=request('/boletas');b=Forms(body).forms['/boletas']|{'description':'ORIGINAL FICTICIO HTTP H62B NOTAS B','quantity':'1','unit_price':'100.00'}
status,body,boleta=request('/boletas',b);bid=boleta.rsplit('/',1)[1]
base_rc={'_token':csrf,'idempotency_key':str(uuid.uuid4()),'members':[bid]}
assert request('/summaries/review',base_rc,json_body=True)[0]==200
status,body,bsummary=request('/summaries',base_rc,json_body=True);bp=path(bsummary)
request(bp+'/simulate',{'_token':csrf,'action':'send'});request(bp+'/simulate',{'_token':csrf,'action':'poll','scenario':'0'})
status,body,_=request('/receipt-notes?operation=NC01');bf=Forms(body).forms['/receipt-notes']|{'original_id':bid,'reason':'ANULACION COMPLETA FICTICIA H62B HTTP','original_granted_test':'1'}
check('nc_has_no_editable_increment',status==200 and b'name="increment_unit_price"' not in body)
status,body,bnote=request('/receipt-notes',bf);bnpath=path(bnote);bnid=bnote.rsplit('/',1)[1]
check('boleta_note_prepared_with_original_link',status==200 and b'118.00' in body and path(boleta).encode() in body)
check('boleta_note_double_submit_same_identity',request('/receipt-notes',bf)[2]==bnote)
rc={'_token':csrf,'idempotency_key':str(uuid.uuid4()),'members':[bnid]}
check('rc_freeze_requires_review',request('/note-summaries',rc,json_body=True)[0]==409)
check('review_csrf_required',request('/note-summaries/review',{k:v for k,v in rc.items() if k!='_token'},json_body=True)[0]==419)
status,body,_=request('/note-summaries/review',rc,json_body=True)
check('rc_review_has_note_and_03_reference_without_reservation',status==200 and b'03 B000-' in body and 'no reserva numeración' in body.decode() and b'118.00' in body)
status,body,_=request('/note-summaries/edit-review',rc,json_body=True)
check('edit_keeps_members_and_key',status==200 and rc['idempotency_key'].encode() in body and bnid.encode() in body and b'checked' in body)
status,body,summary=request('/note-summaries',rc,json_body=True);sp=path(summary);rid=summary.rsplit('/',1)[1];action='/note-summaries/'+rid+'/simulate'
check('rc_confirmation_is_idempotent',status==200 and request('/note-summaries',rc,json_body=True)[2]==summary)
request(action,{'_token':csrf,'action':'send'});status,body,_=request(action,{'_token':csrf,'action':'poll','scenario':'98'})
check('ticket_and_98_are_not_acceptance',status==200 and b'SIM-H5B-' in body and b'Sin resultado definitivo confirmado' in body)
time.sleep(5.2)
status,body,_=request(action,{'_token':csrf,'action':'poll','scenario':'timeout'})
check('rc_uncertainty_prevents_resend',status==200 and request(action,{'_token':csrf,'action':'send'},json_body=True)[0]==409)
request(action,{'_token':csrf,'action':'recover'});status,body,_=request(action,{'_token':csrf,'action':'poll','scenario':'0'})
check('rc_controlled_query_records_result_and_history',status==200 and b'manual_query_rearmed' in body and b'result_saved' in body and b'H5B_LOCAL_FAKE' in body)
check('note_differentiates_its_rc_result',b'El resultado corresponde al RC' in request(bnpath)[1])
check('rc_private_zip_and_note_evidence',bool(exact_zip(sp)) and bool(exact_zip(bnpath)))
check('rc_terminal_actions_denied',all(request(action,{'_token':csrf,'action':a},json_body=True)[0]==409 for a in ('send','poll','recover')))
result={'scope':'H6-2B SEGUNDA ENTREGA NOTAS FICTICIAS SIN SUNAT','legacy_checks':previous,'checks':checks,'invoice_note':note,'boleta_note':bnote,'note_rc':summary,'invoice_note_xml_sha256':digest}
(args.output_dir/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
