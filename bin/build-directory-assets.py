#!/usr/bin/env python3
"""Synchronize listing screenshots from tracked originals without touching runtime."""
import argparse
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]
SCREENSHOTS = ('dashboard', 'settings', 'email-templates', 'low-stock-products', 'activity', 'tools')

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Fail on drift instead of copying originals.')
    args = parser.parse_args()
    saved = 0
    for number, name in enumerate(SCREENSHOTS, 1):
        source = ROOT / ('docs/screenshots/' + name + '.png')
        target = ROOT / ('wordpress-org-assets/screenshot-' + str(number) + '.png')
        if not source.is_file():
            raise SystemExit('Missing original screenshot: ' + str(source))
        if args.check:
            if not target.is_file() or source.read_bytes() != target.read_bytes():
                raise SystemExit('Listing screenshot drift: ' + str(target))
        else:
            target.parent.mkdir(exist_ok=True)
            shutil.copyfile(source, target)
        saved += source.stat().st_size
    print('PASS: six listing screenshots match canonical originals (' + str(saved) + ' bytes outside the runtime ZIP).')
