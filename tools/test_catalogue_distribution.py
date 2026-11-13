#!/usr/bin/env python3
"""Check that the pinned Articles ZIP is reproducible and mirrors shipped sources."""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import zipfile
import xml.etree.ElementTree as ET

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
work_version = (ROOT / PREFIX / 'MODULE-VERSION').read_text().strip()
official_version = package['version']
unreleased = work_version != official_version
if unreleased:
    # Only a pull request may carry a newer module alongside the previously
    # published immutable catalogue. This is forbidden for the stable build.
    assert os.environ.get('P3_DEVELOPMENT_VALIDATION') == '1'
    assert tuple(map(int, work_version.split('.'))) > tuple(map(int, official_version.split('.')))
assert package['p3_min'] == ET.parse(ROOT / PREFIX / 'desc.xml').getroot().findtext('MinimumP3')

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
if not unreleased and first != official.read_bytes():
    raise AssertionError('Official package differs from current sources or rebuilt ZIP')
if hashlib.sha256(official.read_bytes()).hexdigest() != package['sha256']:
    raise AssertionError('Catalogue SHA-256 differs from official ZIP')
with zipfile.ZipFile(official) as archive:
    if archive.testzip() is not None:
        raise AssertionError('Invalid ZIP')
    if not unreleased and set(archive.namelist()) != set(expected):
        raise AssertionError('ZIP content list differs from tracked module files')
    for name, src in (expected.items() if not unreleased else []):
        if not unreleased and archive.read(name) != src.read_bytes():
            raise AssertionError('ZIP file differs from checked-in source: ' + name)
        if archive.getinfo(name).date_time != (1980, 1, 1, 0, 0, 0):
            raise AssertionError('ZIP timestamp is not reproducible: ' + name)
print('Articles: trusted official ZIP verified; working tree reproducible; '
      + ('newer candidate is not yet published' if unreleased else 'sources identical'))
