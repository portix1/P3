#!/usr/bin/env python3
"""Check that the pinned Articles ZIP is reproducible and mirrors shipped sources."""
import hashlib
import json
from pathlib import Path
import subprocess
import zipfile

from build_module import ROOT, build

MODULE = 'articles'
PREFIX = 'var/modules/articles/'
catalogue = json.loads((ROOT / 'distribution/catalogue.json').read_text())
assert catalogue['schema'] == 1
packages = [
    p for p in catalogue['packages']
    if p.get('kind') == 'module' and p.get('id') == MODULE
]
if len(packages) != 1:
    raise AssertionError('Expected exactly one official Articles package')
package = packages[0]
assert package['version'] == (ROOT / PREFIX / 'MODULE-VERSION').read_text().strip()
assert package['p3_min'] == '0.9.0'

tracked = sorted(
    p for p in subprocess.check_output(
        ['git', 'ls-files', '-z', '--', PREFIX], cwd=ROOT
    ).decode().split('\0') if p.startswith(PREFIX)
)
expected = {MODULE + '/' + name[len(PREFIX):]: ROOT / name for name in tracked}
produced = build(MODULE)
first = produced.read_bytes()
build(MODULE)
if produced.read_bytes() != first:
    raise AssertionError('Module builder is not reproducible')
official = ROOT / 'distribution' / package['file']
if first != official.read_bytes():
    raise AssertionError('Official package differs from current sources or rebuilt ZIP')
if hashlib.sha256(first).hexdigest() != package['sha256']:
    raise AssertionError('Catalogue SHA-256 differs from official ZIP')
with zipfile.ZipFile(official) as archive:
    if archive.testzip() is not None:
        raise AssertionError('Invalid ZIP')
    if set(archive.namelist()) != set(expected):
        raise AssertionError('ZIP content list differs from tracked module files')
    for name, src in expected.items():
        if archive.read(name) != src.read_bytes():
            raise AssertionError('ZIP file differs from checked-in source: ' + name)
        if archive.getinfo(name).date_time != (1980, 1, 1, 0, 0, 0):
            raise AssertionError('ZIP timestamp is not reproducible: ' + name)
print('Articles 1.4.8: ZIP reproducible, SHA-256 verified, sources identical')
