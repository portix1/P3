#!/usr/bin/env python3
"""Check packaging boundaries on a temporary repository containing fake private data."""
import contextlib
import io
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch
import zipfile

import build_distribution


class DistributionTests(unittest.TestCase):
    def test_runtime_data_excluded_and_application_included(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            files = {
                'VERSION': '0.8.3-rc4\n', 'LICENSE': 'license', 'index.php': '<?php',
                'install.php': '<?php', '.htaccess': 'routing', 'bootstrap/app.php': '<?php',
                'src/Maintenance/UpdateSafety.php': '<?php',
                'src/Maintenance/core-release-public.pem': 'official public fixture',
                'src/Maintenance/accidental-private.pem': 'fake private key',
                'var/modules/safety/templates/rollback.tpl': '<form></form>',
                'var/modules/articles/database/schema.sql': 'CREATE TABLE example (id INTEGER);',
                'database/.htaccess': 'Require all denied',
                'sessions/.htaccess': 'Require all denied',
                'tmp/.htaccess': 'Require all denied',
                'var/sys/.htaccess': 'Require all denied',
                'var/uploads/images/.htaccess': 'Options -Indexes',
                'database/live.sqlite': 'fake private database',
                'var/sys/config.json': 'fake private configuration',
                'var/sys/core-release-public.pem': 'local trust anchor not shipped',
                'var/private/.htaccess': 'Require all denied',
                'PREPARATION-P3-0.9.2.md': 'obsolete preparation',
                'var/private/backup/manifest.json': 'fake backup',
                'var/uploads/images/private.webp': 'fake media',
                'sessions/sess_example': 'fake session', 'tmp/example.txt': 'fake runtime data',
                '.github/workflows/ci.yml': 'workflow', 'patches/installer.php': '<?php',
                'distribution/old.zip': 'old archive',
            }
            for name, content in files.items():
                target = root / name
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_text(content)
            subprocess.run(['git', 'init', '-q', str(root)], check=True, capture_output=True)
            subprocess.run(['git', '-C', str(root), 'add', '.'], check=True, capture_output=True)
            with patch.object(build_distribution, 'ROOT', root), contextlib.redirect_stdout(io.StringIO()):
                build_distribution.build()
            archive = root / 'dist/P3-0.8.3-rc4.zip'
            first_bytes = archive.read_bytes()
            os.utime(root / 'index.php', (1700000000, 1700000000))
            with patch.object(build_distribution, 'ROOT', root), contextlib.redirect_stdout(io.StringIO()):
                build_distribution.build()
            self.assertEqual(archive.read_bytes(), first_bytes, 'Distribution ZIP depends on file timestamps')
            with zipfile.ZipFile(archive) as package:
                expected = {'P3/' + name for name in files if build_distribution.include(name)}
                self.assertEqual(set(package.namelist()), expected)
                self.assertIn('P3/var/modules/articles/database/schema.sql', package.namelist())
                for protected in ('database', 'sessions', 'tmp', 'var/sys', 'var/private', 'var/uploads/images'):
                    self.assertIn('P3/' + protected + '/.htaccess', package.namelist())
                self.assertEqual(package.read('P3/var/private/.htaccess'), b'Require all denied')
                self.assertNotIn('P3/var/private/backup/manifest.json', package.namelist())
                self.assertNotIn('P3/PREPARATION-P3-0.9.2.md', package.namelist())
                self.assertNotIn('P3/database/live.sqlite', package.namelist())
                self.assertNotIn('P3/var/sys/core-release-public.pem', package.namelist())
                self.assertEqual(package.read('P3/src/Maintenance/core-release-public.pem'), b'official public fixture')
                self.assertNotIn('P3/src/Maintenance/accidental-private.pem', package.namelist())
            self.assertTrue((root / 'dist/P3-0.8.3-rc4.sha256').read_text().endswith('  P3-0.8.3-rc4.zip\n'))
            (root / 'var/private/.htaccess').unlink()
            subprocess.run(['git', '-C', str(root), 'add', '-u'], check=True, capture_output=True)
            with patch.object(build_distribution, 'ROOT', root), self.assertRaises(RuntimeError):
                build_distribution.build()
            (root / 'VERSION').write_text('../invalid')
            with patch.object(build_distribution, 'ROOT', root), self.assertRaises(RuntimeError):
                build_distribution.build()


if __name__ == '__main__':
    unittest.main()
