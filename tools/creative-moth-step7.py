#!/usr/bin/env python3
PAYLOAD = (
    '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'
    'zq1Cf0Nb+4DxFwlDzq5+/78I0/VRMssAAA=='
)
from pathlib import Path
from datetime import datetime, timezone
import base64, gzip, json, hashlib, subprocess, shutil, secrets, re, os, sys, difflib

ROOT=Path('/var/www/marketplace.dieseldesigns.co')

def run(args, **kwargs):
    return subprocess.run(args,check=True,**kwargs)

def note(value):
    print(value,flush=True)

def main():
    os.chdir(ROOT)
    if subprocess.check_output(['git','status','--porcelain'],text=True).strip():
        raise RuntimeError('Working tree is not clean. No site files changed.')
    head=subprocess.check_output(['git','rev-parse','--short','HEAD'],text=True).strip()
    if head!='e23a3ac':
        raise RuntimeError('Expected inspected commit e23a3ac. No site files changed.')
    manifest=json.loads(gzip.decompress(base64.b64decode(PAYLOAD)))
    originals={}; candidates={}
    for name,change in manifest.items():
        path=Path(name)
        if path.is_absolute() or '..' in path.parts:
            raise RuntimeError('Invalid patch path.')
        if change['before'] is None:
            if path.exists(): raise RuntimeError('Unexpected existing file: '+name)
            data=change['content'].encode()
        else:
            original=path.read_bytes()
            if hashlib.sha256(original).hexdigest()!=change['before']:
                raise RuntimeError('File differs from inspected source: '+name)
            originals[name]=original
            lines=original.decode().splitlines(keepends=True)
            for start,end,value in reversed(change['changes']):
                lines[start:end]=value.splitlines(keepends=True)
            data=''.join(lines).encode()
        if hashlib.sha256(data).hexdigest()!=change['after']:
            raise RuntimeError('Candidate verification failed: '+name)
        candidates[name]=data
    backup=ROOT/'backups'/('step7-pricing-'+datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S-%f'))
    stage=backup/'candidate'
    stage.mkdir(parents=True)
    note('BACKUP / STAGING: '+str(backup))
    tracked=subprocess.check_output(['git','ls-files','-z'],text=True).split('\0')
    for name in tracked:
        path=Path(name)
        if name and path.suffix in ('.php','.sql') and path.parts[0] in ('app','public','tests','database'):
            target=stage/name;target.parent.mkdir(parents=True,exist_ok=True)
            shutil.copy2(path,target)
    for name,data in candidates.items():
        target=stage/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
    note('STAGED PHP SYNTAX CHECKS')
    for name in candidates:
        if name.endswith('.php'):run(['php','-l',str(stage/name)])
    run(['php',str(stage/'tests/GrandOpeningSaleControlsTest.php')])
    env_text=Path('.env').read_text()
    match=re.search(r'(?m)^\s*(?:export\s+)?DB_NAME\s*=\s*(.*?)\s*$',env_text)
    if not match:raise RuntimeError('DB_NAME not found.')
    raw=match.group(1).strip()
    if raw.startswith(('"',"'")):
        closing=raw.find(raw[0],1)
        if closing<1:raise RuntimeError('DB_NAME quoting needs verification.')
        db=raw[1:closing]
    else:db=raw.split('#',1)[0].strip()
    if not re.fullmatch(r'[A-Za-z0-9_]+',db):raise RuntimeError('Invalid DB_NAME.')
    mysql=['mysql','--protocol=socket','-u','root','--batch','--skip-column-names']
    socket=subprocess.check_output(mysql+['-e','SELECT @@socket;'],text=True).strip()
    if not socket or ';' in socket or '\n' in socket:raise RuntimeError('MySQL socket needs verification.')
    tables=subprocess.check_output(mysql+[db,'-e',"SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE';"],text=True).splitlines()
    if 'grand_opening_pricing_snapshots' in tables:raise RuntimeError('Pricing snapshot table already exists.')
    enabled=subprocess.check_output(mysql+[db,'-e','SELECT is_enabled FROM grand_opening_events WHERE id=1;'],text=True).strip()
    if enabled!='0':raise RuntimeError('Event must remain disabled during installation.')
    migration=candidates['database/migrations/2026_10_27_grand_opening_pricing_snapshots.sql'].decode()
    for suite in ('pricing','admin'):
        test_db='cm_sale_test_'+secrets.token_hex(10)
        created=False
        try:
            note('ISOLATED '+suite.upper()+' TESTS — no live customer records copied')
            run(mysql+['-e','CREATE DATABASE `'+test_db+'` CHARACTER SET utf8mb4;'])
            created=True
            env=os.environ.copy();env['CM_SALE_TEST_DSN']='mysql:unix_socket='+socket+';dbname='+test_db
            if suite=='pricing':
                statements=[]
                for table in tables:
                    if not re.fullmatch(r'[A-Za-z0-9_]+',table):raise RuntimeError('Invalid table identifier.')
                    statements.append('CREATE TABLE `'+test_db+'`.`'+table+'` LIKE `'+db+'`.`'+table+'`;')
                run(mysql,input='\n'.join(statements),text=True)
                run(mysql+[test_db],input=migration,text=True)
                run(['php',str(stage/'tests/GrandOpeningPricingIntegrationTest.php')],env=env)
            else:
                run(mysql+[test_db],input=(stage/'database/migrations/2026_10_27_grand_opening_sale_controls.sql').read_text(),text=True)
                run(['php',str(stage/'tests/GrandOpeningSaleAdminTest.php'),str(stage),str(stage/'app/Views/admin/grand_opening.php')],env=env)
        finally:
            if created:run(mysql+['-e','DROP DATABASE `'+test_db+'`;'])
    note('ALL PRE-INSTALL CHECKS PASSED — backing up changed files')
    for name,original in originals.items():
        if Path(name).read_bytes()!=original:raise RuntimeError('Source changed during tests: '+name)
        target=backup/'original'/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(name,target)
    with (backup/'before-schema.sql').open('wb') as output:
        run(['mysqldump','--protocol=socket','-u','root','--no-data',db],stdout=output)
    review=[]
    for name,data in candidates.items():
        before=originals.get(name,b'').decode().splitlines(keepends=True)
        review.extend(difflib.unified_diff(before,data.decode().splitlines(keepends=True),fromfile='a/'+name,tofile='b/'+name))
    (backup/'review.diff').write_text(''.join(review))
    installed=[]
    try:
        run(mysql+[db],input=migration,text=True)
        late=['app/Controllers/PublicController.php','app/Controllers/CartController.php','app/Services/GrandOpeningSaleService.php','app/Controllers/GrandOpeningSaleController.php']
        order=[n for n in candidates if n not in late]+late
        for name in order:
            if name in originals and Path(name).read_bytes()!=originals[name]:raise RuntimeError('Source changed before installation: '+name)
            if name not in originals and Path(name).exists():raise RuntimeError('File appeared before installation: '+name)
            path=Path(name);path.parent.mkdir(parents=True,exist_ok=True)
            installed.append(name);shutil.copy2(stage/name,path)
        run(['git','diff','--check'])
        run(['php','tests/GrandOpeningSaleControlsTest.php'])
        for name,data in candidates.items():
            if Path(name).read_bytes()!=data:raise RuntimeError('Installed candidate mismatch: '+name)
    except Exception:
        for name in reversed(installed):
            original=backup/'original'/name
            if original.exists():shutil.copy2(original,name)
            else:Path(name).unlink(missing_ok=True)
        note('Site files restored. New snapshot table retained for inspection.')
        raise
    note('INSTALLED: pricing, highest-discount selection, checkout snapshots, sale browsing and homepage.')
    note('The event remains disabled. Review the pages before enabling it.')
    note('BACKUP: '+str(backup))
    note('REVIEW DIFF: '+str(backup/'review.diff'))
    run(['git','diff','--stat'])
    run(['git','status','--short'])
    note('RESULT=0')

if __name__=='__main__':
    try:main()
    except Exception as error:
        print('STOPPED: '+str(error),file=sys.stderr,flush=True)
        print('RESULT=1',file=sys.stderr,flush=True)
        sys.exit(1)
