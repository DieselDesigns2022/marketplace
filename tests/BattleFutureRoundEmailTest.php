<?php

require __DIR__.'/helpers/GrandOpeningPricingBootstrap.php';

use App\Core\Database as DB;
use App\Services\BattleService as Battle;

$checks=0;

$check=static function(
    bool $ok,
    string $name
)use(&$checks): void {
    if(!$ok){
        throw new RuntimeException($name);
    }

    ++$checks;
};

$fixture=static function(
    string $table,
    array $fields
): void {
    if(
        !preg_match(
            '/^[a-z_]+$/D',
            $table
        )
    ){
        throw new RuntimeException(
            'Invalid fixture table.'
        );
    }

    foreach(
        DB::rows(
            'show columns from `'.$table.'`'
        )
        as $column
    ){
        $name=$column['Field'];

        if(
            array_key_exists(
                $name,
                $fields
            ) ||
            $column['Null']==='YES' ||
            $column['Default']!==null ||
            str_contains(
                $column['Extra'],
                'auto_increment'
            ) ||
            str_contains(
                $column['Extra'],
                'GENERATED'
            )
        ){
            continue;
        }

        $type=$column['Type'];

        if(str_starts_with($type,'enum(')){
            preg_match(
                "/^enum\\('((?:[^'\\\\]|\\\\.)*)'/",
                $type,
                $match
            );

            $fields[$name]=
                stripslashes(
                    $match[1]
                );

        }elseif(
            preg_match(
                '/int|decimal|float|double|bit/',
                $type
            )
        ){
            $fields[$name]=0;

        }elseif(
            preg_match(
                '/date|time|year/',
                $type
            )
        ){
            $fields[$name]=
                '2026-01-01 00:00:00';

        }elseif($type==='json'){
            $fields[$name]='{}';

        }else{
            $fields[$name]='fixture';
        }
    }

    $columns=implode(
        ',',
        array_map(
            static fn(string $column): string =>
                '`'.$column.'`',
            array_keys($fields)
        )
    );

    DB::exec(
        'insert into `'.$table.'`('.
        $columns.
        ') values('.
        implode(
            ',',
            array_fill(
                0,
                count($fields),
                '?'
            )
        ).
        ')',
        array_values($fields)
    );
};


/*
 * ---------------------------------------------------------
 * 8 sellers = 3 full rounds:
 *
 * Round 1: 4 sequential matchups
 * Round 2: 2 sequential matchups
 * Round 3: championship
 * ---------------------------------------------------------
 */

$fixture(
    'users',
    [
        'id'=>1,
        'name'=>'Admin Fixture',
        'email'=>'admin@example.invalid',
        'role'=>'admin',
        'status'=>'active',
    ]
);

for($i=2;$i<=9;$i++){
    $fixture(
        'users',
        [
            'id'=>$i,
            'name'=>'Seller '.$i,
            'email'=>'seller'.$i.'@example.invalid',
            'role'=>'designer',
            'status'=>'active',
            'referral_code'=>'BATTLE'.$i,
        ]
    );

    $fixture(
        'designers',
        [
            'id'=>$i,
            'user_id'=>$i,
            'display_name'=>'Shop '.$i,
            'store_slug'=>'shop-'.$i,
            'status'=>'approved',
        ]
    );
}

$firstOpen=
    (
        new DateTimeImmutable(
            'now',
            new DateTimeZone(
                'America/Detroit'
            )
        )
    )
    ->modify('+2 days')
    ->setTime(12,0)
    ->setTimezone(
        new DateTimeZone('UTC')
    )
    ->format('Y-m-d H:i:s');

$fixture(
    'battle_events',
    [
        'id'=>1,
        'title'=>'Future Round Email Test',
        'slug'=>'future-round-email-test',
        'status'=>'signup',
        'created_by_admin_id'=>1,
        'signup_closes_at'=>
            gmdate(
                'Y-m-d H:i:s',
                time()-1
            ),
        'first_round_opens_at'=>
            $firstOpen,
    ]
);

for($i=2;$i<=9;$i++){
    $fixture(
        'battle_participants',
        [
            'id'=>$i,
            'battle_id'=>1,
            'designer_id'=>$i,
            'logo_path'=>'fixture/logo.png',
            'status'=>'active',
        ]
    );
}

$service=new Battle();

$result=$service->processSchedule();

$check(
    !$result['errors'],
    'Round 1 schedules successfully'
);

$event=DB::row(
    'select *
       from battle_events
      where id=1'
);

$check(
    (int)$event['total_rounds']===3,
    'Eight sellers create three Battle rounds'
);


/*
 * ---------------------------------------------------------
 * ROUND 1
 * ---------------------------------------------------------
 */

$roundOneMatchups=DB::rows(
    'select *
       from battle_matchups
      where battle_id=1
        and round_number=1
      order by bracket_position'
);

$check(
    count($roundOneMatchups)===4,
    'Round 1 contains four matchups'
);

$roundOneEmails=DB::rows(
    'select *
       from email_messages
      order by id'
);

$check(
    count($roundOneEmails)===24,
    'Round 1 queues three emails for both designers in all four matchups'
);


/*
 * Give every Round 1 matchup a winner.
 */

