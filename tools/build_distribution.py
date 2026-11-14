#!/usr/bin/env python3
"""Build an installation ZIP from tracked source files, excluding runtime data."""
import argparse
import hashlib
import os
from pathlib import Path
import re
import stat
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parent.parent
EXCLUDED = ('.github/', 'patches/', 'distribution/', 'dist/')
RUNTIME = ('database/', 'sessions/', 'tmp/', 'var/private/', 'var/sys/', 'var/uploads/', 'var/cache/')
PLACEHOLDERS = {'.gitkeep', '.htaccess', 'web.config'}


def include(name):
    if name in {'.gitignore', '.gitattributes', 'AGENTS.md', 'PREPARATION-P3-0.9.2.md'} or name.startswith(EXCLUDED):
        return False
    return not name.startswith(RUNTIME) or Path(name).name in PLACEHOLDERS


def build(excluded_modules=()):
    version = (ROOT / 'VERSION').read_text().strip()
    if not re.fullmatch(r'\d+\.\d+\.\d+(?:-[A-Za-z0-9][A-Za-z0-9.-]*)?', version):
        raise RuntimeError('Invalid VERSION')
    tracked = subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT).decode().split('\0')
    for module in excluded_modules:
        if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]{0,62}', module) or not (ROOT / 'var/modules' / module / 'desc.xml').is_file():
            raise RuntimeError('Unknown module exclusion')
    paths = sorted(name for name in tracked if name and include(name) and not any(name.startswith('var/modules/' + module + '/') for module in excluded_modules))
    required = {'VERSION', 'LICENSE', 'index.php', 'install.php', '.htaccess', 'bootstrap/app.php',
                'var/private/.htaccess', 'src/Maintenance/UpdateSafety.php', 'var/modules/safety/templates/rollback.tpl'}
    if not required.issubset(paths):
        raise RuntimeError('Distribution is missing required source files')
    output = ROOT / 'dist'
    output.mkdir(exist_ok=True)
    archive = output / f'P3-{version}.zip'
    temporary = archive.with_suffix('.zip.tmp')
    try:
        with zipfile.ZipFile(temporary, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as package:
            for name in paths:
                source = ROOT / name
                if not stat.S_ISREG(source.lstat().st_mode) or source.is_symlink():
                    raise RuntimeError(f'Non-regular tracked file: {name}')
                info = zipfile.ZipInfo('P3/' + name, (1980, 1, 1, 0, 0, 0))
                info.create_system = 3
                info.compress_type = zipfile.ZIP_DEFLATED
                info.external_attr = 0o100644 << 16
                package.writestr(info, source.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        with zipfile.ZipFile(temporary) as package:
            if package.testzip() is not None:
                raise RuntimeError('Corrupt ZIP')
            if package.read('P3/VERSION').decode().strip() != version:
                raise RuntimeError('Wrong archived VERSION')
        os.replace(temporary, archive)
    finally:
        temporary.unlink(missing_ok=True)
    checksum = output / f'P3-{version}.sha256'
    checksum.write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  ' + archive.name + '\n')
    print(f'{archive.name}: {len(paths)} files, {archive.stat().st_size} bytes')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--exclude-module', action='append', default=[])
    build(parser.parse_args().exclude_module)
