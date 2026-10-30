"""Recorrido de boletas/RC por HTTP loopback; conserva los 100 controles anteriores."""
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

ROOT = Path(__file__).resolve().parents[1]
p = argparse.ArgumentParser()
p.add_argument('--output-dir', type=Path, required=True)
args = p.parse_args()
args.output_dir.mkdir(parents=True, exist_ok=False)
sys.argv = [str(ROOT/'tests/h62a-http-smoke.py'), '--output-dir', str(args.output_dir/'legacy')]
legacy = runpy.run_path(str(ROOT/'tests/h62a-http-smoke.py'))
request, Inputs = legacy['request'], legacy['Inputs']
checks = {}

class Forms(HTMLParser):
    def __init__(self, body):
        super().__init__(); self.forms = {}; self.current = None; self.feed(body.decode())
    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if tag == 'form':
            self.current = urllib.parse.urlsplit(a.get('action', '')).path
            self.forms[self.current] = {}
        elif self.current is not None and tag == 'input' and a.get('name'):
            if a.get('type') != 'checkbox':
                self.forms[self.current][a['name']] = a.get('value', '')
    def handle_endtag(self, tag):
        if tag == 'form': self.current = None

def check(name, condition):
    assert condition, name
    checks[name] = True

status, body, _ = request('/boletas')
f = Forms(body).forms['/boletas']
payload = f | {'description':'BOLETA FICTICIA H6-2B HTTP', 'quantity':'2', 'unit_price':'12.50'}
key = payload['idempotency_key']; csrf = payload['_token']
status, body, _ = request('/boletas', payload | {'quantity':'0'})
check('validation_keeps_boleta_values_and_key', status == 200 and Forms(body).forms['/boletas']['idempotency_key'] == key and b'BOLETA FICTICIA H6-2B HTTP' in body)
status, body, boleta_url = request('/boletas', payload)
bid = boleta_url.rsplit('/', 1)[1]
check('boleta_prepared_server_total', status == 200 and b'29.50' in body and 'Aún no incluida'.encode() in body)
check('boleta_double_submit_same_identity', request('/boletas', payload)[2] == boleta_url)
status, body, _ = request('/boletas')
rc_key = Forms(body).forms['/summaries/review']['idempotency_key']
# JSON mantiene el array explícito de miembros; usa la misma sesión y CSRF que el formulario.
rc = {'_token':csrf, 'idempotency_key':rc_key, 'members':[bid]}
check('review_guest_and_csrf_denied', request('/summaries/review', rc, authenticated=False, json_body=True)[0] in (401,419) and request('/summaries/review', {k:v for k,v in rc.items() if k!='_token'}, json_body=True)[0] == 419)
check('freeze_requires_review', request('/summaries', rc, json_body=True)[0] == 409)
status, body, _ = request('/summaries/review', rc, json_body=True)
check('rc_review_totals_no_reservation', status == 200 and b'29.50' in body and 'no se reserva numeración' in body.decode())
check('rc_review_preserves_operation', Forms(body).forms['/summaries']['idempotency_key'] == rc_key)
check('rc_edit_keeps_selection_and_key', request('/summaries/edit-review', rc, json_body=True)[0] == 200 and rc_key.encode() in request('/summaries/edit-review', rc, json_body=True)[1])
status, body, rc_url = request('/summaries', rc, json_body=True)
rid = rc_url.rsplit('/', 1)[1]; path = '/summaries/'+rid; action = path+'/simulate'
check('rc_freezes_once', status == 200 and b'pending_send' in body and request('/summaries', rc, json_body=True)[2] == rc_url)
check('reserved_member_cannot_enter_new_rc', request('/summaries/review', rc | {'idempotency_key':str(uuid.uuid4())}, json_body=True)[0] == 409)
status, xml, _ = request('/h4/rc/'+rid+'/evidence/xml'); digest = hashlib.sha256(xml).hexdigest()
status, body, _ = request(action, {'_token':csrf, 'action':'send'})
check('ticket_is_only_transport_not_result', status == 200 and b'SIM-H4-' in body and b'Sin resultado definitivo confirmado' in body and b'id="send_scenario"' not in body)
status, body, _ = request(action, {'_token':csrf, 'action':'poll', 'scenario':'98'})
check('98_retains_wait_and_blocks_early_poll', status == 200 and b'query_98' in body and request(action, {'_token':csrf, 'action':'poll','scenario':'0'}, json_body=True)[0] == 409)
time.sleep(5.2)
status, body, _ = request(action, {'_token':csrf, 'action':'poll','scenario':'timeout'})
check('uncertain_query_blocks_resend', status == 200 and b'Resultado incierto' in body and request(action, {'_token':csrf, 'action':'send'}, json_body=True)[0] == 409)
status, body, _ = request(action, {'_token':csrf,'action':'recover'})
check('manual_recovery_only_rearms_query', status == 200 and b'manual_query_rearmed' in body and b'id="poll_scenario"' in body)
status, body, _ = request(action, {'_token':csrf,'action':'poll','scenario':'0'})
check('correlated_fictitious_result_and_history', status == 200 and b'sim_accepted' in body and b'result_saved' in body and b'Sin CDR oficial' in body)
check('boleta_result_is_explicitly_rc_result', b'El resultado corresponde al RC' in request('/boletas/'+bid)[1])
check('terminal_actions_blocked', all(request(action, {'_token':csrf,'action':a}, json_body=True)[0] == 409 for a in ('send','poll','recover')))
status, data, _ = request('/h4/rc/'+rid+'/evidence/zip')
with zipfile.ZipFile(io.BytesIO(data)) as z:
    check('private_zip_preserves_original_signed_xml', status == 200 and z.read(z.namelist()[0]) == xml and hashlib.sha256(request('/h4/rc/'+rid+'/evidence/xml')[1]).hexdigest() == digest)
result = {'scope':'H6-2B PRIMERA ENTREGA FICTICIA LOCAL SIN SUNAT', 'boleta_url':boleta_url, 'rc_url':rc_url, 'legacy_checks':100, 'checks':checks, 'xml_sha256':digest}
(args.output_dir/'result.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result))
