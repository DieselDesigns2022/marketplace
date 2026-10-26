<?php

require dirname(__DIR__).'/app/bootstrap.php';

use App\Services\BattleService;

$fail=[];

$check=function(
    bool $ok,
    string $name
)use(&$fail):void{
    echo ($ok?'PASS: ':'FAIL: ').$name.PHP_EOL;

    if(!$ok){
        $fail[]=$name;
    }
};

$check(
    BattleService::CHAMPION_AD_CREDIT==='100.00',
    'Battle champion prize is exactly $100 Advertising Credit'
);

$check(
    BattleService::bracketSize(2)===2 &&
    BattleService::bracketSize(3)===4 &&
    BattleService::bracketSize(5)===8 &&
    BattleService::bracketSize(17)===32,
    'Battle bracket supports non-power-of-two participant counts'
);

$a=BattleService::deterministicSlots(
    [1,2,3,4,5,6,7],
    'fixed-seed-for-test'
);

$b=BattleService::deterministicSlots(
    [1,2,3,4,5,6,7],
    'fixed-seed-for-test'
);

$check(
    $a===$b,
    'Battle seeding is reproducible from its stored seed'
);

$check(
    count($a)===8 &&
    count(array_filter(
        $a,
        static fn($v)=>$v===null
    ))===1,
    'Battle bracket creates safe bye slots'
);

$service=file_get_contents(
    dirname(__DIR__).'/app/Services/BattleService.php'
);

$migration=file_get_contents(
    dirname(__DIR__).
    '/database/migrations/2026_10_26_battle_of_designers.sql'
);

$check(
    str_contains(
        $migration,
        'UNIQUE KEY battle_vote_one_per_matchup_uq'
    ),
    'one vote per account is enforced per matchup'
);

$check(
    str_contains(
        $service,
        'Your Battle logo is required.'
    ) &&
    str_contains(
        $service,
        "public_path(\n            'uploads/battles/logos'"
    ),
    'seller Battle signup requires a dedicated uploaded logo'
);

$check(
    str_contains(
        $service,
        'Set the Battle voting duration before opening a round.'
    ) &&
    str_contains(
        $migration,
        'voting_minutes INT UNSIGNED NULL'
    ),
    'Battle voting duration has no invented default'
);

$check(
    str_contains(
        $service,
        'set status="tied"'
    ) &&
    str_contains(
        $service,
        'Only a tied matchup can be manually resolved.'
    ),
    'Battle ties stop advancement instead of inventing a tie-break rule'
);

$check(
    str_contains(
        $service,
        'AdvertisingCreditService'
    ) &&
    str_contains(
        $service,
        ':champion:advertising-credit:100'
    ) &&
    !str_contains(
        $service,
        'PrizeCreditService'
    ) &&
    !str_contains(
        $service,
        'PrizeDonationService'
    ),
    'Battle reward uses only the locked $100 Advertising Credit system'
);

$check(
    str_contains(
        $migration,
        'battle_vote_attempts'
    ) &&
    str_contains(
        $migration,
        'battle_vote_flags'
    ) &&
    str_contains(
        $service,
        'flagSuspiciousAcceptedBurst'
    ),
    'Battle voting preserves anti-abuse audit evidence and flags suspicious bursts'
);



foreach([3,5,6,9,10,17] as $participantCount){
    $ids=range(1,$participantCount);

    $slots=BattleService::deterministicSlots(
        $ids,
        'multi-bye-test-'.$participantCount
    );

    $safe=true;

    for($i=0;$i<count($slots);$i+=2){
        if(
            $slots[$i]===null &&
            $slots[$i+1]===null
        ){
            $safe=false;
            break;
        }
    }

    $check(
        $safe,
        'Battle '.$participantCount.
        '-seller bracket never creates bye vs bye'
    );
}

$check(
    str_contains(
        $service,
        "'fingerprint_burst'"
    ) &&
    str_contains(
        $service,
        'fingerprint_hash=?'
    ),
    'Battle reviews browser-pattern fingerprints as anti-abuse evidence'
);




$voteStart=strpos(
    $service,
    'public function vote('
);

$voteEnd=strpos(
    $service,
    'public function closeRound(',
    $voteStart
);

$voteMethod=substr(
    $service,
    $voteStart,
    $voteEnd-$voteStart
);

$check(
    substr_count(
        $voteMethod,
        "DB::commit();"
    )>=5 &&
    str_contains(
        $voteMethod,
        "'rate_limited'"
    ) &&
    str_contains(
        $voteMethod,
        "'closed'"
    ) &&
    str_contains(
        $voteMethod,
        "'invalid_choice'"
    ) &&
    str_contains(
        $voteMethod,
        "'duplicate'"
    ),
    'rejected Battle vote attempts are committed for audit review'
);


exit($fail?1:0);
