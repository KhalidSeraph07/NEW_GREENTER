"""Recorrido H6-2A loopback; conserva los 83 controles de regresión anteriores."""
import argparse
import hashlib
import io
import json
import runpy
import sys
import urllib.parse
import urllib.request
import uuid
import zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
p=argparse.ArgumentParser();p.add_argument('--output-dir',type=Path,required=True);args=p.parse_args()
args.output_dir.mkdir(parents=True,exist_ok=False)
sys.argv=[str(ROOT/'tests/h6-http-smoke.py'),'--output-dir',str(args.output_dir/'legacy')]
legacy=runpy.run_path(str(ROOT/'tests/h6-http-smoke.py'))
base=legacy['base'];request,Inputs,opener,BASE=base['request'],base['Inputs'],base['opener'],base['BASE']
# H6-1 termina con logout: iniciar una sesión nueva para el recorrido de factura.
form=opener.open(BASE+'/login',timeout=30).read()
login=urllib.parse.urlencode({'_token':Inputs(form).values['_token'],'email':'operador.h2@example.invalid','password':base['env']['H2_OPERATOR_PASSWORD']}).encode()
opener.open(urllib.request.Request(BASE+'/login',login),timeout=30).read()
checks={}
def check(name,value):
 assert value,name
 checks[name]=True
status,body,_=request('/invoices/create');values=Inputs(body).values
payload={'_token':values['_token'],'idempotency_key':values['idempotency_key'],'description':'RECORRIDO FICTICIO H6-2A HTTP','quantity':'2','unit_price':'12.50'}
check('guest_review_denied',request('/invoices/review',payload,authenticated=False)[0] in (401,419))
check('review_csrf_required',request('/invoices/review',{k:v for k,v in payload.items() if k!='_token'})[0]==419)
check('confirmation_without_review_blocked',request('/invoices',payload,json_body=True)[0]==409)
status,body,_=request('/invoices/review',payload)
check('server_review_has_totals_no_document',status==200 and b'25.00 PEN' in body and b'4.50 PEN' in body and b'29.50 PEN' in body and b'no se ha creado una factura' in body)
check('review_preserves_operation_key',Inputs(body).values['idempotency_key']==payload['idempotency_key'])
status,body,_=request('/invoices/edit-review',payload)
check('edit_preserves_values_and_key',status==200 and Inputs(body).values['quantity']=='2' and Inputs(body).values['idempotency_key']==payload['idempotency_key'])
check('changed_confirmation_blocked',request('/invoices',payload|{'unit_price':'13.50'},json_body=True)[0]==409)
status,body,url=request('/invoices',payload);path=urllib.parse.urlsplit(url).path
check('prepared_only_after_confirmation',status==200 and b'29.50' in body and b'Factura preparada y registrada' in body)
check('double_confirmation_same_document',request('/invoices',payload)[2]==url)
csrf=Inputs(body).values['_token']
status,xml,_=request(path+'/evidence/xml');digest=hashlib.sha256(xml).hexdigest()
status,body,_=request(path+'/simulate',{'_token':csrf,'scenario':'timeout'})
check('timeout_is_uncertain_not_final',status==200 and b'Resultado incierto' in body and b'Sin resultado definitivo confirmado' in body and b'SIM-ACCEPTED' not in body)
check('uncertain_resend_blocked',request(path+'/simulate',{'_token':csrf,'scenario':'accepted'},json_body=True)[0]==409)
check('recover_csrf_required',request(path+'/recover',{})[0]==419)
status,body,_=request(path+'/recover',{'_token':csrf})
check('manual_recovery_uses_recorded_result',status==200 and b'Resultado recuperado del simulador' in body and b'SIM-ACCEPTED' in body and b'Sin CDR oficial' in body)
check('terminal_recovery_blocked',request(path+'/recover',{'_token':csrf},json_body=True)[0]==409)
status,new_xml,_=request(path+'/evidence/xml');check('xml_preserved_through_recovery',status==200 and hashlib.sha256(new_xml).hexdigest()==digest)
status,data,_=request(path+'/evidence/zip')
with zipfile.ZipFile(io.BytesIO(data)) as z: check('private_zip_matches_original_xml',status==200 and z.read('invoice.xml')==xml)
# Nueva factura sin constancia en el simulador: consultar no fabrica aceptación.
payload2=payload|{'idempotency_key':str(uuid.uuid4()),'description':'INCERTIDUMBRE SIN RESULTADO H6-2A'}
request('/invoices/review',payload2);status,body,url2=request('/invoices',payload2);path2=urllib.parse.urlsplit(url2).path
request(path2+'/simulate',{'_token':csrf,'scenario':'uncertain'});status,body,_=request(path2+'/recover',{'_token':csrf})
check('unresolved_query_remains_uncertain',status==200 and b'Consulta sin resultado verificable' in body and b'SIM-ACCEPTED' not in body)
result={'scope':'H6-2A FICTICIO LOCAL SIN SUNAT','invoice_url':url,'unresolved_invoice_url':url2,'legacy_checks':83,'checks':checks,'xml_sha256':digest}
(args.output_dir/'result.json').write_text(json.dumps(result,indent=2),encoding='utf-8');print(json.dumps(result))
