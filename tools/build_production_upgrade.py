#!/usr/bin/env python3
"""Build an FTP upgrade against an explicitly supplied production snapshot."""
import argparse,hashlib,json,subprocess,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent
PROTECTED=('database/','sessions/','tmp/','var/sys/','var/uploads/','var/private/','var/cache/','var/templates/','var/modules/search/data/')
SKIP=('.github/','patches/','distribution/','dist/','var/modules/home/')
def build(source,output):
    source=source.resolve();output=output.resolve();output.mkdir(parents=True,exist_ok=True)
    if (source/'VERSION').read_text().strip()!='0.8.01-beta1-preview5':raise RuntimeError('Unexpected production version')
    target=(ROOT/'VERSION').read_text().strip()
    tracked=subprocess.check_output(['git','ls-files','-z'],cwd=ROOT).decode().split('\0')
    manifest={'from':'0.8.01-beta1-preview5','to':target,'files':{}}
    for name in sorted(tracked):
        if not name or name.startswith(PROTECTED+SKIP) or name in {'.htaccess','web.config','robots.txt','favicon.ico','install.php','AGENTS.md','.gitignore','.gitattributes'}:continue
        f=ROOT/name
        if not f.is_file() or f.is_symlink():raise RuntimeError('Unexpected file '+name)
        before=source/name;data=f.read_bytes()
        if before.is_file() and before.read_bytes().replace(b'\r\n',b'\n')==data.replace(b'\r\n',b'\n'):continue
        dest=output/'2-fichiers'/name;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(data)
        manifest['files'][name]={'before':hashlib.sha256(before.read_bytes()).hexdigest() if before.is_file() else None,'after':hashlib.sha256(data).hexdigest()}
    lock=output/'1-maintenance/var/sys/maintenance.lock';lock.parent.mkdir(parents=True,exist_ok=True);lock.write_text('Mise à jour P3 '+target+'\n')
    state=output/'3-version/var/sys/release.json';state.parent.mkdir(parents=True,exist_ok=True);state.write_text(json.dumps({'version':target,'operation':'update-from-preview5'},indent=2)+'\n')
    (output/'MANIFEST.json').write_text(json.dumps(manifest,indent=2)+'\n')
    print(json.dumps({'files':len(manifest['files']),'output':str(output),'target':target}))
if __name__=='__main__':
    p=argparse.ArgumentParser();p.add_argument('production',type=Path);p.add_argument('output',type=Path);a=p.parse_args();build(a.production,a.output)
