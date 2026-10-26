"""Ensayo HTTP real, restringido a loopback y datos ficticios. No imprime credenciales."""
import base64
import argparse
import hashlib
import http.cookiejar
import io
import json
from html.parser import HTMLParser
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request
import zipfile

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--report', type=Path, default=ROOT / 'storage/audit/h2/http-smoke.json')
args = parser.parse_args()
env = {}
for line in (ROOT / ".env").read_text(encoding="utf-8-sig").splitlines():
    if "=" in line and not line.startswith("#"):
        key, value = line.split("=", 1)
        env[key] = value
port = int(env.get("H2_HOST_PORT", "8180"))
assert 1024 <= port <= 65535
BASE = f"http://127.0.0.1:{port}"
credential = base64.b64encode(
    ("operador.h2@example.invalid:" + env["H2_OPERATOR_PASSWORD"]).encode()
).decode()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
)
checks = {}


class Inputs(HTMLParser):
    def __init__(self, body):
        super().__init__()
        self.values = {}
        self.feed(body.decode())

    def handle_starttag(self, tag, attributes):
        fields = dict(attributes)
        if tag == "input" and "name" in fields:
            self.values[fields["name"]] = fields.get("value", "")


def request(path, data=None, authenticated=True, json_body=False, host=None):
    headers = {}
    if authenticated:
        headers["Authorization"] = "Basic " + credential
    if host:
        headers["Host"] = host
    if json_body:
        headers["Content-Type"] = "application/json"
        headers["Accept"] = "application/json"
        payload = json.dumps(data).encode()
    else:
        payload = urllib.parse.urlencode(data).encode() if data is not None else None
    message = urllib.request.Request(BASE + path, payload, headers)
    try:
        response = opener.open(message, timeout=30)
    except urllib.error.HTTPError as error:
        return error.code, error.read(), error.geturl()
    return response.status, response.read(), response.geturl()


def check(name, condition):
    assert condition, name
    checks[name] = True


status, _, _ = request("/invoices/create", authenticated=False)
check("authentication_401", status == 401)
status, body, _ = request("/invoices/create")
check("form_with_csrf_and_fake_marker", status == 200 and b'name="_token"' in body and b"Sin validez fiscal" in body)
values = Inputs(body).values
payload = {
    "_token": values["_token"], "idempotency_key": values["idempotency_key"],
    "description": "PRODUCTO FICTICIO H2 HTTP", "quantity": "2", "unit_price": "12.50",
}
without_csrf = {key: value for key, value in payload.items() if key != "_token"}
status, _, _ = request("/invoices", without_csrf)
check("csrf_419_without_test_bypass", status == 419)
status, body, invoice_url = request("/invoices", payload)
check("invoice_created_2950", status == 200 and b"29.50" in body and b"Pendiente de simulaci" in body)
invoice_path = urllib.parse.urlsplit(invoice_url).path
status, _, replay_url = request("/invoices", payload)
check("replay_returns_same_invoice", status == 200 and replay_url == invoice_url)
status, _, _ = request("/invoices", payload | {"unit_price": "13.50"}, json_body=True)
check("changed_replay_409", status == 409)
status, _, _ = request("/invoices", payload | {"total_cents": 1}, json_body=True)
check("client_total_spoof_422", status == 422)
status, xml, _ = request(invoice_path + "/evidence/xml")
check("private_xml_download", status == 200 and b"Invoice" in xml)
status, compressed, _ = request(invoice_path + "/evidence/zip")
with zipfile.ZipFile(io.BytesIO(compressed)) as archive:
    check("zip_matches_downloaded_xml", status == 200 and archive.read("invoice.xml") == xml and archive.testzip() is None)
status, _, _ = request("/.env")
check("env_not_exposed_404", status == 404)
status, _, _ = request("/storage/app/private/h2/" + invoice_path.rsplit("/", 1)[1] + "/signed.xml")
check("no_public_storage_alias_404", status == 404)
status, _, _ = request("/", host="evil.example.invalid")
check("host_header_rejected_400", status == 400)
csrf = Inputs(body).values["_token"]
status, simulated_body, _ = request(invoice_path + "/simulate", {"_token": csrf, "scenario": "accepted"})
check("simulation_clearly_fake", status == 200 and b"Aceptada en simulaci" in simulated_body and b"Sin CDR oficial" in simulated_body)
result = {
    "scope": "H2 LOCAL FICTICIO - SIN SUNAT",
    "invoice_url": invoice_url, "checks": checks,
    "xml_sha256": hashlib.sha256(xml).hexdigest(),
}
report = args.report
report.parent.mkdir(parents=True, exist_ok=True)
report.write_text(json.dumps(result, indent=2, ensure_ascii=False), encoding="utf-8")
print(json.dumps(result, ensure_ascii=True))
