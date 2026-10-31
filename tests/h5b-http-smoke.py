"""H5-B loopback ficticio; conserva y ejecuta los recorridos H2/H4/H5-A."""
import argparse
import io
import json
import runpy
import sys
import time
import uuid
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
p = argparse.ArgumentParser()
p.add_argument('--output-dir', type=Path, default=ROOT / 'storage/audit/h5b' / ('http-' + str(uuid.uuid4())))
args = p.parse_args()
args.output_dir.mkdir(parents=True, exist_ok=False)
sys.argv = [str(ROOT / 'tests/h5-http-smoke.py'), '--output-dir', str(args.output_dir / 'legacy')]
h5 = runpy.run_path(str(ROOT / 'tests/h5-http-smoke.py'))
request, Inputs = h5['request'], h5['Inputs']
h4 = h5['h4']
checks = {}


def check(name, value):
    assert value, name
    checks[name] = True


status, _, _ = request('/receipt-notes', authenticated=False)
check('authentication_401', status == 401)
status, body, _ = request('/receipt-notes')
check('minimal_fictitious_form', status == 200 and b'H5-B' in body and b'Sin validez fiscal' in body)
csrf = Inputs(body).values['_token']
date = Inputs(body).values['emission_date']
source = h4['receipt_url'].rsplit('/', 1)[1]
payload = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'original_id': source, 'operation': 'NC01',
           'reason': 'ANULACION COMPLETA FICTICIA HTTP', 'emission_date': date, 'original_granted_test': '1'}
status, _, _ = request('/receipt-notes', {k: v for k, v in payload.items() if k != '_token'})
check('note_csrf_419', status == 419)
status, body, url = request('/receipt-notes', payload)
check('credit_prepared_not_accepted', status == 200 and b'118.00' in body and b'<strong>prepared</strong>' in body)
status, _, replay = request('/receipt-notes', payload)
check('note_replay_same_identity', status == 200 and replay == url)
note_id = url.rsplit('/', 1)[1]
status, _, _ = request('/receipt-notes', payload | {'idempotency_key': str(uuid.uuid4())}, json_body=True)
check('original_stays_reserved_409', status == 409)
status, _, _ = request('/receipt-notes', payload | {'currency': 'USD'}, json_body=True)
check('currency_override_422', status == 422)
status, xml, _ = request('/receipt-notes/note/' + note_id + '/evidence/xml')
ns = {'cac': 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
      'cbc': 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2',
      'sac': 'urn:sunat:names:specification:ubl:peru:schema:xsd:SunatAggregateComponents-1'}
root = ET.fromstring(xml)
original_number = root.find('cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID', ns).text
check('credit_xml_reference_03', status == 200 and root.tag.endswith('CreditNote') and
      root.find('cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode', ns).text == '03')
rc_payload = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'members': [note_id]}
status, _, _ = request('/note-summaries', {k: v for k, v in rc_payload.items() if k != '_token'}, json_body=True)
check('rc_csrf_419', status == 419)
assert request('/note-summaries/review', rc_payload, json_body=True)[0] == 200, 'review note RC before freeze'
status, body, rc_url = request('/note-summaries', rc_payload, json_body=True)
check('new_note_rc_pending', status == 200 and b'<strong>pending_send</strong>' in body)
rc_id = rc_url.rsplit('/', 1)[1]
status, _, replay = request('/note-summaries', rc_payload, json_body=True)
check('rc_replay_same_identity', status == 200 and replay == rc_url)
duplicate_note_rc = rc_payload | {'idempotency_key': str(uuid.uuid4())}
assert request('/note-summaries/review', duplicate_note_rc, json_body=True)[0] == 409, 'reserved note rejected at review'
status, _, _ = request('/note-summaries', duplicate_note_rc, json_body=True)
check('note_membership_reserved_409', status == 409)
status, rc_xml, _ = request('/receipt-notes/rc/' + rc_id + '/evidence/xml')
line = ET.fromstring(rc_xml).find('sac:SummaryDocumentsLine', ns)
check('rc_note_identity_and_explicit_boleta_reference', status == 200 and line.find('cbc:DocumentTypeCode', ns).text == '07' and
      line.find('cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID', ns).text == original_number and
      line.find('cac:BillingReference/cac:InvoiceDocumentReference/cbc:DocumentTypeCode', ns).text == '03' and
      line.find('cac:Status/cbc:ConditionCode', ns).text == '1')
