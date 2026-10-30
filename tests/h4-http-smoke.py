"""Recorrido H2/H4 sobre loopback; conserva informes previos y no imprime credenciales."""
import argparse
import io
import json
import runpy
import sys
import time
import uuid
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--output-dir', type=Path, default=ROOT / 'storage/audit/h4' / ('http-' + str(uuid.uuid4())))
args = parser.parse_args()
args.output_dir.mkdir(parents=True, exist_ok=False)
sys.argv = [str(ROOT / 'tests/http-smoke.py'), '--report', str(args.output_dir / 'h2.json')]
legacy = runpy.run_path(str(ROOT / 'tests/http-smoke.py'))
request, Inputs = legacy['request'], legacy['Inputs']
checks = {}


def check(name, condition):
    assert condition, name
    checks[name] = True


status, body, _ = request('/boletas')
check('h4_form_and_fake_marker', status == 200 and b'H4' in body and b'Sin validez fiscal' in body)
csrf = Inputs(body).values['_token']
payload = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'description': 'BOLETA FICTICIA H4 HTTP',
           'quantity': '1', 'unit_price': '100.00', 'emission_date': Inputs(body).values['emission_date']}
status, _, _ = request('/boletas', {k: v for k, v in payload.items() if k != '_token'})
check('boleta_csrf_419', status == 419)
status, body, receipt_url = request('/boletas', payload)
receipt_id = receipt_url.rsplit('/', 1)[1]
check('boleta_created', status == 200 and b'Boleta ficticia B000-' in body)
status, _, replay = request('/boletas', payload)
check('boleta_replay', status == 200 and replay == receipt_url)
status, xml, _ = request('/h4/boleta/' + receipt_id + '/evidence/xml')
root = ET.fromstring(xml)
check('xml_03_private', status == 200 and root.find('{urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2}InvoiceTypeCode').text == '03')
rc_payload = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'members': [receipt_id]}
assert request('/summaries/review', rc_payload, json_body=True)[0] == 200, 'review before freeze'
status, body, rc_url = request('/summaries', rc_payload, json_body=True)
rc_id = rc_url.rsplit('/', 1)[1]
check('rc_frozen_with_member', status == 200 and b'pending_send' in body and receipt_id.encode() in body)
status, _, replay = request('/summaries', rc_payload, json_body=True)
check('rc_replay', status == 200 and replay == rc_url)
duplicate_rc = rc_payload | {'idempotency_key': str(uuid.uuid4())}
assert request('/summaries/review', duplicate_rc, json_body=True)[0] == 409, 'reserved member rejected at review'
status, _, _ = request('/summaries', duplicate_rc, json_body=True)
check('duplicate_membership_409', status == 409)
action = '/summaries/' + rc_id + '/simulate'
status, body, _ = request(action, {'_token': csrf, 'action': 'send'})
check('ticket_is_not_acceptance', status == 200 and b'SIM-H4-' in body and b'awaiting' in body and b'sim_accepted' not in body)
status, body, _ = request(action, {'_token': csrf, 'action': 'poll', 'scenario': '98'})
check('query_98_pending', status == 200 and b'awaiting' in body and b'sim_accepted' not in body)
time.sleep(5.1)
status, body, _ = request(action, {'_token': csrf, 'action': 'poll', 'scenario': '0'})
check('query_0_fake_result', status == 200 and b'sim_accepted' in body and b'Sin CDR oficial' in body and b'result_saved' in body)
status, rc_xml, _ = request('/h4/rc/' + rc_id + '/evidence/xml')
check('rc_summary_documents', status == 200 and ET.fromstring(rc_xml).tag.endswith('SummaryDocuments'))
status, compressed, _ = request('/h4/rc/' + rc_id + '/evidence/zip')
with zipfile.ZipFile(io.BytesIO(compressed)) as z:
    check('rc_zip_exact_xml', status == 200 and len(z.namelist()) == 1 and z.read(z.namelist()[0]) == rc_xml)
status, _, _ = request('/storage/app/private/h4/' + rc_id + '/signed.xml')
check('h4_evidence_not_public', status == 404)

payload['idempotency_key'] = str(uuid.uuid4())
status, _, lost_receipt_url = request('/boletas', payload)
lost_receipt = lost_receipt_url.rsplit('/', 1)[1]
lost_payload = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'members': [lost_receipt]}
assert request('/summaries/review', lost_payload, json_body=True)[0] == 200, 'review lost-ticket RC'
status, _, lost_url = request('/summaries', lost_payload, json_body=True)
lost_action = '/summaries/' + lost_url.rsplit('/', 1)[1] + '/simulate'
status, body, _ = request(lost_action, {'_token': csrf, 'action': 'send', 'scenario': 'lost_ticket'})
check('lost_ticket_uncertain', status == 200 and b'uncertain' in body and b'No recibido' in body)
status, body, _ = request(lost_action, {'_token': csrf, 'action': 'recover'})
lost_status, lost_body, _ = request('/summaries/' + lost_url.rsplit('/', 1)[1])
check('lost_ticket_no_lookup_recovery', status == 409 and lost_status == 200 and b'uncertain' in lost_body and b'No recibido' in lost_body)
status, body, _ = request(lost_action, {'_token': csrf, 'action': 'send'})
lost_status, lost_body, _ = request('/summaries/' + lost_url.rsplit('/', 1)[1])
check('lost_ticket_no_resend', status == 409 and lost_status == 200 and b'uncertain' in lost_body and b'No recibido' in lost_body)
report = {'scope': 'H4 LOCAL FICTICIO SIN SUNAT NI CDR OFICIAL', 'h2_checks': len(legacy['checks']),
          'checks': checks, 'receipt_url': receipt_url, 'rc_url': rc_url, 'lost_ticket_rc_url': lost_url}
(args.output_dir / 'h4.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps(report))
