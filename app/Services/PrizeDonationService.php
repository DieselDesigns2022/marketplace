<?php

namespace App\Services;

use App\Core\Database as DB;
use DomainException;
use InvalidArgumentException;
use Throwable;

class PrizeDonationService
{
    public const TYPES=[
        'finished_product'=>'Finished product',
        'bundle'=>'Bundle',
        'custom_design'=>'Custom design',
        'store_credit'=>'Seller / store credit',
        'exclusive'=>'Exclusive product',
        'service'=>'Service / digital prize',
        'other'=>'Other marketplace-compliant prize',
    ];

    public const USAGES=[
        'prize_wheel'=>'Prize Wheel',
        'giveaway'=>'Giveaway',
        'social_game'=>'Facebook / social game',
        'battle'=>'Battle of the Designers',
        'other'=>'Other Grand Opening event',
    ];

    private function atomic(callable $fn): mixed
    {
        $own=!DB::pdo()->inTransaction();

        if($own){
            DB::begin();
        }

        try{
            $value=$fn();

            if($own){
                DB::commit();
            }

            return $value;
        }catch(Throwable $e){
            if($own && DB::pdo()->inTransaction()){
                DB::rollBack();
            }

            throw $e;
        }
    }

    private function seller(int $designerId): array
    {
        return DB::row(
            'select * from designers
             where id=? and status="approved"',
            [$designerId]
        ) ?? throw new DomainException(
            'Prize donations require an approved seller account.'
        );
    }

    private function quantity(mixed $raw): int
    {
        $value=trim((string)$raw);

        if(
            $value==='' ||
            !preg_match('/^[1-9][0-9]*$/D',$value)
        ){
            throw new InvalidArgumentException(
                'Quantity must be a whole number of at least 1.'
            );
        }

        $quantity=(int)$value;

        if($quantity<1 || $quantity>10000){
            throw new InvalidArgumentException(
                'Quantity must be between 1 and 10,000.'
            );
        }

        return $quantity;
    }

    private function value(mixed $raw): ?string
    {
        $value=trim((string)$raw);

        if($value===''){
            return null;
        }

        $cents=CreditService::parseCents($value,false);

        if($cents<0){
            throw new InvalidArgumentException(
                'Approximate retail value cannot be negative.'
            );
        }

        return CreditService::formatCents($cents);
    }

    private function usages(mixed $raw): array
    {
        $values=is_array($raw)?$raw:[];

        $values=array_values(
            array_unique(
                array_filter(
                    array_map(
                        fn($v)=>trim((string)$v),
                        $values
                    ),
                    fn($v)=>isset(self::USAGES[$v])
                )
            )
        );

        sort($values);

        if(!$values){
            throw new InvalidArgumentException(
                'Choose at least one approved prize use.'
            );
        }

        return $values;
    }

    private function storeDonationUpload(
        array $file,
        string $kind
    ): array {
        $label=$kind==='preview'
            ? 'Preview image'
            : 'Prize file';

        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);

        if($error===UPLOAD_ERR_NO_FILE){
            throw new InvalidArgumentException(
                $label.' is required.'
            );
        }

        if($error!==UPLOAD_ERR_OK){
            throw new InvalidArgumentException(
                $label.' upload failed.'
            );
        }

        $tmp=(string)($file['tmp_name']??'');
        $original=(string)($file['name']??'');
        $reportedSize=$file['size']??null;

        if(
            $tmp==='' ||
            $original==='' ||
            !is_uploaded_file($tmp)
        ){
            throw new InvalidArgumentException(
                $label.' must be a genuine uploaded file.'
            );
        }

        if(
            !is_int($reportedSize) &&
            !ctype_digit((string)$reportedSize)
        ){
            throw new InvalidArgumentException(
                $label.' has an invalid file size.'
            );
        }

        $size=(int)$reportedSize;
        $actualSize=@filesize($tmp);

        if(
            $actualSize===false ||
            $actualSize!==$size ||
            $size<1
        ){
            throw new InvalidArgumentException(
                $label.' has an invalid file size.'
            );
        }

        $finfo=new \finfo(FILEINFO_MIME_TYPE);
        $mime=(string)($finfo->file($tmp)?:'');