status, compressed, _ = request('/receipt-notes/rc/' + rc_id + '/evidence/zip')
with zipfile.ZipFile(io.BytesIO(compressed)) as z:
    check('rc_private_zip_exact_xml', status == 200 and len(z.namelist()) == 1 and z.read(z.namelist()[0]) == rc_xml)
action = '/note-summaries/' + rc_id + '/simulate'
status, body, _ = request(action, {'_token': csrf, 'action': 'send'})
check('ticket_is_not_acceptance', status == 200 and b'SIM-H5B-' in body and b'<strong>awaiting</strong>' in body)
status, body, _ = request(action, {'_token': csrf, 'action': 'poll', 'scenario': '98'})
check('98_is_pending', status == 200 and b'<strong>awaiting</strong>' in body)
time.sleep(5.1)
status, body, _ = request(action, {'_token': csrf, 'action': 'poll', 'scenario': '0'})
check('0_correlated_fictitious_constancia', status == 200 and b'<strong>sim_accepted</strong>' in body and b'H5B_LOCAL_FAKE' in body and b'Sin CDR oficial' in body)
status, body, _ = request('/summaries/' + h4['rc_url'].rsplit('/', 1)[1])
check('historical_boleta_rc_still_accepted', status == 200 and b'sim_accepted' in body)

# A second own boleta proves ND and lost-ticket blocking without releasing its original.
status, _, b_url = request('/boletas', {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'description': 'ORIGINAL H5B HTTP',
                                      'quantity': '1', 'unit_price': '100', 'emission_date': date})
b_id = b_url.rsplit('/', 1)[1]
b_rc_payload = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'members': [b_id]}
assert request('/summaries/review', b_rc_payload, json_body=True)[0] == 200, 'review H4 RC before freeze'
status, _, b_rc = request('/summaries', b_rc_payload, json_body=True)
b_action = '/summaries/' + b_rc.rsplit('/', 1)[1] + '/simulate'
request(b_action, {'_token': csrf, 'action': 'send'})
request(b_action, {'_token': csrf, 'action': 'poll', 'scenario': '0'})
status, body, nd_url = request('/receipt-notes', payload | {'original_id': b_id, 'idempotency_key': str(uuid.uuid4()),
    'operation': 'ND02', 'increment_unit_price': '20.00', 'reason': 'INCREMENTO POSITIVO FICTICIO'})
check('debit_increment_only_2360', status == 200 and b'23.60' in body and b'Tipo 08 / motivo 02' in body)
lost_payload = rc_payload | {'idempotency_key': str(uuid.uuid4()), 'members': [nd_url.rsplit('/', 1)[1]]}
assert request('/note-summaries/review', lost_payload, json_body=True)[0] == 200, 'review lost-ticket note RC'
status, _, lost_url = request('/note-summaries', lost_payload, json_body=True)
lost_action = '/note-summaries/' + lost_url.rsplit('/', 1)[1] + '/simulate'
status, body, _ = request(lost_action, {'_token': csrf, 'action': 'send', 'scenario': 'lost_ticket'})
check('lost_ticket_uncertain', status == 200 and b'<strong>uncertain</strong>' in body and b'No recibido' in body)
status, body, _ = request(lost_action, {'_token': csrf, 'action': 'recover'})
lost_status, lost_body, _ = request('/receipt-notes/rc/' + lost_url.rsplit('/', 1)[1])
check('no_recovery_without_ticket', status == 409 and lost_status == 200 and b'<strong>uncertain</strong>' in lost_body and b'No recibido' in lost_body)
status, body, _ = request(lost_action, {'_token': csrf, 'action': 'send'})
check('no_uncertain_resend', status == 409 and b'<strong>uncertain</strong>' in request('/receipt-notes/rc/' + lost_url.rsplit('/', 1)[1])[1])
status, _, _ = request('/storage/app/private/h5b/' + note_id + '/signed.xml')
check('private_files_not_public', status == 404)
report = {'scope': 'H5-B FICTICIO SIN SUNAT NI CDR OFICIAL', 'checks': checks, 'legacy_checks': 48,
          'note_url': url, 'rc_url': rc_url, 'debit_url': nd_url, 'lost_ticket_rc_url': lost_url}
(args.output_dir / 'h5b.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps(report))
