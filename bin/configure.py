#!/usr/bin/env python3
"""Generate local configuration without printing or replacing credentials."""
import os
from pathlib import Path
import secrets

root = Path(__file__).resolve().parent.parent
path = root / '.env'
values = {
    'WORDPRESS_PORT': '8080',
    'WORDPRESS_URL': 'http://localhost:8080',
    'WORDPRESS_TITLE': 'WordPress with ACF',
    'WORDPRESS_ADMIN_USER': 'siteadmin',
    'WORDPRESS_ADMIN_EMAIL': 'admin@example.test',
    'WORDPRESS_ADMIN_PASSWORD': secrets.token_hex(24),
    'DB_PASSWORD': secrets.token_hex(24),
    'DB_ROOT_PASSWORD': secrets.token_hex(24),
}
try:
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
except FileExistsError:
    print('Existing .env preserved.')
else:
    with os.fdopen(descriptor, 'w') as stream:
        stream.write(''.join(f'{key}={value}\n' for key, value in values.items()))
    print('Created .env with random credentials. Edit the admin email before setup.')
