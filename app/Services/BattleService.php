<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class BattleService
{
    public const CHAMPION_AD_CREDIT='100.00';
    public const ROUND_DURATION_HOURS=24;
    public const PRE_BATTLE_EMAIL_HOURS=12;
    public const FINAL_PUSH_EMAIL_HOURS=1;

    /*
     * These are technical abuse ceilings, not Battle scoring rules.
     * Legitimate votes are never silently deleted because of these signals.
     */
    private const USER_ATTEMPT_LIMIT_PER_MINUTE=10;
    private const IP_ATTEMPT_LIMIT_PER_MINUTE=60;
    private const SUSPICIOUS_ACCEPTED_IP_5_MINUTES=25;
    private const SUSPICIOUS_DISTINCT_USERS_5_MINUTES=10;

    private function atomic(callable $callback): mixed
    {
        $own=!DB::pdo()->inTransaction();

        if($own){
            DB::begin();
        }

        try{
            $result=$callback();

            if($own){
                DB::commit();
            }

            return $result;
        }catch(Throwable $error){
            if($own && DB::pdo()->inTransaction()){
                DB::rollBack();
            }

            throw $error;
        }
    }

    public static function bracketSize(int $participants): int
    {
        if($participants<2){
            throw new InvalidArgumentException(
                'At least two Battle participants are required.'
            );
        }

        $size=1;

        while($size<$participants){
            $size*=2;
        }

        return $size;
    }

    public static function deterministicSlots(
        array $participantIds,
        string $seed
    ): array {
        $participantIds=array_values(
            array_map('intval',$participantIds)
        );

        $size=self::bracketSize(
            count($participantIds)
        );

        usort(
            $participantIds,
            static function(int $a,int $b)use($seed):int{
                $ah=hash(
                    'sha256',
                    $seed.'|participant:'.$a
                );

                $bh=hash(
                    'sha256',
                    $seed.'|participant:'.$b
                );

                $cmp=strcmp($ah,$bh);

                return $cmp!==0
                    ? $cmp
                    : $a<=>$b;
            }
        );

        $matchCount=(int)($size/2);
        $byeCount=$size-count($participantIds);

        $slots=[];
        $index=0;

        for(
            $match=0;
            $match<$matchCount;
            $match++
        ){
            if($match<$byeCount){
                $participant=$participantIds[$index++];

                $participantOnLeft=
                    (hexdec(substr(
                        hash(
                            'sha256',
                            $seed.'|bye-side:'.$match
                        ),
                        0,
                        2
                    )) % 2)===0;

                if($participantOnLeft){
                    $slots[]=$participant;
                    $slots[]=null;
                }else{
                    $slots[]=null;
                    $slots[]=$participant;
                }

                continue;
            }

            $slots[]=$participantIds[$index++];
            $slots[]=$participantIds[$index++];
        }

        return $slots;
    }

    private static function safeSlug(string $value): string
    {
        $value=mb_strtolower(trim($value));
        $value=preg_replace(
            '/[^a-z0-9]+/u',
            '-',
            $value
        ) ?? '';

        $value=trim($value,'-');

        if($value===''){
            throw new InvalidArgumentException(
                'Battle title must produce a valid URL slug.'
            );
        }

        return mb_substr($value,0,190);
    }

    public function createEvent(
        int $adminId,
        string $title
    ): int {
        $title=trim($title);

        if(
            mb_strlen($title)<3 ||
            mb_strlen($title)>190
        ){
            throw new InvalidArgumentException(
                'Battle title must be between 3 and 190 characters.'
            );
        }

        $slug=self::safeSlug($title);

        return $this->atomic(function()use(
            $adminId,
            $title,
            $slug
        ){
            if(!DB::row(
                'select id
                 from users
                 where id=?
                   and role="admin"
                   and status="active"
                 for update',
                [$adminId]
            )){
                throw new DomainException(
                    'An active admin is required.'
                );
            }

            if(DB::row(
                'select id
                 from battle_events
                 where slug=?
                 limit 1',
                [$slug]
            )){
                $slug.='-'.substr(
                    hash(
                        'sha256',
                        microtime(true).
                        random_bytes(8)
                    ),
                    0,
                    8
                );
            }

            DB::exec(
                'insert into battle_events
                 (
                    title,
                    slug,
                    created_by_admin_id
                 )
                 values (?,?,?)',
                [
                    $title,
                    $slug,
                    $adminId,
                ]
            );

            $id=(int)DB::id();

            $this->adminLog(
                $adminId,
                'created_battle',
                'battle_event',
                $id,
                [
                    'title'=>$title,
                    'round_duration_hours'=>
                        self::ROUND_DURATION_HOURS,
                ]
            );

            return $id;
        });
    }

    private function normalizeLogo(array $upload): array
    {
        $error=(int)(
            $upload['error'] ??
            UPLOAD_ERR_NO_FILE
        );

        if($error===UPLOAD_ERR_NO_FILE){
            throw new InvalidArgumentException(
                'Your Battle logo is required.'
            );
        }

        if($error!==UPLOAD_ERR_OK){
            throw new InvalidArgumentException(
                'Battle logo upload failed.'
            );
        }

        $tmp=(string)($upload['tmp_name']??'');
        $size=(int)($upload['size']??0);

        if(
            $tmp==='' ||
            !is_uploaded_file($tmp) ||
            $size<1 ||
            $size>10*1024*1024
        ){
            throw new InvalidArgumentException(
                'Battle logo must be a genuine image no larger than 10 MB.'
            );
        }

        $mime=(string)(
            (new \finfo(FILEINFO_MIME_TYPE))
                ->file($tmp) ?: ''
        );

        if(!in_array(
            $mime,
            [
                'image/jpeg',
                'image/png',
                'image/webp',
            ],
            true
        )){
            throw new InvalidArgumentException(
                'Battle logo must be JPG, PNG, or WEBP.'
            );
        }

        $bytes=@file_get_contents($tmp);

        $info=$bytes===false
            ? false
            : @getimagesizefromstring($bytes);

        if(
            !$info ||
            !hash_equals(
                $mime,
                (string)($info['mime']??'')
            ) ||
            !SellerReceiptService::sourceDimensionsAllowed(
                (int)$info[0],
                (int)$info[1]
            ) ||
            !SellerReceiptService::hasExactImageContainer(
                $bytes,
                $mime
            )
        ){
            throw new InvalidArgumentException(
                'Battle logo is malformed or unsafe.'
            );
        }

        if(
            !extension_loaded('gd') ||
            !function_exists('imagecreatefromstring')
        ){
            throw new DomainException(
                'Image processing is unavailable.'
            );
        }

        $source=@imagecreatefromstring($bytes);

        if(!$source){
            throw new InvalidArgumentException(
                'Battle logo could not be decoded.'
            );
        }

        $sourceWidth=imagesx($source);
        $sourceHeight=imagesy($source);

        if(
            $sourceWidth<1 ||
            $sourceHeight<1
        ){
            imagedestroy($source);

            throw new InvalidArgumentException(
                'Battle logo dimensions are invalid.'
            );
        }

        $canvas=imagecreatetruecolor(800,800);

        if(!$canvas){
            imagedestroy($source);

            throw new DomainException(
                'Battle logo canvas could not be created.'
            );
        }

        imagealphablending($canvas,false);
        imagesavealpha($canvas,true);

        $transparent=imagecolorallocatealpha(
            $canvas,
            0,
            0,
            0,
            127
        );

        imagefilledrectangle(
            $canvas,
            0,
            0,
            799,
            799,
            $transparent
        );

        $scale=min(
            720/$sourceWidth,
            720/$sourceHeight
        );

        $targetWidth=max(
            1,
            (int)round($sourceWidth*$scale)
        );

        $targetHeight=max(
            1,
            (int)round($sourceHeight*$scale)
        );

        $x=(int)floor(
            (800-$targetWidth)/2
        );

        $y=(int)floor(
            (800-$targetHeight)/2
        );

        if(!imagecopyresampled(
            $canvas,
            $source,
            $x,
            $y,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        )){
            imagedestroy($source);
            imagedestroy($canvas);

            throw new DomainException(
                'Battle logo could not be normalized.'
            );
        }

        $directory=public_path(
            'uploads/battles/logos'
        );

        if(
            !is_dir($directory) &&
            !mkdir($directory,0775,true) &&
            !is_dir($directory)
        ){
            imagedestroy($source);
            imagedestroy($canvas);

            throw new DomainException(
                'Battle logo storage is unavailable.'
            );
        }

        $name=bin2hex(random_bytes(24)).
            '.png';

        $absolute=$directory.'/'.$name;

        if(!imagepng($canvas,$absolute,6)){
            imagedestroy($source);
            imagedestroy($canvas);

            throw new DomainException(
                'Battle logo could not be stored.'
            );
        }

        imagedestroy($source);
        imagedestroy($canvas);

        @chmod($absolute,0644);

        return [
            'absolute'=>$absolute,
            'path'=>'/uploads/battles/logos/'.$name,
        ];
    }

    public function signup(
        int $battleId,
        int $userId,
        array $logoUpload
    ): int {
        $stored=$this->normalizeLogo(
            $logoUpload
        );

        try{
            return $this->atomic(function()use(
                $battleId,
                $userId,
                $stored
            ){
                $designer=DB::row(
                    'select d.*
                     from designers d
                     join users u
                       on u.id=d.user_id
                     where d.user_id=?
                       and d.status="approved"
                       and u.status="active"
                     for update',
                    [$userId]
                ) ?? throw new DomainException(
                    'Battle signup requires an active approved seller.'
                );

                $battle=DB::row(
                    'select *
                     from battle_events
                     where id=?
                     for update',
                    [$battleId]
                ) ?? throw new DomainException(
                    'Battle not found.'
                );

                if($battle['status']!=='signup'){
                    throw new DomainException(
                        'Battle signup is closed.'
                    );
                }

                if(DB::row(
                    'select id
                     from battle_participants
                     where battle_id=?
                       and designer_id=?
                     limit 1',
                    [
                        $battleId,
                        (int)$designer['id'],
                    ]
                )){
                    throw new DomainException(
                        'Your store is already registered for this Battle.'
                    );
                }

                DB::exec(
                    'insert into battle_participants
                     (
                        battle_id,
                        designer_id,
                        logo_path
                     )
                     values (?,?,?)',
                    [
                        $battleId,
                        (int)$designer['id'],
                        $stored['path'],
                    ]
                );

                return (int)DB::id();
            });
        }catch(Throwable $error){
            if(is_file($stored['absolute'])){
                @unlink($stored['absolute']);
            }

            throw $error;
        }
    }

    public function withdrawSignup(
        int $battleId,
        int $userId
    ): bool {
        return $this->atomic(function()use(
            $battleId,
            $userId
        ){
            $battle=DB::row(
                'select *
                 from battle_events
                 where id=?
                 for update',
                [$battleId]
            );

            if(!$battle){
                return false;
            }

            if($battle['status']!=='signup'){
                throw new DomainException(
                    'Battle registration can no longer be withdrawn after seeding.'
                );
            }

            $participant=DB::row(
                'select p.*
                 from battle_participants p
                 join designers d
                   on d.id=p.designer_id
                 where p.battle_id=?
                   and d.user_id=?
                   and p.status="active"
                 for update',
                [$battleId,$userId]
            );

            if(!$participant){
                return false;
            }

            DB::exec(
                'update battle_participants
                 set status="withdrawn"
                 where id=?',
                [(int)$participant['id']]
            );

            return true;
        });
    }

    public function generateBracket(
        int $battleId,
        int $adminId
    ): array {
        return $this->atomic(function()use(
            $battleId,
            $adminId
        ){
            $battle=DB::row(
                'select *
                 from battle_events
                 where id=?
                 for update',
                [$battleId]
            ) ?? throw new DomainException(
                'Battle not found.'
            );

            if($battle['status']!=='signup'){
                throw new DomainException(
                    'This Battle has already been seeded.'
                );
            }

            $participants=DB::rows(
                'select id
                 from battle_participants
                 where battle_id=?
                   and status="active"
                 order by id
                 for update',
                [$battleId]
            );

            if(count($participants)<2){
                throw new DomainException(
                    'At least two registered sellers are required to generate the bracket.'
                );
            }

            $ids=array_map(
                static fn(array $row):int =>
                    (int)$row['id'],
                $participants
            );

            $seed=bin2hex(random_bytes(32));

            $slots=self::deterministicSlots(
                $ids,
                $seed
            );

            $size=count($slots);
            $rounds=(int)round(
                log($size,2)
            );

            DB::exec(
                'update battle_events
                 set status="seeded",
                     seed_token=?,
                     bracket_size=?,
                     total_rounds=?,
                     current_round=null
                 where id=?',
                [
                    $seed,
                    $size,
                    $rounds,
                    $battleId,
                ]
            );

            foreach(
                $slots as
                $position=>$participantId
            ){
                if($participantId!==null){
                    DB::exec(
                        'update battle_participants
                         set seed_position=?
                         where id=?',
                        [
                            $position+1,
                            $participantId,
                        ]
                    );
                }
            }

            $roundIds=[];

            for(
                $round=1;
                $round<=$rounds;
                $round++
            ){
                DB::exec(
                    'insert into battle_rounds
                     (
                        battle_id,
                        round_number
                     )
                     values (?,?)',
                    [$battleId,$round]
                );

                $roundIds[$round]=(int)DB::id();

                $matchCount=(int)(
                    $size/(2**$round)
                );

                for(
                    $position=1;
                    $position<=$matchCount;
                    $position++
                ){
                    $p1=null;
                    $p2=null;
                    $winner=null;
                    $status='pending';

                    if($round===1){
                        $p1=$slots[
                            ($position-1)*2
                        ];

                        $p2=$slots[
                            (($position-1)*2)+1
                        ];

                        if(
                            $p1!==null &&
                            $p2===null
                        ){
                            $winner=$p1;
                            $status='completed';
                        }elseif(
                            $p1===null &&
                            $p2!==null
                        ){
                            $winner=$p2;
                            $status='completed';
                        }
                    }

                    DB::exec(
                        'insert into battle_matchups
                         (
                            battle_id,
                            round_id,
                            round_number,
                            bracket_position,
                            participant_one_id,
                            participant_two_id,
                            winner_participant_id,
                            status
                         )
                         values (?,?,?,?,?,?,?,?)',
                        [
                            $battleId,
                            $roundIds[$round],
                            $round,
                            $position,
                            $p1,
                            $p2,
                            $winner,
                            $status,
                        ]
                    );
                }
            }

            $this->propagateCompletedRound(
                $battleId,
                1
            );

            $this->adminLog(
                $adminId,
                'generated_battle_bracket',
                'battle_event',
                $battleId,
                [
                    'participant_count'=>count($ids),
                    'bracket_size'=>$size,
                    'rounds'=>$rounds,
                    'seed_token'=>$seed,
                ]
            );

            return [
                'seed_token'=>$seed,
                'participants'=>count($ids),
                'bracket_size'=>$size,
                'rounds'=>$rounds,
            ];
        });
    }

    private function propagateCompletedRound(
        int $battleId,
        int $roundNumber
    ): void {
        $battle=DB::row(
            'select *
             from battle_events
             where id=?',
            [$battleId]
        );

        if(!$battle){
            return;
        }

        if(
            $roundNumber >=
            (int)$battle['total_rounds']
        ){
            return;
        }

        $matchups=DB::rows(
            'select *
             from battle_matchups
             where battle_id=?
               and round_number=?
               and status="completed"
               and winner_participant_id is not null
             order by bracket_position',
            [$battleId,$roundNumber]
        );

        foreach($matchups as $matchup){
            $nextPosition=(int)ceil(
                (int)$matchup['bracket_position']/2
            );

            $field=
                ((int)$matchup['bracket_position']%2)===1
                    ? 'participant_one_id'
                    : 'participant_two_id';

            DB::exec(
                'update battle_matchups
                 set '.$field.'=coalesce('.$field.',?)
                 where battle_id=?
                   and round_number=?
                   and bracket_position=?',
                [
                    (int)$matchup['winner_participant_id'],
                    $battleId,
                    $roundNumber+1,
                    $nextPosition,
                ]
            );
        }
    }

    public function scheduleRound(
        int $battleId,
        int $roundNumber,
        int $adminId,
        string $startsAtLocal
    ): array {
        $timezone=new \DateTimeZone(
            'America/Detroit'
        );

        $start=\DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i',
            trim($startsAtLocal),
            $timezone
        );

        if(!$start){
            throw new InvalidArgumentException(
                'Choose a valid Battle start date and time.'
            );
        }

        if(
            $start->getTimestamp() <
            time()+
            (
                self::PRE_BATTLE_EMAIL_HOURS *
                60 *
                60
            )
        ){
            throw new DomainException(
                'The Battle round must be scheduled at least 12 hours ahead.'
            );
        }

        $opens=$start
            ->setTimezone(
                new \DateTimeZone('UTC')
            )
            ->format('Y-m-d H:i:s');

        return $this->atomic(function()use(
            $battleId,
            $roundNumber,
            $adminId,
            $opens
        ){
            return $this->scheduleRoundLocked(
                $battleId,
                $roundNumber,
                $adminId,
                $opens
            );
        });
    }

    private function scheduleRoundLocked(
        int $battleId,
        int $roundNumber,
        int $adminId,
        string $opens
    ): array {
        $battle=DB::row(
            'select *
             from battle_events
             where id=?
             for update',
            [$battleId]
        ) ?? throw new DomainException(
            'Battle not found.'
        );

        if((int)($battle['is_preview']??0)===1){
            throw new DomainException(
                'Preview Battles cannot schedule real voting rounds.'
            );
        }

        if(
            !in_array(
                $battle['status'],
                ['seeded','active'],
                true
            )
        ){
            throw new DomainException(
                'This Battle cannot schedule a voting round.'
            );
        }

        $round=DB::row(
            'select *
             from battle_rounds
             where battle_id=?
               and round_number=?
             for update',
            [$battleId,$roundNumber]
        ) ?? throw new DomainException(
            'Battle round not found.'
        );

        if($round['status']!=='pending'){
            throw new DomainException(
                'Only a pending Battle round can be scheduled.'
            );
        }

        if(!empty($round['voting_opens_at'])){
            throw new DomainException(
                'That Battle round is already scheduled.'
            );
        }

        if($roundNumber>1){
            $previous=DB::row(
                'select status
                 from battle_rounds
                 where battle_id=?
                   and round_number=?
                 for update',
                [
                    $battleId,
                    $roundNumber-1
                ]
            );

            if(
                !$previous ||
                $previous['status']!=='completed'
            ){
                throw new DomainException(
                    'The previous Battle round must be completed first.'
                );
            }
        }

        $pending=DB::rows(
            'select *
             from battle_matchups
             where battle_id=?
               and round_number=?
               and status="pending"
             order by bracket_position
             for update',
            [
                $battleId,
                $roundNumber
            ]
        );

        if(!$pending){
            throw new DomainException(
                'That round has no pending matchups.'
            );
        }

        foreach($pending as $matchup){
            if(
                empty($matchup['participant_one_id']) ||
                empty($matchup['participant_two_id'])
            ){
                throw new DomainException(
                    'The bracket is not ready to schedule this round.'
                );
            }
        }

        $opensTimestamp=strtotime(
            $opens.' UTC'
        );

        if($opensTimestamp===false){
            throw new DomainException(
                'Battle start time could not be calculated.'
            );
        }

        $closes=gmdate(
            'Y-m-d H:i:s',
            $opensTimestamp+
            (
                self::ROUND_DURATION_HOURS *
                60 *
                60
            )
        );

        DB::exec(
            'update battle_rounds
             set voting_opens_at=?,
                 voting_closes_at=?
             where id=?',
            [
                $opens,
                $closes,
                (int)$round['id'],
            ]
        );

        DB::exec(
            'update battle_matchups
             set voting_opens_at=?,
                 voting_closes_at=?
             where battle_id=?
               and round_number=?
               and status="pending"',
            [
                $opens,
                $closes,
                $battleId,
                $roundNumber,
            ]
        );

        $this->queueRoundEmails(
            $battleId,
            $roundNumber,
            $opens,
            $closes
        );

        $this->adminLog(
            $adminId,
            'scheduled_battle_round',
            'battle_event',
            $battleId,
            [
                'round'=>$roundNumber,
                'opens_at'=>$opens,
                'closes_at'=>$closes,
                'duration_hours'=>
                    self::ROUND_DURATION_HOURS,
                'warning_hours'=>
                    self::PRE_BATTLE_EMAIL_HOURS,
            ]
        );

        return [
            'opens_at'=>$opens,
            'closes_at'=>$closes,
        ];
    }

    public function openRound(
        int $battleId,
        int $roundNumber,
        int $adminId
    ): array {
        return $this->atomic(function()use(
            $battleId,
            $roundNumber,
            $adminId
        ){
            $battle=DB::row(
                'select *
                 from battle_events
                 where id=?
                 for update',
                [$battleId]
            ) ?? throw new DomainException(
                'Battle not found.'
            );

            if((int)($battle['is_preview']??0)===1){
                throw new DomainException(
                    'Preview Battles cannot open real voting rounds.'
                );
            }

            $round=DB::row(
                'select *
                 from battle_rounds
                 where battle_id=?
                   and round_number=?
                 for update',
                [$battleId,$roundNumber]
            ) ?? throw new DomainException(
                'Battle round not found.'
            );

            if($round['status']!=='pending'){
                throw new DomainException(
                    'That Battle round is not pending.'
                );
            }

            if(
                empty($round['voting_opens_at']) ||
                empty($round['voting_closes_at'])
            ){
                throw new DomainException(
                    'That Battle round has not been scheduled.'
                );
            }

            if(
                time() <
                strtotime(
                    (string)$round['voting_opens_at'].
                    ' UTC'
                )
            ){
                throw new DomainException(
                    'That Battle round is not scheduled to open yet.'
                );
            }

            DB::exec(
                'update battle_rounds
                 set status="open"
                 where id=?',
                [(int)$round['id']]
            );

            DB::exec(
                'update battle_matchups
                 set status="open"
                 where battle_id=?
                   and round_number=?
                   and status="pending"',
                [
                    $battleId,
                    $roundNumber,
                ]
            );

            DB::exec(
                'update battle_events
                 set status="active",
                     current_round=?
                 where id=?',
                [
                    $roundNumber,
                    $battleId,
                ]
            );

            $this->adminLog(
                $adminId,
                'opened_scheduled_battle_round',
                'battle_event',
                $battleId,
                [
                    'round'=>$roundNumber,
                    'opens_at'=>$round['voting_opens_at'],
                    'closes_at'=>$round['voting_closes_at'],
                ]
            );

            return [
                'opens_at'=>$round['voting_opens_at'],
                'closes_at'=>$round['voting_closes_at'],
            ];
        });
    }

    public function processSchedule(
        int $limit=50
    ): array {
        $limit=max(
            1,
            min(200,$limit)
        );

        $opened=0;
        $closed=0;
        $ties=0;
        $errors=[];

        $dueOpen=DB::rows(
            'select
                r.battle_id,
                r.round_number,
                e.created_by_admin_id
             from battle_rounds r
             join battle_events e
               on e.id=r.battle_id
             where r.status="pending"
               and r.voting_opens_at is not null
               and r.voting_opens_at<=utc_timestamp()
               and e.is_preview=0
               and e.status in ("seeded","active")
             order by r.voting_opens_at,r.id
             limit '.$limit
        );

        foreach($dueOpen as $row){
            try{
                $this->openRound(
                    (int)$row['battle_id'],
                    (int)$row['round_number'],
                    (int)$row['created_by_admin_id']
                );

                $opened++;

            }catch(Throwable $error){
                $errors[]=
                    'open:'.
                    (int)$row['battle_id'].
                    ':'.
                    (int)$row['round_number'].
                    ':'.
                    $error->getMessage();
            }
        }

        $dueClose=DB::rows(
            'select
                r.battle_id,
                r.round_number,
                e.created_by_admin_id
             from battle_rounds r
             join battle_events e
               on e.id=r.battle_id
             where r.status="open"
               and r.voting_closes_at is not null
               and r.voting_closes_at<=utc_timestamp()
               and e.is_preview=0
             order by r.voting_closes_at,r.id
             limit '.$limit
        );

        foreach($dueClose as $row){
            try{
                $result=$this->closeRound(
                    (int)$row['battle_id'],
                    (int)$row['round_number'],
                    (int)$row['created_by_admin_id']
                );

                $closed++;

                $ties+=(int)($result['ties']??0);

            }catch(Throwable $error){
                $errors[]=
                    'close:'.
                    (int)$row['battle_id'].
                    ':'.
                    (int)$row['round_number'].
                    ':'.
                    $error->getMessage();
            }
        }

        return [
            'opened'=>$opened,
            'closed'=>$closed,
            'ties'=>$ties,
            'errors'=>$errors,
        ];
    }

    private function queueRoundEmails(
        int $battleId,
        int $roundNumber,
        string $opens,
        string $closes
    ): void {
        $battle=DB::row(
            'select id,title,slug
             from battle_events
             where id=?',
            [$battleId]
        );

        if(!$battle){
            throw new DomainException(
                'Battle not found while scheduling emails.'
            );
        }

        $participants=DB::rows(
            'select distinct
                p.id participant_id,
                d.display_name,
                d.store_slug,
                u.id user_id,
                u.name,
                u.email
             from battle_matchups m
             join battle_participants p
               on p.id in (
                    m.participant_one_id,
                    m.participant_two_id
               )
             join designers d
               on d.id=p.designer_id
             join users u
               on u.id=d.user_id
             where m.battle_id=?
               and m.round_number=?
               and m.status in ("pending","open")
               and u.status="active"
             order by p.id',
            [
                $battleId,
                $roundNumber,
            ]
        );

        $opensTs=strtotime($opens);
        $closesTs=strtotime($closes);

        if(
            $opensTs===false ||
            $closesTs===false
        ){
            throw new DomainException(
                'Battle email schedule timestamps are invalid.'
            );
        }

        foreach($participants as $participant){
            $base=[
                'name'=>$participant['name'],
                'store_name'=>$participant['display_name'],
                'battle_title'=>$battle['title'],
                'round_number'=>$roundNumber,
                'round_hours'=>self::ROUND_DURATION_HOURS,
                'battle_url'=>
                    H::baseUrl().
                    '/battle-of-the-designers/'.
                    $battle['slug'],
            ];

            EmailQueueService::queueAt(
                'transactional',
                $participant['email'],
                'Your Battle time is almost here!',
                'battle_notification',
                $base+[
                    'stage'=>'upcoming',
                    'message'=>
                        'Your Battle time is almost here! '.
                        'You have exactly 24 hours to get your votes in. '.
                        'Get ready to share your matchup and rally your supporters.',
                ],
                'battle:'.
                    $battleId.
                    ':round:'.
                    $roundNumber.
                    ':participant:'.
                    $participant['participant_id'].
                    ':upcoming',
                date(
                    'Y-m-d H:i:s',
                    $opensTs-
                    (
                        self::PRE_BATTLE_EMAIL_HOURS *
                        60 *
                        60
                    )
                )
            );

            EmailQueueService::queueAt(
                'transactional',
                $participant['email'],
                'Your Battle is LIVE!',
                'battle_notification',
                $base+[
                    'stage'=>'live',
                    'message'=>
                        'Your Battle is officially live! '.
                        'You have exactly 24 hours to collect votes. '.
                        'Share your Battle link and send your supporters to Creative Moth to vote.',
                ],
                'battle:'.
                    $battleId.
                    ':round:'.
                    $roundNumber.
                    ':participant:'.
                    $participant['participant_id'].
                    ':live',
                $opens
            );

            EmailQueueService::queueAt(
                'transactional',
                $participant['email'],
                '1 hour left in your Battle!',
                'battle_notification',
                $base+[
                    'stage'=>'final_hour',
                    'message'=>
                        'Only 1 hour remains in your Battle! '.
                        'This is your last chance to make a final push for votes before the round closes.',
                ],
                'battle:'.
                    $battleId.
                    ':round:'.
                    $roundNumber.
                    ':participant:'.
                    $participant['participant_id'].
                    ':final-hour',
                date(
                    'Y-m-d H:i:s',
                    $closesTs-
                    (
                        self::FINAL_PUSH_EMAIL_HOURS *
                        60 *
                        60
                    )
                )
            );
        }
    }

    private function requestIdentity(
        string $ip,
        string $userAgent
    ): array {
        $ip=mb_substr(trim($ip),0,45);
        $userAgent=mb_substr(
            trim($userAgent),
            0,
            500
        );

        return [
            'ip'=>$ip,
            'user_agent'=>$userAgent,
            'fingerprint'=>hash(
                'sha256',
                $ip.'|'.$userAgent
            ),
        ];
    }

    private function recordAttempt(
        int $battleId,
        ?int $matchupId,
        ?int $userId,
        array $identity,
        string $outcome
    ): void {
        DB::exec(
            'insert into battle_vote_attempts
             (
                battle_id,
                matchup_id,
                user_id,
                ip_address,
                user_agent,
                fingerprint_hash,
                outcome
             )
             values (?,?,?,?,?,?,?)',
            [
                $battleId,
                $matchupId,
                $userId,
                $identity['ip'],
                $identity['user_agent'],
                $identity['fingerprint'],
                $outcome,
            ]
        );
    }

    private function rateLimited(
        int $userId,
        array $identity
    ): bool {
        $userAttempts=(int)(
            DB::row(
                'select count(*) c
                 from battle_vote_attempts
                 where user_id=?
                   and created_at>=date_sub(now(),interval 1 minute)',
                [$userId]
            )['c']??0
        );

        if(
            $userAttempts >=
            self::USER_ATTEMPT_LIMIT_PER_MINUTE
        ){
            return true;
        }

        $ipAttempts=(int)(
            DB::row(
                'select count(*) c
                 from battle_vote_attempts
                 where ip_address=?
                   and created_at>=date_sub(now(),interval 1 minute)',
                [$identity['ip']]
            )['c']??0
        );

        return
            $ipAttempts >=
            self::IP_ATTEMPT_LIMIT_PER_MINUTE;
    }

    private function upsertVoteFlag(
        int $battleId,
        int $matchupId,
        string $type,
        string $signal,
        int $count
    ): void {
        DB::exec(
            'insert into battle_vote_flags
             (
                battle_id,
                matchup_id,
                signal_type,
                signal_hash,
                event_count
             )
             values (?,?,?,?,?)
             on duplicate key update
                event_count=greatest(
                    event_count,
                    values(event_count)
                ),
                updated_at=now()',
            [
                $battleId,
                $matchupId,
                $type,
                hash('sha256',$signal),
                $count,
            ]
        );
    }

    private function flagSuspiciousAcceptedBurst(
        int $battleId,
        int $matchupId,
        array $identity
    ): void {
        $ip=DB::row(
            'select
                count(*) attempts,
                count(distinct user_id) users
             from battle_vote_attempts
             where battle_id=?
               and ip_address=?
               and outcome="accepted"
               and created_at>=date_sub(
                    now(),
                    interval 5 minute
               )',
            [
                $battleId,
                $identity['ip'],
            ]
        );

        if(
            (int)($ip['attempts']??0) >=
                self::SUSPICIOUS_ACCEPTED_IP_5_MINUTES &&
            (int)($ip['users']??0) >=
                self::SUSPICIOUS_DISTINCT_USERS_5_MINUTES
        ){
            $this->upsertVoteFlag(
                $battleId,
                $matchupId,
                'ip_burst',
                $identity['ip'],
                (int)$ip['attempts']
            );
        }

        $fingerprint=DB::row(
            'select
                count(*) attempts,
                count(distinct user_id) users
             from battle_vote_attempts
             where battle_id=?
               and fingerprint_hash=?
               and outcome="accepted"
               and created_at>=date_sub(
                    now(),
                    interval 5 minute
               )',
            [
                $battleId,
                $identity['fingerprint'],
            ]
        );

        if(
            (int)($fingerprint['attempts']??0) >=
                self::SUSPICIOUS_ACCEPTED_IP_5_MINUTES &&
            (int)($fingerprint['users']??0) >=
                self::SUSPICIOUS_DISTINCT_USERS_5_MINUTES
        ){
            $this->upsertVoteFlag(
                $battleId,
                $matchupId,
                'fingerprint_burst',
                $identity['fingerprint'],
                (int)$fingerprint['attempts']
            );
        }
    }

    public function vote(
        int $matchupId,
        int $participantId,
        int $userId,
        string $ip,
        string $userAgent
    ): int {
        if(DB::pdo()->inTransaction()){
            throw new DomainException(
                'Battle voting cannot begin inside another database transaction.'
            );
        }

        $identity=$this->requestIdentity(
            $ip,
            $userAgent
        );

        DB::begin();

        try{
            $user=DB::row(
                'select *
                 from users
                 where id=?
                   and status="active"
                 for update',
                [$userId]
            ) ?? throw new DomainException(
                'Voting requires an active Creative Moth account.'
            );

            $matchup=DB::row(
                'select *
                 from battle_matchups
                 where id=?
                 for update',
                [$matchupId]
            ) ?? throw new DomainException(
                'Battle matchup not found.'
            );

            $battleId=(int)$matchup['battle_id'];

            if(
                $this->rateLimited(
                    $userId,
                    $identity
                )
            ){
                $this->recordAttempt(
                    $battleId,
                    $matchupId,
                    $userId,
                    $identity,
                    'rate_limited'
                );

                DB::commit();

                throw new DomainException(
                    'Too many Battle voting requests were received. Try again shortly.'
                );
            }

            if(
                $matchup['status']!=='open' ||
                empty($matchup['voting_opens_at']) ||
                empty($matchup['voting_closes_at']) ||
                time()<strtotime(
                    (string)$matchup['voting_opens_at']
                ) ||
                time()>=strtotime(
                    (string)$matchup['voting_closes_at']
                )
            ){
                $this->recordAttempt(
                    $battleId,
                    $matchupId,
                    $userId,
                    $identity,
                    'closed'
                );

                DB::commit();

                throw new DomainException(
                    'Voting is not open for this matchup.'
                );
            }

            if(
                $participantId!==
                    (int)$matchup['participant_one_id'] &&
                $participantId!==
                    (int)$matchup['participant_two_id']
            ){
                $this->recordAttempt(
                    $battleId,
                    $matchupId,
                    $userId,
                    $identity,
                    'invalid_choice'
                );

                DB::commit();

                throw new DomainException(
                    'That designer is not part of this matchup.'
                );
            }

            if(DB::row(
                'select id
                 from battle_votes
                 where matchup_id=?
                   and user_id=?
                 limit 1',
                [$matchupId,$userId]
            )){
                $this->recordAttempt(
                    $battleId,
                    $matchupId,
                    $userId,
                    $identity,
                    'duplicate'
                );

                DB::commit();

                throw new DomainException(
                    'You already voted in this matchup.'
                );
            }

            DB::exec(
                'insert into battle_votes
                 (
                    battle_id,
                    matchup_id,
                    user_id,
                    participant_id,
                    ip_address,
                    user_agent,
                    fingerprint_hash
                 )
                 values (?,?,?,?,?,?,?)',
                [
                    $battleId,
                    $matchupId,
                    $userId,
                    $participantId,
                    $identity['ip'],
                    $identity['user_agent'],
                    $identity['fingerprint'],
                ]
            );

            $voteId=(int)DB::id();

            $this->recordAttempt(
                $battleId,
                $matchupId,
                $userId,
                $identity,
                'accepted'
            );

            $this->flagSuspiciousAcceptedBurst(
                $battleId,
                $matchupId,
                $identity
            );

            DB::commit();

            return $voteId;

        }catch(Throwable $error){
            if(DB::pdo()->inTransaction()){
                DB::rollBack();
            }

            throw $error;
        }
    }

    public function closeRound(
        int $battleId,
        int $roundNumber,
        int $adminId
    ): array {
        return $this->atomic(function()use(
            $battleId,
            $roundNumber,
            $adminId
        ){
            $battle=DB::row(
                'select *
                 from battle_events
                 where id=?
                 for update',
                [$battleId]
            ) ?? throw new DomainException(
                'Battle not found.'
            );

            $round=DB::row(
                'select *
                 from battle_rounds
                 where battle_id=?
                   and round_number=?
                 for update',
                [$battleId,$roundNumber]
            ) ?? throw new DomainException(
                'Battle round not found.'
            );

            if($round['status']!=='open'){
                throw new DomainException(
                    'That Battle round is not open.'
                );
            }

            if(
                empty($round['voting_closes_at']) ||
                time()<
                    strtotime(
                        (string)$round['voting_closes_at']
                    )
            ){
                throw new DomainException(
                    'Voting has not ended for this round.'
                );
            }

            $matchups=DB::rows(
                'select *
                 from battle_matchups
                 where battle_id=?
                   and round_number=?
                   and status="open"
                 order by bracket_position
                 for update',
                [$battleId,$roundNumber]
            );

            $ties=0;

            foreach($matchups as $matchup){
                $counts=DB::rows(
                    'select participant_id,count(*) votes
                     from battle_votes
                     where matchup_id=?
                     group by participant_id',
                    [(int)$matchup['id']]
                );

                $voteCounts=[
                    (int)$matchup['participant_one_id']=>0,
                    (int)$matchup['participant_two_id']=>0,
                ];

                foreach($counts as $count){
                    $voteCounts[
                        (int)$count['participant_id']
                    ]=(int)$count['votes'];
                }

                $one=$voteCounts[
                    (int)$matchup['participant_one_id']
                ];

                $two=$voteCounts[
                    (int)$matchup['participant_two_id']
                ];

                if($one===$two){
                    DB::exec(
                        'update battle_matchups
                         set status="tied"
                         where id=?',
                        [(int)$matchup['id']]
                    );

                    $ties++;
                    continue;
                }

                $winner=
                    $one>$two
                        ? (int)$matchup['participant_one_id']
                        : (int)$matchup['participant_two_id'];

                DB::exec(
                    'update battle_matchups
                     set status="completed",
                         winner_participant_id=?
                     where id=?',
                    [
                        $winner,
                        (int)$matchup['id'],
                    ]
                );
            }

            if($ties>0){
                DB::exec(
                    'update battle_rounds
                     set status="tied"
                     where id=?',
                    [(int)$round['id']]
                );

                $this->adminLog(
                    $adminId,
                    'closed_battle_round_with_ties',
                    'battle_event',
                    $battleId,
                    [
                        'round'=>$roundNumber,
                        'ties'=>$ties,
                    ]
                );

                return [
                    'completed'=>false,
                    'ties'=>$ties,
                ];
            }

            $this->completeRound(
                $battleId,
                $roundNumber,
                $adminId
            );

            return [
                'completed'=>true,
                'ties'=>0,
            ];
        });
    }

    public function resolveTie(
        int $matchupId,
        int $winnerParticipantId,
        int $adminId
    ): bool {
        return $this->atomic(function()use(
            $matchupId,
            $winnerParticipantId,
            $adminId
        ){
            $matchup=DB::row(
                'select *
                 from battle_matchups
                 where id=?
                 for update',
                [$matchupId]
            );

            if(!$matchup){
                return false;
            }

            if($matchup['status']!=='tied'){
                throw new DomainException(
                    'Only a tied matchup can be manually resolved.'
                );
            }

            if(
                $winnerParticipantId!==
                    (int)$matchup['participant_one_id'] &&
                $winnerParticipantId!==
                    (int)$matchup['participant_two_id']
            ){
                throw new InvalidArgumentException(
                    'Tie winner must be one of the two matchup participants.'
                );
            }

            DB::exec(
                'update battle_matchups
                 set status="completed",
                     winner_participant_id=?,
                     tie_resolved_by_admin_id=?,
                     tie_resolved_at=now()
                 where id=?',
                [
                    $winnerParticipantId,
                    $adminId,
                    $matchupId,
                ]
            );

            $remaining=(int)(
                DB::row(
                    'select count(*) c
                     from battle_matchups
                     where battle_id=?
                       and round_number=?
                       and status<>"completed"',
                    [
                        (int)$matchup['battle_id'],
                        (int)$matchup['round_number'],
                    ]
                )['c']??0
            );

            if($remaining===0){
                $this->completeRound(
                    (int)$matchup['battle_id'],
                    (int)$matchup['round_number'],
                    $adminId
                );
            }

            $this->adminLog(
                $adminId,
                'resolved_battle_tie',
                'battle_matchup',
                $matchupId,
                [
                    'winner_participant_id'=>
                        $winnerParticipantId,
                ]
            );

            return true;
        });
    }

    private function completeRound(
        int $battleId,
        int $roundNumber,
        int $adminId
    ): void {
        $battle=DB::row(
            'select *
             from battle_events
             where id=?
             for update',
            [$battleId]
        ) ?? throw new DomainException(
            'Battle not found.'
        );

        DB::exec(
            'update battle_rounds
             set status="completed"
             where battle_id=?
               and round_number=?',
            [$battleId,$roundNumber]
        );

        if(
            $roundNumber <
            (int)$battle['total_rounds']
        ){
            $this->propagateCompletedRound(
                $battleId,
                $roundNumber
            );

            $nextOpens=gmdate(
                'Y-m-d H:i:s',
                time()+
                (
                    self::PRE_BATTLE_EMAIL_HOURS *
                    60 *
                    60
                )
            );

            $this->scheduleRoundLocked(
                $battleId,
                $roundNumber+1,
                $adminId,
                $nextOpens
            );

            return;
        }

        $final=DB::row(
            'select *
             from battle_matchups
             where battle_id=?
               and round_number=?
               and bracket_position=1
               and status="completed"
             for update',
            [$battleId,$roundNumber]
        ) ?? throw new DomainException(
            'Completed championship matchup not found.'
        );

        $championId=(int)$final[
            'winner_participant_id'
        ];

        $champion=DB::row(
            'select p.*,d.user_id,d.display_name
             from battle_participants p
             join designers d
               on d.id=p.designer_id
             where p.id=?
             for update',
            [$championId]
        ) ?? throw new DomainException(
            'Battle champion participant not found.'
        );

        $key=
            'battle:'.
            $battleId.
            ':champion:advertising-credit:100';

        (new AdvertisingCreditService())->adjust(
            (int)$champion['user_id'],
            self::CHAMPION_AD_CREDIT,
            $key,
            [
                'admin_user_id'=>$adminId,
                'description'=>
                    'Battle of the Designers champion — '.
                    $battle['title'],
            ]
        );

        DB::exec(
            'update battle_participants
             set status=case
                when id=? then "champion"
                else status
             end
             where battle_id=?',
            [$championId,$battleId]
        );

        DB::exec(
            'update battle_events
             set status="completed",
                 champion_participant_id=?,
                 prize_awarded_at=coalesce(
                    prize_awarded_at,
                    now()
                 )
             where id=?',
            [$championId,$battleId]
        );

        $this->adminLog(
            $adminId,
            'completed_battle',
            'battle_event',
            $battleId,
            [
                'champion_participant_id'=>$championId,
                'champion_user_id'=>(int)$champion['user_id'],
                'advertising_credit'=>
                    self::CHAMPION_AD_CREDIT,
                'idempotency_key'=>$key,
            ]
        );
    }

    public function eventById(int $id): ?array
    {
        return DB::row(
            'select *
             from battle_events
             where id=?',
            [$id]
        );
    }

    public function eventBySlug(string $slug): ?array
    {
        return DB::row(
            'select *
             from battle_events
             where slug=?
               and status<>"cancelled"
             limit 1',
            [$slug]
        );
    }

    public function publicEvents(): array
    {
        return DB::rows(
            'select e.*,
                    (
                        select count(*)
                        from battle_participants p
                        where p.battle_id=e.id
                          and p.status<>"withdrawn"
                    ) participant_count
             from battle_events e
             where e.status<>"cancelled"
               and e.is_preview=0
             order by
                case e.status
                    when "active" then 1
                    when "signup" then 2
                    when "seeded" then 3
                    when "completed" then 4
                    else 5
                end,
                e.id desc'
        );
    }

    public function createPreview(
        int $adminId
    ): int {
        return $this->atomic(function()use($adminId){
            $admin=DB::row(
                'select id
                 from users
                 where id=?
                   and role="admin"
                   and status="active"
                 for update',
                [$adminId]
            );

            if(!$admin){
                throw new DomainException(
                    'An active admin is required.'
                );
            }

            $existing=DB::row(
                'select id
                 from battle_events
                 where is_preview=1
                 order by id desc
                 limit 1
                 for update'
            );

            if($existing){
                return (int)$existing['id'];
            }

            $suffix=bin2hex(random_bytes(4));

            $fakeUsers=[
                [
                    'name'=>'Preview Designer Luna',
                    'email'=>'battle-preview-luna-'.$suffix.'@example.test',
                    'display'=>'Luna & Ink Studio',
                    'slug'=>'battle-preview-luna-'.$suffix,
                    'logo'=>'LUNA & INK',
                ],
                [
                    'name'=>'Preview Designer Raven',
                    'email'=>'battle-preview-raven-'.$suffix.'@example.test',
                    'display'=>'Raven Moon Creative',
                    'slug'=>'battle-preview-raven-'.$suffix,
                    'logo'=>'RAVEN MOON',
                ],
            ];

            $designerIds=[];

            foreach($fakeUsers as $index=>$fake){
                DB::exec(
                    'insert into users
                     (
                        name,
                        email,
                        password_hash,
                        role,
                        status
                     )
                     values (?,?,?,?,?)',
                    [
                        $fake['name'],
                        $fake['email'],
                        password_hash(
                            bin2hex(random_bytes(32)),
                            PASSWORD_DEFAULT
                        ),
                        'designer',
                        'active',
                    ]
                );

                $userId=(int)DB::id();

                DB::exec(
                    'insert into designers
                     (
                        user_id,
                        display_name,
                        store_slug,
                        status
                     )
                     values (?,?,?,"approved")',
                    [
                        $userId,
                        $fake['display'],
                        $fake['slug'],
                    ]
                );

                $designerId=(int)DB::id();
                $designerIds[]=$designerId;

                $dir=public_path(
                    'uploads/battles/logos'
                );

                if(
                    !is_dir($dir) &&
                    !mkdir($dir,0775,true) &&
                    !is_dir($dir)
                ){
                    throw new DomainException(
                        'Battle preview logo storage is unavailable.'
                    );
                }

                $filename=
                    'preview-'.$suffix.'-'.$index.'.png';

                $absolute=$dir.'/'.$filename;

                $canvas=imagecreatetruecolor(
                    800,
                    800
                );

                $bg=imagecolorallocate(
                    $canvas,
                    $index===0?235:28,
                    $index===0?220:24,
                    $index===0?235:35
                );

                $fg=imagecolorallocate(
                    $canvas,
                    $index===0?35:245,
                    $index===0?25:235,
                    $index===0?45:245
                );

                imagefill(
                    $canvas,
                    0,
                    0,
                    $bg
                );

                imagestring(
                    $canvas,
                    5,
                    260,
                    365,
                    $fake['logo'],
                    $fg
                );

                if(!imagepng(
                    $canvas,
                    $absolute,
                    6
                )){
                    imagedestroy($canvas);

                    throw new DomainException(
                        'Battle preview logo could not be created.'
                    );
                }

                imagedestroy($canvas);
                @chmod($absolute,0644);

                $fakeUsers[$index]['logo_path']=
                    '/uploads/battles/logos/'.
                    $filename;
            }

            DB::exec(
                'insert into battle_events
                 (
                    title,
                    slug,
                    status,
                    is_preview,
                    created_by_admin_id
                 )
                 values (?,?,?,?,?)',
                [
                    'Battle of the Designers — PREVIEW',
                    'battle-preview-'.$suffix,
                    'signup',
                    1,
                    $adminId,
                ]
            );

            $battleId=(int)DB::id();

            foreach($designerIds as $index=>$designerId){
                DB::exec(
                    'insert into battle_participants
                     (
                        battle_id,
                        designer_id,
                        logo_path
                     )
                     values (?,?,?)',
                    [
                        $battleId,
                        $designerId,
                        $fakeUsers[$index]['logo_path'],
                    ]
                );
            }

            $this->adminLog(
                $adminId,
                'created_battle_preview',
                'battle_event',
                $battleId,
                [
                    'fake_designers'=>2,
                ]
            );

            return $battleId;
        });
    }

    public function deletePreview(
        int $battleId,
        int $adminId
    ): bool {
        return $this->atomic(function()use(
            $battleId,
            $adminId
        ){
            $battle=DB::row(
                'select *
                 from battle_events
                 where id=?
                   and is_preview=1
                 for update',
                [$battleId]
            );

            if(!$battle){
                return false;
            }

            $designers=DB::rows(
                'select
                    d.id designer_id,
                    d.user_id,
                    p.logo_path
                 from battle_participants p
                 join designers d
                   on d.id=p.designer_id
                 where p.battle_id=?',
                [$battleId]
            );

            DB::exec(
                'delete from battle_vote_flags
                 where battle_id=?',
                [$battleId]
            );

            DB::exec(
                'delete from battle_vote_attempts
                 where battle_id=?',
                [$battleId]
            );

            DB::exec(
                'delete from battle_votes
                 where battle_id=?',
                [$battleId]
            );

            DB::exec(
                'delete from battle_matchups
                 where battle_id=?',
                [$battleId]
            );

            DB::exec(
                'delete from battle_rounds
                 where battle_id=?',
                [$battleId]
            );

            DB::exec(
                'update battle_events
                 set champion_participant_id=null
                 where id=?',
                [$battleId]
            );

            DB::exec(
                'delete from battle_participants
                 where battle_id=?',
                [$battleId]
            );

            DB::exec(
                'delete from battle_events
                 where id=?',
                [$battleId]
            );

            foreach($designers as $designer){
                $logo=(string)$designer['logo_path'];

                if(
                    str_starts_with(
                        $logo,
                        '/uploads/battles/logos/preview-'
                    )
                ){
                    $absolute=
                        public_path(
                            ltrim($logo,'/')
                        );

                    if(is_file($absolute)){
                        @unlink($absolute);
                    }
                }

                DB::exec(
                    'delete from designers
                     where id=?',
                    [(int)$designer['designer_id']]
                );

                DB::exec(
                    'delete from users
                     where id=?',
                    [(int)$designer['user_id']]
                );
            }

            $this->adminLog(
                $adminId,
                'deleted_battle_preview',
                'battle_event',
                $battleId
            );

            return true;
        });
    }

    public function adminEvents(): array
    {
        return DB::rows(
            'select e.*,
                    (
                        select count(*)
                        from battle_participants p
                        where p.battle_id=e.id
                          and p.status<>"withdrawn"
                    ) participant_count,
                    (
                        select count(*)
                        from battle_vote_flags f
                        where f.battle_id=e.id
                          and f.status="open"
                    ) open_flag_count
             from battle_events e
             order by e.id desc'
        );
    }

    public function sellerEvents(int $userId): array
    {
        return DB::rows(
            'select
                e.*,
                p.id participant_id,
                p.logo_path,
                p.status participant_status,
                p.seed_position
             from battle_events e
             left join designers d
               on d.user_id=?
              and d.status="approved"
             left join battle_participants p
               on p.battle_id=e.id
              and p.designer_id=d.id
             where e.status<>"cancelled"
               and e.is_preview=0
             order by e.id desc',
            [$userId]
        );
    }

    public function participants(int $battleId): array
    {
        return DB::rows(
            'select
                p.*,
                d.display_name,
                d.store_slug,
                d.user_id
             from battle_participants p
             join designers d
               on d.id=p.designer_id
             where p.battle_id=?
               and p.status<>"withdrawn"
             order by
                coalesce(p.seed_position,999999),
                p.id',
            [$battleId]
        );
    }

    public function bracket(int $battleId): array
    {
        $rounds=DB::rows(
            'select *
             from battle_rounds
             where battle_id=?
             order by round_number',
            [$battleId]
        );

        foreach($rounds as &$round){
            $round['matchups']=DB::rows(
                'select
                    m.*,

                    p1.logo_path participant_one_logo,
                    d1.display_name participant_one_name,
                    d1.store_slug participant_one_store,

                    p2.logo_path participant_two_logo,
                    d2.display_name participant_two_name,
                    d2.store_slug participant_two_store,

                    wp.logo_path winner_logo,
                    wd.display_name winner_name,

                    (
                        select count(*)
                        from battle_votes v
                        where v.matchup_id=m.id
                          and v.participant_id=
                              m.participant_one_id
                    ) participant_one_votes,

                    (
                        select count(*)
                        from battle_votes v
                        where v.matchup_id=m.id
                          and v.participant_id=
                              m.participant_two_id
                    ) participant_two_votes

                 from battle_matchups m

                 left join battle_participants p1
                   on p1.id=m.participant_one_id
                 left join designers d1
                   on d1.id=p1.designer_id

                 left join battle_participants p2
                   on p2.id=m.participant_two_id
                 left join designers d2
                   on d2.id=p2.designer_id

                 left join battle_participants wp
                   on wp.id=m.winner_participant_id
                 left join designers wd
                   on wd.id=wp.designer_id

                 where m.round_id=?
                 order by m.bracket_position',
                [(int)$round['id']]
            );
        }

        unset($round);

        return $rounds;
    }

    public function userVotes(
        int $battleId,
        int $userId
    ): array {
        $rows=DB::rows(
            'select matchup_id,participant_id
             from battle_votes
             where battle_id=?
               and user_id=?',
            [$battleId,$userId]
        );

        $votes=[];

        foreach($rows as $row){
            $votes[(int)$row['matchup_id']]=
                (int)$row['participant_id'];
        }

        return $votes;
    }

    public function voterLedger(int $battleId): array
    {
        return DB::rows(
            'select
                v.id vote_id,
                v.created_at voted_at,
                v.ip_address,
                v.user_agent,
                v.fingerprint_hash browser_fingerprint_hash,

                u.id user_id,
                u.name voter_name,
                u.email voter_email,
                u.created_at account_created_at,

                m.round_number,
                m.bracket_position,

                d.display_name voted_for_name,
                d.store_slug voted_for_store,

                p.logo_path voted_for_logo

             from battle_votes v

             join users u
               on u.id=v.user_id

             join battle_matchups m
               on m.id=v.matchup_id

             join battle_participants p
               on p.id=v.participant_id

             join designers d
               on d.id=p.designer_id

             where v.battle_id=?

             order by
                m.round_number desc,
                m.bracket_position,
                d.display_name,
                v.created_at desc,
                v.id desc',
            [$battleId]
        );
    }

    public function previousRoundWinners(
        int $battleId,
        int $currentRound
    ): array {
        if($currentRound<=1){
            return [];
        }

        return DB::rows(
            'select distinct
                p.id participant_id,
                p.logo_path,
                d.display_name,
                d.store_slug,
                m.round_number,
                m.bracket_position

             from battle_matchups m

             join battle_participants p
               on p.id=m.winner_participant_id

             join designers d
               on d.id=p.designer_id

             where m.battle_id=?
               and m.round_number=?
               and m.status="completed"
               and m.winner_participant_id is not null

             order by
                m.bracket_position,
                d.display_name',
            [
                $battleId,
                $currentRound-1
            ]
        );
    }

    public function voteFlags(int $battleId): array
    {
        return DB::rows(
            'select
                f.*,
                m.round_number,
                m.bracket_position
             from battle_vote_flags f
             left join battle_matchups m
               on m.id=f.matchup_id
             where f.battle_id=?
             order by
                case f.status
                    when "open" then 1
                    else 2
                end,
                f.created_at desc,
                f.id desc',
            [$battleId]
        );
    }

    public function reviewVoteFlag(
        int $flagId,
        string $decision,
        string $notes,
        int $adminId
    ): bool {
        if(!in_array(
            $decision,
            ['reviewed','dismissed'],
            true
        )){
            throw new InvalidArgumentException(
                'Choose reviewed or dismissed.'
            );
        }

        $notes=mb_substr(
            trim($notes),
            0,
            1000
        );

        return $this->atomic(function()use(
            $flagId,
            $decision,
            $notes,
            $adminId
        ){
            $flag=DB::row(
                'select *
                 from battle_vote_flags
                 where id=?
                 for update',
                [$flagId]
            );

            if(!$flag){
                return false;
            }

            if($flag['status']!=='open'){
                throw new DomainException(
                    'That Battle voting flag was already reviewed.'
                );
            }

            DB::exec(
                'update battle_vote_flags
                 set status=?,
                     reviewed_by_admin_id=?,
                     reviewed_at=now(),
                     admin_notes=?
                 where id=?',
                [
                    $decision,
                    $adminId,
                    $notes!==''?$notes:null,
                    $flagId,
                ]
            );

            $this->adminLog(
                $adminId,
                'reviewed_battle_vote_flag',
                'battle_vote_flag',
                $flagId,
                [
                    'decision'=>$decision,
                    'notes'=>$notes,
                ]
            );

            return true;
        });
    }

    private function adminLog(
        int $adminId,
        string $action,
        string $entityType,
        int $entityId,
        array $metadata=[]
    ): void {
        DB::exec(
            'insert into admin_logs
             (
                admin_user_id,
                action,
                entity_type,
                entity_id,
                metadata
             )
             values (?,?,?,?,?)',
            [
                $adminId,
                $action,
                $entityType,
                $entityId,
                json_encode(
                    $metadata,
                    JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_SLASHES
                ),
            ]
        );
    }
}
