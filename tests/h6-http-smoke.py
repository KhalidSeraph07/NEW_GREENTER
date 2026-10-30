"""Sesiones por HTTP loopback; incluye los 71 controles H2–H5-B sin SUNAT."""
import argparse
import copy
import http.cookiejar
import json
import runpy
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
p = argparse.ArgumentParser()
p.add_argument('--output-dir', type=Path, required=True)
args = p.parse_args()
args.output_dir.mkdir(parents=True, exist_ok=False)
sys.argv = [str(ROOT / 'tests/h5b-http-smoke.py'), '--output-dir', str(args.output_dir / 'legacy')]
legacy = runpy.run_path(str(ROOT / 'tests/h5b-http-smoke.py'))
base = legacy['h4']['legacy']
request, Inputs, opener, BASE = base['request'], base['Inputs'], base['opener'], base['BASE']
checks = {}

def check(name, value):
    assert value, name
    checks[name] = True

response = opener.open(BASE + '/settings', timeout=30)
body = response.read()
check('config_read_only_and_no_secrets', b'00000000000' in body and base['env']['H2_OPERATOR_PASSWORD'].encode() not in body)
check('security_headers_on_authenticated_response', response.headers['X-Content-Type-Options'] == 'nosniff' and "frame-ancestors 'none'" in response.headers['Content-Security-Policy'] and 'no-store' in response.headers['Cache-Control'])
csrf = Inputs(body).values['_token']
check('operator_cannot_list_accounts', request('/accounts')[0] == 403)
check('operator_cannot_create_admin', request('/accounts', {'_token': csrf, 'role': 'ADMIN'})[0] == 403)
check('operator_cannot_deactivate', request('/accounts/1/deactivate', {'_token': csrf})[0] == 403)
check('config_has_no_post', request('/settings', {'_token': csrf})[0] == 405)
check('logout_requires_csrf', request('/logout', {})[0] == 419)
jar = next(h.cookiejar for h in opener.handlers if hasattr(h, 'cookiejar'))
cookies = list(jar)
check('session_cookie_http_only_same_site', any(c.has_nonstandard_attr('HttpOnly') and c.get_nonstandard_attr('SameSite', '').lower() == 'lax' for c in cookies))
saved = http.cookiejar.CookieJar()
for cookie in cookies:
    saved.set_cookie(copy.copy(cookie))
status, body, url = request('/logout', {'_token': csrf})
check('logout_returns_login_without_operator_navigation', status == 200 and urllib.parse.urlsplit(url).path == '/login' and b'Cerrar sesi' not in body)
check('session_after_logout_cannot_read', request('/', json_body=True)[0] == 401)
replay = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(saved))
try:
    replay.open(urllib.request.Request(BASE + '/', headers={'Accept': 'application/json'}), timeout=30)
    denied = False
except urllib.error.HTTPError as error:
    denied = error.code == 401
check('old_session_cookie_replay_denied', denied)
check('login_requires_csrf', request('/login', {'email': 'operador.h2@example.invalid', 'password': 'not-a-secret'})[0] == 419)
result = {'scope': 'H6-1 FICTICIO LOCAL SIN SUNAT', 'legacy_checks': 71, 'checks': checks}
(args.output_dir / 'result.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps(result))
