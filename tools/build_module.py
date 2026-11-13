#!/usr/bin/env python3
"""Build a reproducible ZIP containing only tracked files of a P3 module."""
import argparse
import hashlib
from pathlib import Path
import re
import stat
import subprocess
import xml.etree.ElementTree as ET
import zipfile

ROOT = Path(__file__).resolve().parent.parent


def build(module):
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]{0,62}', module):
        raise ValueError('Invalid module directory')
    folder = ROOT / 'var/modules' / module
    manifest = ET.parse(folder / 'desc.xml').getroot()
    version = (manifest.findtext('Version') or '').strip()
    if not re.fullmatch(r'\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?', version):
        raise ValueError('Invalid module version')
    if (folder / 'MODULE-VERSION').read_text().strip() != version:
        raise ValueError('Inconsistent MODULE-VERSION')
    prefix = 'var/modules/' + module + '/'
    tracked = subprocess.check_output(['git', 'ls-files', '-z', '--', prefix], cwd=ROOT).decode().split('\0')
    paths = sorted(p for p in tracked if p.startswith(prefix))
    if prefix + 'desc.xml' not in paths:
        raise ValueError('Module manifest is not tracked')
    output = ROOT / 'dist'
    output.mkdir(exist_ok=True)
    archive = output / f'P3-{module}-{version}.zip'
    with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as package:
        for path in paths:
            file = ROOT / path
            if file.is_symlink() or not file.is_file() or not stat.S_ISREG(file.stat().st_mode) or file.suffix in {'.sqlite', '.log', '.pyc'}:
                raise ValueError('Unexpected runtime or non-regular module file')
            # A fixed DOS timestamp and mode make ZIP bytes repeatable on different machines.
            info = zipfile.ZipInfo(module + '/' + path[len(prefix):], (1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.create_system = 3
            info.external_attr = 0o100644 << 16
            package.writestr(info, file.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(archive) as package:
        if package.testzip() is not None:
            raise ValueError('Corrupt module archive')
        if {p.split('/')[0] for p in package.namelist()} != {module}:
            raise ValueError('Expected one module root')
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    archive.with_suffix('.sha256').write_text(digest + '  ' + archive.name + '\n')
    print(f'{archive.name}: {len(paths)} files, {archive.stat().st_size} bytes, sha256 {digest}')
    return archive


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('module')
    build(parser.parse_args().module)