        if($kind==='preview'){
            if($size>25*1024*1024){
                throw new InvalidArgumentException(
                    'Preview image must be 25 MB or smaller.'
                );
            }

            $types=[
                'image/jpeg'=>'jpg',
                'image/png'=>'png',
                'image/webp'=>'webp',
            ];

            if(!isset($types[$mime])){
                throw new InvalidArgumentException(
                    'Preview image must be a genuine JPG, PNG, or WEBP image.'
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
                    'Preview image is malformed or unsafe.'
                );
            }

            if(
                !extension_loaded('gd') ||
                !function_exists('imagecreatefromstring')
            ){
                throw new \RuntimeException(
                    'Image decoding is unavailable.'
                );
            }

            $decoded=@imagecreatefromstring($bytes);

            if(!$decoded){
                throw new InvalidArgumentException(
                    'Preview image could not be decoded.'
                );
            }

            imagedestroy($decoded);

            $directory=public_path(
                'uploads/prize_donations/previews'
            );

            if(
                !is_dir($directory) &&
                !mkdir($directory,0775,true) &&
                !is_dir($directory)
            ){
                throw new \RuntimeException(
                    'Prize preview storage is unavailable.'
                );
            }

            $name=bin2hex(random_bytes(24)).
                '.'.$types[$mime];

            $absolute=$directory.'/'.$name;

            if(!move_uploaded_file($tmp,$absolute)){
                throw new \RuntimeException(
                    'Prize preview image could not be stored.'
                );
            }

            @chmod($absolute,0644);

            return [
                'kind'=>'preview',
                'absolute'=>$absolute,
                'path'=>'/uploads/prize_donations/previews/'.$name,
                'original_name'=>mb_substr(
                    basename(
                        str_replace('\\','/',$original)
                    ),
                    0,
                    190
                ),
                'mime'=>$mime,
                'size'=>$size,
            ];
        }

        if($size>100*1024*1024){
            throw new InvalidArgumentException(
                'Prize file must be 100 MB or smaller.'
            );
        }

        $types=[
            'application/zip'=>'zip',
            'application/x-zip-compressed'=>'zip',
            'application/pdf'=>'pdf',
            'image/jpeg'=>'jpg',
            'image/png'=>'png',
            'image/webp'=>'webp',
        ];

        if(!isset($types[$mime])){
            throw new InvalidArgumentException(
                'Prize file must be a ZIP, PDF, JPG, PNG, or WEBP file.'
            );
        }

