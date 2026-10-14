#!/usr/bin/env python3
"""Read native admin HTTP pages on a disposable local WordPress installation."""
import http.cookiejar
from html.parser import HTMLParser
import json
import os
import re
import urllib.parse
import urllib.request


class Elements(HTMLParser):
    def __init__(self):
        super().__init__()
        self.elements = []

    def handle_starttag(self, tag, attributes):
        self.elements.append((tag, dict(attributes)))


def smoke():
    base = os.environ.get('QSN_BASE_URL', '')
    if not re.fullmatch(r'http://127\.0\.0\.1:[0-9]+', base):
        raise ValueError('Use an isolated localhost WordPress URL.')
    user = os.environ.get('QSN_ADMIN_USER')
    password = os.environ.get('QSN_ADMIN_PASSWORD')
    if not user or not password:
        raise ValueError('Supply test administrator credentials through environment variables.')
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    opener.open(base + '/wp-login.php', timeout=30).read()
    payload = urllib.parse.urlencode({'log': user, 'pwd': password, 'wp-submit': 'Log In',
                                     'redirect_to': base + '/wp-admin/', 'testcookie': '1'}).encode()
    response = opener.open(base + '/wp-login.php', payload, timeout=30)
    response.read()
    if not response.geturl().startswith(base + '/wp-admin/'):
        raise ValueError('Test administrator login failed.')
    screens = ('', '-products', '-templates', '-settings', '-activity', '-tools')
    loaded = []
    assets = set()
    for suffix in screens:
        slug = 'quixdevs-stocknotifier' + suffix
        response = opener.open(base + '/wp-admin/admin.php?page=' + slug, timeout=30)
        html = response.read().decode('utf-8')
        if response.status != 200 or 'class="wrap qsn"' not in html or 'StockNotifier' not in html:
            raise ValueError('Native admin screen did not render: ' + slug)
        if 'Fatal error' in html or 'There has been a critical error' in html:
            raise ValueError('PHP failure on native admin screen: ' + slug)
        elements = Elements()
        elements.feed(html)
        for tag, attributes in elements.elements:
            url = attributes.get('src') or attributes.get('href') or ''
            if '/wp-content/plugins/quixdevs-stocknotifier/assets/' in url:
                if urllib.parse.urlsplit(url).netloc != urllib.parse.urlsplit(base).netloc:
                    raise ValueError('Unexpected plugin asset origin.')
                assets.add(url)
        if suffix == '-settings':
            inputs = [attrs for tag, attrs in elements.elements if tag == 'input']
            threshold = next(attrs for attrs in inputs if attrs.get('id') == 'qsn-threshold')
            if not all(threshold.get(key) == value for key, value in {'type': 'number', 'min': '0', 'step': '1', 'inputmode': 'numeric'}.items()):
                raise ValueError('Required numeric input attributes missing.')
            providers = {attrs['value']: attrs for attrs in inputs if attrs.get('name') == 'provider'}
            if set(providers) != {'wordpress', 'dokan', 'wcvendors', 'publishpress'}:
                raise ValueError('Recipient provider controls missing.')
            if 'checked' not in providers['wordpress']:
                raise ValueError('Smoke test expects WordPress author mode.')
            if not all('disabled' in providers[key] for key in ['dokan', 'wcvendors', 'publishpress']):
                raise ValueError('Optional provider guards require absent/inactive test plugins.')
        loaded.append(slug)
    if len(assets) != 2:
        raise ValueError('Expected both plugin CSS and JavaScript assets.')
    for url in assets:
        response = opener.open(url, timeout=30)
        if response.status != 200 or len(response.read()) < 100:
            raise ValueError('Required plugin asset failed to load.')
    print(json.dumps({'native_admin_screens': loaded, 'runtime_assets_loaded': 2,
                      'optional_provider_guards': 'pass', 'stock_and_mail_actions': 'not invoked'}, indent=2))


if __name__ == '__main__':
    smoke()
