TARGET_NAMES=['automatic-launch-20261028-003734-902143', 'automatic-launch-run-sVjl30', 'battle-calendar-20261027-234656-085296', 'battle-calendar-recovery-20261028-001554-422950', 'battle-calendar-run-j4hkw5', 'battle-calendar-ties-20261028-001228-239604', 'battle-calendar-ties-run-MIEtjl', 'battle-recovery-run-bEZDyi', 'final-wheel-run-YglEGl', 'launch-recovery-run-SRmHOS', 'launch-regression-20261027-232915-169740', 'referral-anchor-20261027-195815-352505', 'restore-referral-rules-20261027-194033-779469', 'shared-wheel-retry-NRryDy', 'shared-wheel-run-gugFgc', 'social-menu-test-20261027-233146-315293', 'step10-display-fix-20261027-231450-775214', 'step10-final-cleanup-20261027-231746-981100', 'step10-final-wheel-20261027-203308-906575', 'step10-labeled-wheel-20261027-221354-780885', 'step10-shared-rehearsal-20261027-204753-667714', 'step10-shared-rehearsal-20261027-205135-064952', 'step7-admin-menu-20261027-174818-245073', 'step7-controls-20261027-164352-905748', 'step7-controls-20261027-165225-921992', 'step7-controls-fix-20261027-172843-891435', 'step7-homepage-preview-20261027-182042-954013', 'step7-pricing-20261027-181256-077278', 'step7-run-sUvz5H', 'step8-graphics-users-20261027-184707-674701', 'step8-graphics-users-run-Ikg4Hk', 'step8-planner-instructions-20261027-190537-564216', 'step8-queue-cleanup-20261027-190335-874970', 'step8-run-ebamJp', 'step8-social-content-20261027-183924-919980', 'step9-counting-run-5kgQGP', 'step9-original-referral-counting-20261027-194837-131959', 'step9-referral-giveaway-20261027-193415-810802', 'step9-run-0x6Wj8', 'wheel-cleanup-run-hMWwKe', 'wheel-controls-run-MvYc8R', 'wheel-display-fix-DVtI1Q', 'wheel-display-run-R0TbjL']

from pathlib import Path
import os,shutil,subprocess,sys
sys.excepthook=sys.__excepthook__
ROOT=Path('/var/www/marketplace.dieseldesigns.co')
def run(args,**kw):return subprocess.run(args,check=True,**kw)
def output(args):return subprocess.check_output(args,text=True).strip()
def planned_paths(root):
    backups=root/'backups'
    if backups.is_symlink() or not backups.is_dir():raise RuntimeError('Expected a real backups directory.')
    latest=backups/'automatic-launch-20261028-003924-638639'
    cron=backups/'email-worker-cron-20261027-232644-173126'
    for p in [latest,cron]:
        if p.is_symlink() or not p.is_dir():raise RuntimeError('Protected rollback copy missing: '+str(p))
    dump=latest/'settings-before.sql'
    if dump.is_symlink() or not dump.is_file() or dump.stat().st_size==0:raise RuntimeError('Protected settings backup is missing or empty.')
    if not (latest/'original').is_dir():raise RuntimeError('Protected original files missing.')
    paths=[backups/name for name in TARGET_NAMES]+[latest/'candidate',latest/'configure.php']
    for p in paths:
        if p.is_symlink():raise RuntimeError('Unexpected symlink: '+str(p))
        if not p.exists():continue
        if not p.resolve().is_relative_to(backups.resolve()):raise RuntimeError('Path escapes backups.')
        if os.path.ismount(p):raise RuntimeError('Unexpected mount: '+str(p))
        if p.is_dir():
            for directory,children,_ in os.walk(p,followlinks=False):
                for child in children:
                    if os.path.ismount(Path(directory)/child):raise RuntimeError('Nested mount in cleanup target.')
        elif p.name!='configure.php':raise RuntimeError('Expected directory: '+str(p))
    return paths

def main():
    os.chdir(ROOT)
    if output(['git','branch','--show-current'])!='main' or output(['git','status','--porcelain']):raise RuntimeError('Expected clean main.')
    if not output(['git','rev-parse','HEAD']).startswith('836cdcc'):raise RuntimeError('Version changed; inspect before cleanup.')
    run(['git','fetch','origin','main'])
    if output(['git','rev-parse','HEAD'])!=output(['git','rev-parse','origin/main']):raise RuntimeError('Local and GitHub versions differ.')
    paths=planned_paths(ROOT)
    tracked=output(['git','ls-files']).splitlines()
    for p in paths:
        relative=str(p.relative_to(ROOT))
        if any(n==relative or n.startswith(relative+'/') for n in tracked):raise RuntimeError('Cleanup target contains a tracked file: '+relative)
    cron=output(['crontab','-l'])
    cron_sources=[cron]+[p.read_text() for p in Path('/etc/cron.d').iterdir() if p.is_file()]
    for p in paths:
        if any(str(p) in source for source in cron_sources):raise RuntimeError('A scheduled job references a cleanup target: '+str(p))
    print('=== REMAINING CHECKLIST AUDIT — READ ONLY ===',flush=True)
    run(['php'],input=r"""<?php
require '/var/www/marketplace.dieseldesigns.co/app/bootstrap.php';
use App\Core\Database as DB;
echo "\nRECURRING ADVERTISING CREDIT GRANTS\n";
echo json_encode(DB::rows('select g.id,g.user_id,u.name recipient,g.amount,g.frequency,g.day_of_month,g.next_run_at,g.is_active from advertising_credit_recurring_grants g join users u on u.id=g.user_id order by g.id'),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
foreach(['advertising_credits','advertising_credit_transactions','advertising_credit_recurring_grants'] as $table){
    echo "\nSCHEMA: ",$table,"\n";
    echo json_encode(DB::rows('SHOW CREATE TABLE '.$table),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
}
""",text=True)
    removed=0
    for p in paths:
        if not p.exists():continue
        if p.is_dir():shutil.rmtree(p)
        else:p.unlink()
        removed+=1
        print('REMOVED:',p.relative_to(ROOT),flush=True)
    if any(p.exists() or p.is_symlink() for p in paths):raise RuntimeError('Cleanup verification failed.')
    if output(['git','status','--porcelain']):raise RuntimeError('Working tree changed during cleanup; inspect.')
    print('CLEANUP COMPLETE:',removed,'temporary backup/staging items removed.')
    print('KEPT: latest settings/original-file rollback and email-worker cron rollback.')
    print('Older October 19 backups were outside this cleanup and remain untouched.')
    print('GITHUB:',output(['git','log','-1','--oneline']))
    print('WORKING TREE: Clean. No live code, event settings or customer records changed.')
if __name__=='__main__':main()