        if(str_starts_with($mime,'image/')){
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
                    'Prize image is malformed or unsafe.'
                );
            }
        }

        $directory=app_path(
            'storage/protected_uploads/prize_donations'
        );

        if(
            !is_dir($directory) &&
            !mkdir($directory,0770,true) &&
            !is_dir($directory)
        ){
            throw new \RuntimeException(
                'Protected prize storage is unavailable.'
            );
        }

        $name=bin2hex(random_bytes(24)).
            '.'.$types[$mime];

        $absolute=$directory.'/'.$name;

        if(!move_uploaded_file($tmp,$absolute)){
            throw new \RuntimeException(
                'Prize file could not be stored.'
            );
        }

        @chmod($absolute,0640);

        return [
            'kind'=>'prize',
            'absolute'=>$absolute,
            'path'=>'prize_donations/'.$name,
            'original_name'=>mb_substr(
                basename(
                    str_replace('\\','/',$original)
                ),
                0,
                190
            ),
            'mime'=>$mime,
            'size'=>$size,
        ];
    }


    private function removeStoredUpload(?array $file): void
    {
        if(
            $file &&
            !empty($file['absolute']) &&
            is_file($file['absolute'])
        ){
            @unlink($file['absolute']);
        }
    }


    public function sellerFile(
        int $donationId,
        int $designerId
    ): ?array {
        return DB::row(
            'select id,
                    designer_id,
                    prize_file_storage_path,
                    prize_file_original_name,
                    prize_file_mime_type,
                    prize_file_size
             from prize_donations
             where id=?
               and designer_id=?
               and prize_file_storage_path is not null
             limit 1',
            [$donationId,$designerId]
        );
    }


    public function adminFile(int $donationId): ?array
    {
        return DB::row(
            'select id,
                    designer_id,
                    prize_file_storage_path,
                    prize_file_original_name,
                    prize_file_mime_type,
                    prize_file_size
             from prize_donations
             where id=?
               and prize_file_storage_path is not null
             limit 1',
            [$donationId]
        );
    }


    public function submit(
        int $designerId,
        array $data,
        array $files=[]
    ): int {
        $this->seller($designerId);

        $type=trim((string)($data['prize_type']??''));
        $title=trim((string)($data['title']??''));
        $description=trim((string)($data['description']??''));
        $instructions=trim(
            (string)($data['fulfillment_instructions']??'')
        );
        $restrictions=trim(
            (string)($data['restrictions']??'')
        );

        if(!isset(self::TYPES[$type])){
            throw new InvalidArgumentException(
                'Choose a valid prize type.'
            );
        }

        if(mb_strlen($title)<3 || mb_strlen($title)>190){
            throw new InvalidArgumentException(
                'Prize title must be between 3 and 190 characters.'
            );
        }

        if(
            mb_strlen($description)<10 ||
            mb_strlen($description)>5000
        ){
            throw new InvalidArgumentException(
                'Prize description must be between 10 and 5,000 characters.'
            );
        }

        if(
            mb_strlen($instructions)<5 ||
            mb_strlen($instructions)>5000
        ){
            throw new InvalidArgumentException(
                'Fulfillment instructions must be between 5 and 5,000 characters.'
            );
        }

        if(mb_strlen($restrictions)>3000){
            throw new InvalidArgumentException(
                'Prize restrictions must be 3,000 characters or fewer.'
            );
        }

        if(empty($data['seller_authorized'])){
            throw new InvalidArgumentException(
                'You must authorize Creative Moth to award this donated prize.'
            );
        }

        $quantity=$this->quantity(
            $data['quantity']??null
        );

        $value=$this->value(
            $data['approximate_retail_value']??''
        );

        $usages=$this->usages(
            $data['usage_designations']??[]
        );

        $preview=null;
        $prize=null;

        try{
            $preview=$this->storeDonationUpload(
                $files['preview_image']??[],
                'preview'
            );

            $prize=$this->storeDonationUpload(
                $files['prize_file']??[],
                'prize'
            );

            DB::exec(
                'insert into prize_donations
                 (
                    designer_id,
                    prize_type,
                    title,
                    description,
                    preview_image_path,
                    prize_file_storage_path,
                    prize_file_original_name,
                    prize_file_mime_type,
                    prize_file_size,
                    approximate_retail_value,
                    quantity_donated,
                    quantity_remaining,
                    fulfillment_instructions,
                    restrictions,
                    usage_designations,
                    seller_authorized,
                    status
                 )
                 values (
                    ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,"submitted"
                 )',
                [
                    $designerId,
                    $type,
                    $title,
                    $description,
                    $preview['path'],
                    $prize['path'],
                    $prize['original_name'],
                    $prize['mime'],
                    $prize['size'],
                    $value,
                    $quantity,
                    $quantity,
                    $instructions,
                    $restrictions!==''?$restrictions:null,
                    implode(',',$usages),
                ]
            );

            return (int)DB::id();
        }catch(Throwable $e){
            $this->removeStoredUpload($preview);
            $this->removeStoredUpload($prize);
            throw $e;
        }
    }

    public function sellerDonations(int $designerId): array
    {
        return DB::rows(
            'select d.*,
                coalesce(
                    (
                        select sum(a.quantity)
                        from prize_assignments a
                        where a.prize_donation_id=d.id
                          and a.status<>"cancelled"
                    ),
                    0
                ) assigned_quantity,
                coalesce(
                    (
                        select sum(a.quantity)
                        from prize_assignments a
                        where a.prize_donation_id=d.id
                          and a.status="fulfilled"
                    ),
                    0
                ) fulfilled_quantity
             from prize_donations d
             where d.designer_id=?
             order by d.created_at desc,d.id desc',
            [$designerId]
        );
    }

    public function adminDonations(): array
    {
        return DB::rows(
            'select d.*,
                    s.display_name seller_name,
                    s.store_slug,
                    coalesce(
                        (
                            select sum(a.quantity)
                            from prize_assignments a
                            where a.prize_donation_id=d.id
                              and a.status<>"cancelled"
                        ),
                        0
                    ) assigned_quantity,
                    coalesce(
                        (
                            select sum(a.quantity)
                            from prize_assignments a
                            where a.prize_donation_id=d.id
                              and a.status in ("awarded","fulfilled")
                        ),
                        0
                    ) awarded_quantity,
                    coalesce(
                        (
                            select sum(a.quantity)
                            from prize_assignments a
                            where a.prize_donation_id=d.id
                              and a.status="fulfilled"
                        ),
                        0
                    ) fulfilled_quantity
             from prize_donations d
             join designers s on s.id=d.designer_id
             order by
                field(
                    d.status,
                    "submitted",
                    "available",
                    "assigned",
                    "awarded",
                    "approved",
                    "fulfilled",
                    "cancelled"
                ),
                d.created_at desc,
                d.id desc'
        );
    }

    public function assignments(): array
    {
        return DB::rows(
            'select a.*,
                    d.title prize_title,
                    d.prize_type,
                    s.display_name seller_name,
                    u.name winner_account_name,
                    u.email winner_account_email
             from prize_assignments a
             join prize_donations d
               on d.id=a.prize_donation_id
             join designers s
               on s.id=d.designer_id
             left join users u
               on u.id=a.winner_user_id
             order by a.created_at desc,a.id desc'
        );
    }

    public function approve(
        int $donationId,
        int $adminId
    ): bool {
        return $this->atomic(function()use(
            $donationId,
            $adminId
        ){
            $donation=DB::row(
                'select * from prize_donations
                 where id=? for update',
                [$donationId]
            );

            if(!$donation){
                return false;
            }

            if($donation['status']==='available'){
                return false;
            }

            if($donation['status']!=='submitted'){
                throw new DomainException(
                    'Only submitted prize donations may be approved.'
                );
            }

            DB::exec(
                'update prize_donations
                 set status="available",
                     approved_by_admin_id=?,
                     approved_at=now()
                 where id=?',
                [$adminId,$donationId]
            );

            return true;
        });
    }

    public function sellerCancel(
        int $donationId,
        int $designerId
    ): bool {
        return $this->atomic(function()use(
            $donationId,
            $designerId
        ){
            $donation=DB::row(
                'select * from prize_donations
                 where id=? and designer_id=?
                 for update',
                [$donationId,$designerId]
            );

            if(!$donation){
                return false;
            }

            if($donation['status']!=='submitted'){
                throw new DomainException(
                    'Only a submitted prize may be withdrawn by the seller.'
                );
            }

            DB::exec(
                'update prize_donations
                 set status="cancelled",
                     quantity_remaining=0,
                     cancelled_at=now()
                 where id=?',
                [$donationId]
            );

            return true;
        });
    }

    public function adminCancel(int $donationId): bool
    {
        return $this->atomic(function()use($donationId){
            $donation=DB::row(
                'select * from prize_donations
                 where id=? for update',
                [$donationId]
            );

            if(!$donation){
                return false;
            }

            if(
                in_array(
                    $donation['status'],
                    ['cancelled','fulfilled'],
                    true
                )
            ){
                return false;
            }

            $active=DB::row(
                'select id from prize_assignments
                 where prize_donation_id=?
                   and status<>"cancelled"
                 limit 1',
                [$donationId]
            );

            if($active){
                throw new DomainException(
                    'A prize with an active assignment cannot be cancelled.'
                );
            }

            DB::exec(
                'update prize_donations
                 set status="cancelled",
                     quantity_remaining=0,
                     cancelled_at=now()
                 where id=?',
                [$donationId]
            );

            return true;
        });
    }

    public function assign(
        int $donationId,
        int $quantity,
        string $eventType,
        ?string $eventReference,
        int $adminId,
        string $idempotencyKey
    ): int {
        if($quantity<1){
            throw new InvalidArgumentException(
                'Assignment quantity must be at least 1.'
            );
        }

        if(!isset(self::USAGES[$eventType])){
            throw new InvalidArgumentException(
                'Choose a valid prize event type.'
            );
        }

        $idempotencyKey=trim($idempotencyKey);

        if(
            $idempotencyKey==='' ||
            mb_strlen($idempotencyKey)>190
        ){
            throw new InvalidArgumentException(
                'Prize assignment idempotency key is invalid.'
            );
        }

        $eventReference=trim((string)$eventReference);

        if(mb_strlen($eventReference)>190){
            throw new InvalidArgumentException(
                'Event reference must be 190 characters or fewer.'
            );
        }

        return $this->atomic(function()use(
            $donationId,
            $quantity,
            $eventType,
            $eventReference,
            $adminId,
            $idempotencyKey
        ){
            $existing=DB::row(
                'select * from prize_assignments
                 where idempotency_key=?',
                [$idempotencyKey]
            );

            if($existing){
                if(
                    (int)$existing['prize_donation_id']!==$donationId ||
                    (int)$existing['quantity']!==$quantity ||
                    $existing['event_type']!==$eventType
                ){
                    throw new DomainException(
                        'Prize assignment idempotency conflict.'
                    );
                }

                return (int)$existing['id'];
            }

            $donation=DB::row(
                'select * from prize_donations
                 where id=? for update',
                [$donationId]
            ) ?? throw new DomainException(
                'Prize donation not found.'
            );

            if($donation['status']!=='available'){
                throw new DomainException(
                    'Only an available prize may be assigned.'
                );
            }

            $approvedUses=array_filter(
                explode(
                    ',',
                    (string)$donation['usage_designations']
                )
            );

            if(!in_array($eventType,$approvedUses,true)){
                throw new DomainException(
                    'That prize was not authorized for this event type.'
                );
            }

            if(
                (int)$donation['quantity_remaining']<$quantity
            ){
                throw new DomainException(
                    'Not enough prize inventory remains for that assignment.'
                );
            }

            DB::exec(
                'update prize_donations
                 set quantity_remaining=quantity_remaining-?
                 where id=?
                   and quantity_remaining>=?',
                [$quantity,$donationId,$quantity]
            );

            DB::exec(
                'insert into prize_assignments
                 (
                    prize_donation_id,
                    quantity,
                    event_type,
                    event_reference,
                    idempotency_key,
                    status,
                    assigned_by_admin_id
                 )
                 values (?,?,?,?,?,"assigned",?)',
                [
                    $donationId,
                    $quantity,
                    $eventType,
                    $eventReference!==''?$eventReference:null,
                    $idempotencyKey,
                    $adminId,
                ]
            );

            $assignmentId=(int)DB::id();

            $this->refreshStatus($donationId);

            return $assignmentId;
        });
    }

    public function award(
        int $assignmentId,
        int $adminId,
        ?int $winnerUserId,
        ?string $winnerName,
        ?string $winnerEmail
    ): bool {
        $winnerName=trim((string)$winnerName);
        $winnerEmail=trim((string)$winnerEmail);

        if(
            $winnerEmail!=='' &&
            !filter_var(
                $winnerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ){
            throw new InvalidArgumentException(
                'Winner email must be valid.'
            );
        }

        return $this->atomic(function()use(
            $assignmentId,
            $adminId,
            $winnerUserId,
            $winnerName,
            $winnerEmail
        ){
            $assignment=DB::row(
                'select * from prize_assignments
                 where id=? for update',
                [$assignmentId]
            );

            if(!$assignment){
                return false;
            }

            if($assignment['status']==='awarded'){
                return false;
            }

            if($assignment['status']!=='assigned'){
                throw new DomainException(
                    'Only an assigned prize may be awarded.'
                );
            }

            if($winnerUserId){
                if(!DB::row(
                    'select id from users
                     where id=? and status="active"',
                    [$winnerUserId]
                )){
                    throw new DomainException(
                        'Winner account was not found or is disabled.'
                    );
                }
            }

            if(
                !$winnerUserId &&
                $winnerName==='' &&
                $winnerEmail===''
            ){
                throw new InvalidArgumentException(
                    'Provide a Creative Moth user ID, winner name, or winner email.'
                );
            }

            DB::exec(
                'update prize_assignments
                 set status="awarded",
                     winner_user_id=?,
                     winner_name=?,
                     winner_email=?,
                     awarded_by_admin_id=?,
                     awarded_at=now()
                 where id=?',
                [
                    $winnerUserId?:null,
                    $winnerName!==''?$winnerName:null,
                    $winnerEmail!==''?$winnerEmail:null,
                    $adminId,
                    $assignmentId,
                ]
            );

            $this->refreshStatus(
                (int)$assignment['prize_donation_id']
            );

            return true;
        });
    }

    public function fulfill(
        int $assignmentId,
        int $adminId,
        string $notes=''
    ): bool {
        $notes=trim($notes);

        if(mb_strlen($notes)>5000){
            throw new InvalidArgumentException(
                'Fulfillment notes must be 5,000 characters or fewer.'
            );
        }

        return $this->atomic(function()use(
            $assignmentId,
            $adminId,
            $notes
        ){
            $assignment=DB::row(
                'select * from prize_assignments
                 where id=? for update',
                [$assignmentId]
            );

            if(!$assignment){
                return false;
            }

            if($assignment['status']==='fulfilled'){
                return false;
            }

            if($assignment['status']!=='awarded'){
                throw new DomainException(
                    'Only an awarded prize may be marked fulfilled.'
                );
            }

            DB::exec(
                'update prize_assignments
                 set status="fulfilled",
                     fulfilled_by_admin_id=?,
                     fulfilled_at=now(),
                     fulfillment_notes=?
                 where id=?',
                [
                    $adminId,
                    $notes!==''?$notes:null,
                    $assignmentId,
                ]
            );

            $this->refreshStatus(
                (int)$assignment['prize_donation_id']
            );

            return true;
        });
    }

    public function releaseAssignment(
        int $assignmentId
    ): bool {
        return $this->atomic(function()use($assignmentId){
            $assignment=DB::row(
                'select * from prize_assignments
                 where id=? for update',
                [$assignmentId]
            );

            if(!$assignment){
                return false;
            }

            if($assignment['status']==='cancelled'){
                return false;
            }

            if($assignment['status']!=='assigned'){
                throw new DomainException(
                    'An awarded or fulfilled prize cannot be returned to inventory.'
                );
            }

            $donation=DB::row(
                'select * from prize_donations
                 where id=? for update',
                [$assignment['prize_donation_id']]
            ) ?? throw new DomainException(
                'Prize donation not found.'
            );

            DB::exec(
                'update prize_assignments
                 set status="cancelled",
                     cancelled_at=now()
                 where id=?',
                [$assignmentId]
            );

            DB::exec(
                'update prize_donations
                 set quantity_remaining=
                     quantity_remaining+?
                 where id=?',
                [
                    (int)$assignment['quantity'],
                    (int)$donation['id'],
                ]
            );

            $this->refreshStatus(
                (int)$donation['id']
            );

            return true;
        });
    }

    private function refreshStatus(int $donationId): void
    {
        $donation=DB::row(
            'select * from prize_donations
             where id=? for update',
            [$donationId]
        );

        if(
            !$donation ||
            $donation['status']==='cancelled'
        ){
            return;
        }

        $totals=DB::row(
            'select
                coalesce(
                    sum(
                        case
                            when status="assigned"
                            then quantity
                            else 0
                        end
                    ),
                    0
                ) assigned_qty,
                coalesce(
                    sum(
                        case
                            when status="awarded"
                            then quantity
                            else 0
                        end
                    ),
                    0
                ) awarded_qty,
                coalesce(
                    sum(
                        case
                            when status="fulfilled"
                            then quantity
                            else 0
                        end
                    ),
                    0
                ) fulfilled_qty
             from prize_assignments
             where prize_donation_id=?',
            [$donationId]
        ) ?? [];

        $remaining=(int)$donation['quantity_remaining'];
        $donated=(int)$donation['quantity_donated'];

        $assigned=(int)($totals['assigned_qty']??0);
        $awarded=(int)($totals['awarded_qty']??0);
        $fulfilled=(int)($totals['fulfilled_qty']??0);

        if($remaining>0){
            $status='available';
        }elseif($fulfilled>=$donated){
            $status='fulfilled';
        }elseif(($awarded+$fulfilled)>=$donated){
            $status='awarded';
        }elseif($assigned>0){
            $status='assigned';
        }else{
            $status='available';
        }

        DB::exec(
            'update prize_donations
             set status=?
             where id=?',
            [$status,$donationId]
        );
    }

    public function totals(): array
    {
        $row=DB::row(
            'select
                count(*) donation_count,
                coalesce(sum(quantity_donated),0)
                    quantity_donated,
                coalesce(sum(quantity_remaining),0)
                    quantity_remaining,
                coalesce(
                    sum(status="submitted"),
                    0
                ) pending_review
             from prize_donations
             where status<>"cancelled"'
        ) ?? [];

        return [
            'donation_count'=>(int)($row['donation_count']??0),
            'quantity_donated'=>(int)($row['quantity_donated']??0),
            'quantity_remaining'=>(int)($row['quantity_remaining']??0),
            'pending_review'=>(int)($row['pending_review']??0),
        ];
    }
}