DB::exec(
    'update battle_matchups
        set status="completed",
            winner_participant_id=
                participant_one_id
      where battle_id=1
        and round_number=1'
);

$finish=
    new ReflectionMethod(
        Battle::class,
        'completeRound'
    );

$finish->setAccessible(true);

$finish->invoke(
    $service,
    1,
    1,
    1
);


/*
 * ---------------------------------------------------------
 * ROUND 2
 * ---------------------------------------------------------
 */

$roundTwoMatchups=DB::rows(
    'select *
       from battle_matchups
      where battle_id=1
        and round_number=2
      order by bracket_position'
);

$check(
    count($roundTwoMatchups)===2,
    'Round 2 contains two winner matchups'
);

$roundTwoEmails=DB::rows(
    'select *
       from email_messages
      where json_unquote(
                json_extract(
                    template_data,
                    "$.round_number"
                )
            )="2"
      order by id'
);

$check(
    count($roundTwoEmails)===12,
    'Round 2 automatically queues twelve matchup emails'
);

$roundTwoSubjects=
    array_count_values(
        array_column(
            $roundTwoEmails,
            'subject'
        )
    );

$check(
    ($roundTwoSubjects[
        'Your Battle time is almost here!'
    ] ?? 0)===4,
    'Round 2 queues four 12-hour warnings'
);

$check(
    ($roundTwoSubjects[
        'Your Battle is LIVE!'
    ] ?? 0)===4,
    'Round 2 queues four live emails'
);

$check(
    ($roundTwoSubjects[
        '1 hour left in your Battle!'
    ] ?? 0)===4,
    'Round 2 queues four final-hour emails'
);


/*
 * Every Round 2 email must use one of the actual
 * Round 2 matchup windows.
 */

$roundTwoWindows=[];

foreach($roundTwoMatchups as $matchup){
    $roundTwoWindows[]=
        Battle::eastern(
            $matchup['voting_opens_at']
        ).
        ' — '.
        Battle::eastern(
            $matchup['voting_closes_at']
        );
}

foreach($roundTwoEmails as $email){
    $data=json_decode(
        $email['template_data'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $check(
        in_array(
            $data['voting_window'],
            $roundTwoWindows,
            true
        ),
        'Round 2 email uses its real matchup voting window'
    );
}


/*
 * Give every Round 2 matchup a winner.
 */

DB::exec(
    'update battle_matchups
        set status="completed",
            winner_participant_id=
                participant_one_id
      where battle_id=1
        and round_number=2'
);

$finish->invoke(
    $service,
    1,
    2,
    1
);


/*
 * ---------------------------------------------------------
 * ROUND 3 / CHAMPIONSHIP
 * ---------------------------------------------------------
 */

$championship=DB::rows(
    'select *
       from battle_matchups
      where battle_id=1
        and round_number=3
      order by bracket_position'
);

$check(
    count($championship)===1,
    'Round 3 contains one championship matchup'
);

$championshipEmails=DB::rows(
    'select *
       from email_messages
      where json_unquote(
                json_extract(
                    template_data,
                    "$.round_number"
                )
            )="3"
      order by id'
);

$check(
    count($championshipEmails)===6,
    'Championship automatically queues six emails'
);

$championshipSubjects=
    array_count_values(
        array_column(
            $championshipEmails,
            'subject'
        )
    );

$check(
    ($championshipSubjects[
        'Your Battle time is almost here!'
    ] ?? 0)===2,
    'Championship queues two 12-hour warnings'
);

$check(
    ($championshipSubjects[
        'Your Battle is LIVE!'
    ] ?? 0)===2,
    'Championship queues two live emails'
);

$check(
    ($championshipSubjects[
        '1 hour left in your Battle!'
    ] ?? 0)===2,
    'Championship queues two final-hour emails'
);

$championshipWindow=
    Battle::eastern(
        $championship[0][
            'voting_opens_at'
        ]
    ).
    ' — '.
    Battle::eastern(
        $championship[0][
            'voting_closes_at'
        ]
    );

foreach($championshipEmails as $email){
    $data=json_decode(
        $email['template_data'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $check(
        $data['voting_window']===
            $championshipWindow,
        'Championship email uses the championship voting window'
    );
}


/*
 * ---------------------------------------------------------
 * DEDUPE CHECK
 *
 * Re-run the championship email scheduler directly.
 * The unique deduplication keys must prevent duplicates.
 * ---------------------------------------------------------
 */

$before=(int)(
    DB::row(
        'select count(*) n
           from email_messages'
    )['n'] ?? 0
);

$queueEmails=
    new ReflectionMethod(
        Battle::class,
        'queueRoundEmails'
    );

$queueEmails->setAccessible(true);

$roundThree=
    DB::row(
        'select *
           from battle_rounds
          where battle_id=1
            and round_number=3'
    );

$queueEmails->invoke(
    $service,
    1,
    3,
    $roundThree['voting_opens_at'],
    $roundThree['voting_closes_at']
);

$after=(int)(
    DB::row(
        'select count(*) n
           from email_messages'
    )['n'] ?? 0
);

$check(
    $before===$after,
    'Future-round email scheduling is idempotent'
);

echo
    "PASS: ".
    $checks.
    " future-round Battle email checks\n";
