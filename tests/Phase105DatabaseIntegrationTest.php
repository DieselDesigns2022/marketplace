<?php
// Staging-only integration gate. The scenarios are documented in docs/TESTING.md.
if(getenv('PHASE105_RUN_DATABASE_TESTS')!=='1'){fwrite(STDOUT,"SKIP: set PHASE105_RUN_DATABASE_TESTS=1 with a disposable migrated MariaDB database.\n");exit(0);}
require dirname(__DIR__).'/app/bootstrap.php';
try{$pdo=\App\Core\Database::pdo();foreach(['notifications','email_preferences','waitlist_entries','email_campaigns','email_campaign_recipients','email_messages'] as $table)$pdo->query("select 1 from $table limit 1");echo "Phase 10.5 migrated database connectivity checks passed. Run the documented staging scenario matrix before release.\n";}catch(Throwable $e){fwrite(STDERR,"Database integration precondition failed: ".preg_replace('/[\r\n]+/',' ',$e->getMessage())."\n");exit(1);}
