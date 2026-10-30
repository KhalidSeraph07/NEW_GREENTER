"""H5-A loopback ficticio; ejecuta tambien H2/H4 sin sobrescribir informes."""
import argparse
import io
import json
import runpy
import sys
import uuid
import xml.etree.ElementTree as ET
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--output-dir', type=Path, default=ROOT / 'storage/audit/h5a' / ('http-' + str(uuid.uuid4())))
args = parser.parse_args()
args.output_dir.mkdir(parents=True, exist_ok=False)
sys.argv = [str(ROOT / 'tests/h4-http-smoke.py'), '--output-dir', str(args.output_dir / 'legacy')]
h4 = runpy.run_path(str(ROOT / 'tests/h4-http-smoke.py'))
request, Inputs = h4['request'], h4['Inputs']
checks = {}


def check(name, value):
    assert value, name
    checks[name] = True


status, body, _ = request('/invoice-notes', authenticated=False)
check('authentication_401', status == 401)
status, body, _ = request('/invoice-notes')
check('minimal_form_and_fictitious_marker', status == 200 and b'H5-A' in body and b'Sin validez fiscal' in body)
csrf = Inputs(body).values['_token']
date = Inputs(body).values['emission_date']
source_id = h4['legacy']['invoice_url'].rsplit('/', 1)[1]
payload = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()), 'original_id': source_id,
           'operation': 'NC01', 'reason': 'ANULACION TOTAL FICTICIA HTTP', 'original_granted_test': '1', 'emission_date': date}
status, _, _ = request('/invoice-notes', {k: v for k, v in payload.items() if k != '_token'})
check('csrf_419', status == 419)
status, body, note_url = request('/invoice-notes', payload)
note_path = '/invoice-notes/' + note_url.rsplit('/', 1)[1]
check('credit_issued_2950', status == 200 and b'29.50' in body and b'pending' in body)
status, _, replay = request('/invoice-notes', payload)
check('replay_same_identity', status == 200 and replay == note_url)
status, _, _ = request('/invoice-notes', payload | {'idempotency_key': str(uuid.uuid4())}, json_body=True)
check('original_cannot_be_reserved_twice', status == 409)
status, _, _ = request('/invoice-notes', payload | {'currency': 'USD'}, json_body=True)
check('currency_override_blocked', status == 422)
status, xml, _ = request(note_path + '/evidence/xml')
root = ET.fromstring(xml)
ns = {'cac': 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2',
      'cbc': 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2'}
check('credit_xml_07_01', status == 200 and root.tag.endswith('CreditNote') and root.find('cac:DiscrepancyResponse/cbc:ResponseCode', ns).text == '01')
status, compressed, _ = request(note_path + '/evidence/zip')
with zipfile.ZipFile(io.BytesIO(compressed)) as z:
    check('private_zip_exact_xml', status == 200 and len(z.namelist()) == 1 and z.read(z.namelist()[0]) == xml)
status, body, _ = request(note_path + '/simulate', {'_token': csrf, 'action': 'dispatch', 'scenario': 'timeout'})
check('timeout_uncertain_not_accepted', status == 200 and b'<strong>uncertain</strong>' in body and b'<strong>sim_accepted</strong>' not in body)
status, body, _ = request(note_path + '/simulate', {'_token': csrf, 'action': 'dispatch'})
check('uncertain_no_resend', status == 200 and b'<strong>uncertain</strong>' in body)
status, body, _ = request(note_path + '/simulate', {'_token': csrf, 'action': 'recover'})
check('local_fake_query_recovers', status == 200 and b'sim_accepted' in body and b'H5A_LOCAL_FAKE' in body and b'Sin CDR oficial' in body)

invoice_input = {'_token': csrf, 'idempotency_key': str(uuid.uuid4()),
    'description': 'ORIGINAL FICTICIO ND H5-A', 'quantity': '1', 'unit_price': '100'}
status, _, _ = request('/invoices/review', invoice_input)
assert status == 200
status, _, invoice_url = request('/invoices', invoice_input)
invoice_path = '/invoices/' + invoice_url.rsplit('/', 1)[1]
status, _, _ = request(invoice_path + '/simulate', {'_token': csrf, 'scenario': 'accepted'})
debit = payload | {'idempotency_key': str(uuid.uuid4()), 'original_id': invoice_url.rsplit('/', 1)[1],
                   'operation': 'ND02', 'reason': 'COSTO POSTERIOR FICTICIO', 'increment_unit_price': '20.00'}
status, body, debit_url = request('/invoice-notes', debit)
check('debit_is_increment_only_2360', status == 200 and b'23.60' in body and b'Tipo 08 / motivo 02' in body)
debit_path = '/invoice-notes/' + debit_url.rsplit('/', 1)[1]
status, body, _ = request(debit_path + '/simulate', {'_token': csrf, 'action': 'dispatch', 'scenario': 'rejected'})
check('rejection_explicit_and_terminal', status == 200 and b'sim_rejected' in body and b'Requiere revisi' in body)
status, _, _ = request('/invoice-notes', debit | {'idempotency_key': str(uuid.uuid4())}, json_body=True)
check('rejected_note_does_not_release_original', status == 409)
status, xml, _ = request(debit_path + '/evidence/xml')
check('debit_xml_08_02', status == 200 and ET.fromstring(xml).tag.endswith('DebitNote') and ET.fromstring(xml).find('cac:DiscrepancyResponse/cbc:ResponseCode', ns).text == '02')
status, _, _ = request('/storage/app/private/h5/' + debit_url.rsplit('/', 1)[1] + '/signed.xml')
check('private_files_not_public', status == 404)
status, _, _ = request('/boleta-notes')
check('unsupported_boleta_notes_alias_404', status == 404)
report = {'scope': 'H5-A FICTICIO SIN SUNAT NI CDR OFICIAL', 'checks': checks, 'h2_checks': 13, 'h4_checks': 17,
          'credit_url': note_url, 'debit_url': debit_url}
(args.output_dir / 'h5a.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps(report))
