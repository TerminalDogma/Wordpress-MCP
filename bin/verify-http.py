#!/usr/bin/env python3
"""Check local pages, multisite rewrites, and administrator sign-in."""
from http.cookiejar import CookieJar
from pathlib import Path
import sys
from urllib.parse import urlencode, urlparse
from urllib.request import HTTPCookieProcessor, ProxyHandler, build_opener

root = Path(__file__).resolve().parent.parent
config = dict(
    line.split('=', 1) for line in (root / '.env').read_text().splitlines()
    if line and not line.startswith('#') and '=' in line
)
base = config.get('WORDPRESS_URL', 'http://localhost:8080').rstrip('/')
if urlparse(base).hostname not in ('localhost', '127.0.0.1'):
    raise SystemExit('This test is limited to the local loopback installation.')
mode = sys.argv[1] if len(sys.argv) > 1 else 'multisite'
paths = ['', '/site-a', '/site-b'] if mode == 'multisite' else ['']
# Local loopback requests should not pass through a corporate HTTP proxy.
opener = build_opener(ProxyHandler({}), HTTPCookieProcessor(CookieJar()))

def get(path):
    response = opener.open(base + path, timeout=30)
    data = response.read()
    if response.status != 200:
        raise RuntimeError('Unexpected HTTP status: ' + str(response.status))
    return response, data

for path in paths:
    get(path + '/')
    response, data = get(path + '/wp-includes/css/dashicons.min.css')
    if 'text/css' not in response.headers.get('Content-Type', '') or not data:
        raise RuntimeError('Subsite static asset rewrite failed.')

get('/wp-login.php')
payload = urlencode({
    'log': config.get('WORDPRESS_ADMIN_USER', 'siteadmin'),
    'pwd': config['WORDPRESS_ADMIN_PASSWORD'],
    'wp-submit': 'Log In',
    'redirect_to': base + '/wp-admin/',
    'testcookie': '1',
}).encode()
response = opener.open(base + '/wp-login.php', data=payload, timeout=30)
response.read()
if urlparse(response.url).path != '/wp-admin/':
    raise RuntimeError('Administrator sign-in failed. The .env password may differ from the existing account.')

for path in paths:
    response, data = get(path + '/wp-admin/')
    if urlparse(response.url).path != path + '/wp-admin/' or b'id="wpadminbar"' not in data:
        raise RuntimeError('Authenticated dashboard failed: ' + path)
if mode == 'multisite':
    response, data = get('/wp-admin/network/')
    if urlparse(response.url).path != '/wp-admin/network/' or b'id="wpadminbar"' not in data:
        raise RuntimeError('Network Admin is unavailable.')
print('HTTP pages, rewrites, and administrator sign-in passed (' + mode + ').')
