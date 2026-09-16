<?php
namespace App\Services;

use App\Core\Database as DB;
use App\Core\Helpers as H;

final class CustomDesignService
{
    public const BRIEF_FIELDS=[
        'design_request'=>'Detailed design request',
        'required_text'=>'Required wording / names / text',
        'design_direction'=>'Theme / style / design direction',
        'color_preferences'=>'Color preferences',
        'dimensions'=>'Size / dimensions',
        'file_format'=>'Requested file format',
        'intended_use'=>'Intended use / license needs',
        'additional_notes'=>'Additional notes',
    ];

    public const STATES=['new','in_progress','proof_review','revision_requested','completed','cancelled','refunded'];
    private const HISTORICALLY_PAID=['paid','partially_refunded','refunded'];
    private const WORKFLOW_PAYMENTS=['paid','partially_refunded'];
    private const MAX_FILES=10;
    private const MAX_SIZE=26214400;
    private const IMAGE_MIMES=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];

    public static function transitionFor(string $state,string $action,string $side):?string
    {
        return match("$state:$action:$side"){'new:start:seller'=>'in_progress','revision_requested:start_revision:seller'=>'in_progress','in_progress:proof:seller'=>'proof_review','proof_review:revision:buyer'=>'revision_requested','proof_review:approve:buyer'=>'in_progress','in_progress:final:seller'=>'completed',default=>null};
    }

    public static function buyerFinalEligible(string $customStatus,string $paymentStatus):bool
    { return $customStatus==='completed'&&in_array($paymentStatus,['paid','partially_refunded'],true); }
    public static function retryEligible(?string $customStatus):bool{return $customStatus===null||$customStatus==='new';}
    public static function workflowPaymentEligible(string $paymentStatus):bool{return in_array($paymentStatus,self::WORKFLOW_PAYMENTS,true);}

    public static function containCommunicationFailure(callable $operation,?callable $reporter=null,?callable $fallback=null):bool
    {
        try{$operation();return true;}catch(\Throwable $error){try{($reporter??static fn(\Throwable $e)=>NotificationService::reportFailure('custom_order_communication',$e))($error);}catch(\Throwable){try{($fallback??static fn()=>error_log('Creative Moth custom-order communication and failure reporting failed.'))();}catch(\Throwable){}}return false;}
    }

    public function seller(int $userId):array
    { return DB::row('select * from designers where user_id=? and status="approved"',[$userId])??H::abort(403); }

    public function serviceLicenses(int $serviceId):array
    {
        $rows=DB::rows(
            'select c.*,lt.name platform_name,lt.description platform_description
             from custom_service_license_options c
             left join license_types lt on lt.id=c.license_type_id
             where c.custom_service_id=?
               and (c.license_type_id is null or lt.is_active=1)
             order by c.sort_order,c.id',
            [$serviceId]
        );

        return array_map(static function(array $row):array{
            $customName=trim((string)($row['custom_name']??''));
            $description=trim((string)($row['description']??''));

            return [
                'id'=>(int)$row['id'],
                'license_type_id'=>$row['license_type_id']===null
                    ? null
                    : (int)$row['license_type_id'],
                'license_key'=>(string)$row['license_key'],
                'name'=>$customName!==''
                    ? $customName
                    : (string)($row['platform_name']??'Custom License'),
                'description'=>$description!==''
                    ? $description
                    : (string)($row['platform_description']??''),
                'price'=>(float)$row['price'],
                'is_default'=>!empty($row['is_default']),
                'sort_order'=>(int)$row['sort_order'],
            ];
        },$rows);
    }

    private function normalizeServiceLicenses(array $input):array
    {
        $types=LicenseService::platformTypes();
        $enabled=(array)($input['license_enabled']??[]);
        $prices=(array)($input['license_price']??[]);
        $licenses=[];
        $errors=[];

        foreach($types as $type){
            $key=(string)$type['license_key'];

            if(
                $key!==LicenseService::PERSONAL_KEY &&
                !isset($enabled[$key])
            ){
                continue;
            }

            if((int)($type['id']??0)<=0){
                $errors[]='License settings are temporarily unavailable.';
                continue;
            }

            $price='0.00';

            if($key!==LicenseService::PERSONAL_KEY){
                $raw=trim((string)($prices[$key]??'0.00'));

                if($raw===''){
                    $raw='0.00';
                }

                if(!is_numeric($raw)||(float)$raw<0){
                    $errors[]=($type['name']??$key)
                        .' license price must be a valid non-negative amount.';
                    $raw='0.00';
                }

                $price=number_format((float)$raw,2,'.','');
            }

            $licenses[]=[
                'license_type_id'=>(int)$type['id'],
                'license_key'=>$key,
                'custom_name'=>null,
                'description'=>'',
                'price'=>$price,
                'is_default'=>$key===LicenseService::PERSONAL_KEY?1:0,
                'sort_order'=>(int)($type['sort_order']??0),
            ];
        }

        if(!empty($input['custom_license_enabled'])){
            $name=trim((string)($input['custom_license_name']??''));
            $terms=trim((string)($input['custom_license_terms']??''));
            $rawPrice=trim((string)($input['custom_license_price']??'0.00'));

            if($name===''){
                $errors[]='Custom license name is required.';
            }elseif(mb_strlen($name)>120){
                $errors[]='Custom license name must be 120 characters or fewer.';
            }

            if($terms===''){
                $errors[]='Custom license terms are required.';
            }elseif(mb_strlen($terms)>10000){
                $errors[]='Custom license terms must be 10,000 characters or fewer.';
            }

            if($rawPrice===''){
                $rawPrice='0.00';
            }

            if(!is_numeric($rawPrice)||(float)$rawPrice<0){
                $errors[]='Custom license price must be a valid non-negative amount.';
                $rawPrice='0.00';
            }

            $licenses[]=[
                'license_type_id'=>null,
                'license_key'=>'custom',
                'custom_name'=>$name,
                'description'=>$terms,
                'price'=>number_format((float)$rawPrice,2,'.',''),
                'is_default'=>0,
                'sort_order'=>1000,
            ];
        }

        $hasPersonal=false;

        foreach($licenses as $license){
            if($license['license_key']===LicenseService::PERSONAL_KEY){
                $hasPersonal=true;
                break;
            }
        }

        if(!$hasPersonal){
            $errors[]='Personal license must be available.';
        }

        return [$licenses,$errors];
    }

    private function selectedServiceLicenses(int $serviceId,mixed $keys):array
    {
        if(is_string($keys)){
            $keys=preg_split('/[,\s]+/',$keys)?:[];
        }

        if(!is_array($keys)){
            $keys=[];
        }

        $requested=[LicenseService::PERSONAL_KEY];

        foreach($keys as $key){
            $key=trim((string)$key);

            if($key!==''&&!in_array($key,$requested,true)){
                $requested[]=$key;
            }
        }

        $available=[];

        foreach($this->serviceLicenses($serviceId) as $license){
            $available[$license['license_key']]=$license;
        }

        $selected=[];

        foreach($requested as $key){
            if(!isset($available[$key])){
                return [];
            }

            $selected[]=$available[$key];
        }

        return $selected;
    }

    private static function serviceLicensePriceTotal(array $licenses):float
    {
        $total=0.00;

        foreach($licenses as $license){
            if(
                ($license['license_key']??'')
                !==LicenseService::PERSONAL_KEY
            ){
                $total+=(float)($license['price']??0);
            }
        }

        return round($total,2);
    }

    private static function serviceLicenseSnapshot(array $licenses):string
    {
        $snapshot=json_encode(
            array_map(
                static fn(array $license):array=>[
                    'key'=>$license['license_key'],
                    'name'=>$license['name'],
                    'description'=>$license['description']??'',
                    'included'=>$license['license_key']
                        ===LicenseService::PERSONAL_KEY,
                    'price'=>$license['license_key']
                        ===LicenseService::PERSONAL_KEY
                            ? 0.00
                            : (float)($license['price']??0),
                ],
                $licenses
            ),
            JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
        );

        return $snapshot===false?'[]':$snapshot;
    }

    private static function serviceLicenseDescriptionList(array $licenses):string
    {
        return implode(
            "\n\n",
            array_map(
                static fn(array $license):string =>
                    $license['name'].': '.($license['description']??''),
                $licenses
            )
        );
    }

    public function saveService(int $userId,?int $id,array $input,array $files):array
    {
        $seller=$this->seller($userId);

        $errors=[];
        $isDraft=(($input['save_mode']??'')==='draft');

        $title=trim((string)($input['title']??''));
        $description=trim((string)($input['description']??''));

        $priceRaw=trim((string)($input['price']??''));
        $daysRaw=trim((string)($input['turnaround_days']??''));
        $revisionsRaw=trim((string)($input['included_revisions']??''));

        $price=$priceRaw==='' ? 0.00 : round((float)$priceRaw,2);
        $days=$daysRaw==='' ? 0 : (int)$daysRaw;
        $revisions=$revisionsRaw==='' ? 0 : (int)$revisionsRaw;

        /*
         * Drafts may be incomplete.
         * Publishing / normal save still requires a complete valid listing.
         */
        if($isDraft){

            if(mb_strlen($title)>190){
                $errors[]='Title must be 190 characters or fewer.';
            }

            if(
                $priceRaw!=='' &&
                (!is_numeric($priceRaw)||(float)$priceRaw<0)
            ){
                $errors[]='Price must be a valid non-negative amount.';
            }

            if(
                $daysRaw!=='' &&
                ($days<0||$days>365)
            ){
                $errors[]='Turnaround must be between 0 and 365 days while saved as a draft.';
            }

            if(
                $revisionsRaw!=='' &&
                ($revisions<0||$revisions>100)
            ){
                $errors[]='Included revisions must be between 0 and 100.';
            }

        }else{

            if($title===''||mb_strlen($title)>190){
                $errors[]='Title is required and must be 190 characters or fewer.';
            }

            if(
                $priceRaw==='' ||
                !is_numeric($priceRaw) ||
                $price<0.50
            ){
                $errors[]='Price must be at least $0.50.';
            }

            if($days<1||$days>365){
                $errors[]='Turnaround must be between 1 and 365 days.';
            }

            if($revisions<0||$revisions>100){
                $errors[]='Included revisions must be between 0 and 100.';
            }
        }

        $briefFields=[];

        foreach(self::BRIEF_FIELDS as $key=>$label){

            if(!empty($input['brief_fields'][$key])){

                $briefFields[]=[
                    'key'=>$key,
                    'required'=>!empty(
                        $input['brief_required'][$key]
                    )?1:0
                ];
            }
        }

        $briefFieldsJson=json_encode(
            $briefFields,
            JSON_THROW_ON_ERROR
        );

        $isActive=$isDraft
            ?0
            :(!empty($input['is_active'])?1:0);

        /*
         * Drafts are allowed to contain unfinished license settings.
         * normalizeServiceLicenses still sanitizes anything that can
         * safely be stored, but its completeness errors only block
         * publishing.
         */
        [$licenses,$licenseErrors]=
            $this->normalizeServiceLicenses($input);

        if(!$isDraft){
            $errors=array_merge(
                $errors,
                $licenseErrors
            );
        }

        $questionCount=(int)(
            $input['question_count']
            ??count((array)($input['questions']??[]))
        );

        if($questionCount<0||$questionCount>20){
            $errors[]='Buyer questions must be between 0 and 20.';
        }

        $rawQuestions=array_values(
            (array)($input['questions']??[])
        );

        $questions=[];

        if($isDraft){

            /*
             * Save only questions that already contain text.
             * Empty unfinished question slots do not prevent a draft.
             */
            foreach(
                array_slice($rawQuestions,0,20)
                as $key=>$question
            ){
                $text=trim((string)$question);

                if($text===''){
                    continue;
                }

                $questions[]=[
                    'text'=>mb_substr($text,0,500),
                    'required'=>!empty(
                        $input['question_required'][$key]
                    )?1:0
                ];
            }

        }else{

            if(count($rawQuestions)!==$questionCount){
                $errors[]=
                    'Please complete each selected buyer question.';
            }

            foreach(
                array_slice($rawQuestions,0,20)
                as $key=>$question
            ){
                $text=trim((string)$question);

                if($text===''){
                    $errors[]=
                        'Buyer question '.($key+1).' cannot be blank.';
                    continue;
                }

                $questions[]=[
                    'text'=>mb_substr($text,0,500),
                    'required'=>!empty(
                        $input['question_required'][$key]
                    )?1:0
                ];
            }
        }

        try{
            $uploads=$this->validateUploads(
                $files['examples']??[],
                'image'
            );

        }catch(\InvalidArgumentException $e){

            $errors[]=$e->getMessage();
            $uploads=[];
        }

        if($errors){
            return $errors;
        }

        $stored=[];
        $remove=[];

        $extraProtection=
            !empty($input['extra_protection_watermark'])
                ?1
                :0;

        $protectionChanged=false;

        DB::begin();

        try{

            $existing=null;

            if($id){

                $existing=DB::row(
                    'select id,slug,extra_protection_watermark
                     from custom_design_services
                     where id=? and designer_id=?
                     for update',
                    [$id,$seller['id']]
                )??H::abort(404);

                $slug=(string)$existing['slug'];

                $protectionChanged=
                    (int)($existing['extra_protection_watermark']??0)
                    !==$extraProtection;

                /*
                 * A completely untitled new draft gets a temporary slug.
                 * Replace that temporary slug when it is first published.
                 */
                if(
                    !$isDraft &&
                    str_starts_with(
                        $slug,
                        'custom-design-draft-'
                    )
                ){
                    $base=H::slug($title);

                    if($base===''){
                        $base='custom-design';
                    }

                    $slug=$base.'-'.
                        substr(
                            bin2hex(random_bytes(4)),
                            0,
                            8
                        );
                }

                DB::exec(
                    'update custom_design_services
                     set slug=?,
                         title=?,
                         description=?,
                         price=?,
                         turnaround_days=?,
                         included_revisions=?,
                         buyer_instructions=?,
                         brief_fields=?,
                         extra_protection_watermark=?,
                         is_active=?
                     where id=?',
                    [
                        $slug,
                        $title,
                        $description,
                        $price,
                        $days,
                        $revisions,
                        trim(
                            (string)(
                                $input['buyer_instructions']
                                ??''
                            )
                        ),
                        $briefFieldsJson,
                        $extraProtection,
                        $isActive,
                        $id
                    ]
                );

            }else{

                $base=H::slug($title);

                if($base===''){
                    $base='custom-design-draft';
                }

                $slug=$base.'-'.
                    substr(
                        bin2hex(random_bytes(4)),
                        0,
                        8
                    );

                DB::exec(
                    'insert into custom_design_services
                     (
                         designer_id,
                         slug,
                         title,
                         description,
                         price,
                         turnaround_days,
                         included_revisions,
                         buyer_instructions,
                         brief_fields,
                         extra_protection_watermark,
                         is_active
                     )
                     values(?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $seller['id'],
                        $slug,
                        $title,
                        $description,
                        $price,
                        $days,
                        $revisions,
                        trim(
                            (string)(
                                $input['buyer_instructions']
                                ??''
                            )
                        ),
                        $briefFieldsJson,
                        $extraProtection,
                        $isActive
                    ]
                );

                $id=(int)DB::id();
            }

            DB::exec(
                'delete from custom_service_license_options
                 where custom_service_id=?',
                [$id]
            );

            foreach($licenses as $license){

                DB::exec(
                    'insert into custom_service_license_options
                     (
                         custom_service_id,
                         license_type_id,
                         license_key,
                         custom_name,
                         description,
                         price,
                         is_default,
                         sort_order
                     )
                     values(?,?,?,?,?,?,?,?)',
                    [
                        $id,
                        $license['license_type_id'],
                        $license['license_key'],
                        $license['custom_name'],
                        $license['description'],
                        $license['price'],
                        $license['is_default'],
                        $license['sort_order']
                    ]
                );
            }

            DB::exec(
                'delete from custom_service_questions
                 where custom_service_id=?',
                [$id]
            );

            foreach($questions as $n=>$q){

                DB::exec(
                    'insert into custom_service_questions
                     (
                         custom_service_id,
                         question_text,
                         is_required,
                         sort_order
                     )
                     values(?,?,?,?)',
                    [
                        $id,
                        $q['text'],
                        (int)$q['required'],
                        $n
                    ]
                );
            }

            $removeIds=array_values(
                array_filter(
                    array_map(
                        'intval',
                        (array)(
                            $input['remove_images']
                            ??[]
                        )
                    )
                )
            );

            if($removeIds){

                $marks=implode(
                    ',',
                    array_fill(
                        0,
                        count($removeIds),
                        '?'
                    )
                );

                $remove=DB::rows(
                    "select image_path
                     from custom_service_images
                     where custom_service_id=?
                       and id in ($marks)",
                    array_merge(
                        [$id],
                        $removeIds
                    )
                );

                DB::exec(
                    "delete from custom_service_images
                     where custom_service_id=?
                       and id in ($marks)",
                    array_merge(
                        [$id],
                        $removeIds
                    )
                );
            }

            foreach($uploads as $upload){

                $watermarkErrors=[];

                $saved=WatermarkService::storeCustomDesignPreview(
                    $upload,
                    $watermarkErrors,
                    (bool)$extraProtection
                );

                if(!$saved){
                    throw new \RuntimeException(
                        $watermarkErrors[0]
                        ??'Custom Design preview could not be watermarked.'
                    );
                }

                $path=$saved['image_path'];

                $stored[]=$saved['public_abs'];
                $stored[]=$saved['original_abs'];

                DB::exec(
                    'insert into custom_service_images
                     (
                         custom_service_id,
                         image_path,
                         sort_order
                     )
                     values(?,?,?)',
                    [
                        $id,
                        $path,
                        0
                    ]
                );
            }

            DB::commit();

            if($protectionChanged){
                $protectionFailures=0;

                foreach(
                    DB::rows(
                        'select image_path
                         from custom_service_images
                         where custom_service_id=?
                         order by id',
                        [$id]
                    )
                    as $preview
                ){
                    $result=
                        WatermarkService::regenerateCustomDesignPreview(
                            (string)$preview['image_path'],
                            (bool)$extraProtection
                        );

                    if(!$result['ok']){
                        $protectionFailures++;

                        error_log(
                            'Custom Design extra-protection regeneration failed '
                            .'for service '.$id.': '
                            .($result['message']??'Unknown error')
                        );
                    }
                }

                if($protectionFailures>0){
                    error_log(
                        'Custom Design '.$id.' saved with '
                        .$protectionFailures
                        .' preview regeneration failure(s).'
                    );
                }
            }

            foreach($remove as $old){
                $this->unlinkPublic(
                    $old['image_path']
                );
            }

            return [];

        }catch(\Throwable $e){

            if(DB::pdo()->inTransaction()){
                DB::rollBack();
            }

            foreach($stored as $path){
                @unlink($path);
            }

            throw $e;
        }
    }

    public function deleteService(int $userId,int $id):void
    {
        $seller=$this->seller($userId);

        $service=DB::row(
            'select id
             from custom_design_services
             where id=? and designer_id=?',
            [$id,$seller['id']]
        )??H::abort(404);

        if(DB::row(
            'select id
             from custom_orders
             where custom_service_id=?
             limit 1',
            [$id]
        )){
            throw new \DomainException(
                'This Custom Design has order history and cannot be permanently deleted. Save it as Draft instead so existing customer orders remain intact.'
            );
        }

        $images=DB::rows(
            'select image_path
             from custom_service_images
             where custom_service_id=?',
            [$id]
        );

        DB::begin();

        try{
            DB::exec(
                'delete from custom_design_services
                 where id=? and designer_id=?',
                [$id,$seller['id']]
            );

            if(
                (int)DB::pdo()->query('select row_count()')->fetchColumn() !== 1
            ){
                throw new \RuntimeException(
                    'Custom Design could not be deleted.'
                );
            }

            DB::commit();

        }catch(\Throwable $e){

            if(DB::pdo()->inTransaction()){
                DB::rollBack();
            }

            throw $e;
        }

        foreach($images as $image){
            $this->unlinkPublic((string)$image['image_path']);
        }
    }

    public function prepareCheckout(
        array $service,
        int $buyerId,
        array $input,
        array $files
    ):string
    {
        if(
            !$buyerId ||
            $buyerId === (int)$service['seller_user_id']
        ){
            H::abort(403);
        }

        $errors=[];

        $briefConfig=json_decode(
            (string)($service['brief_fields']??'[]'),
            true
        );

        if(!is_array($briefConfig)){
            $briefConfig=[];
        }

        $enabledBriefFields=[];

        foreach($briefConfig as $field){

            $key=(string)($field['key']??'');

            if(
                !isset(self::BRIEF_FIELDS[$key]) ||
                isset($enabledBriefFields[$key])
            ){
                continue;
            }

            $enabledBriefFields[$key]=[
                'required'=>!empty($field['required'])
            ];

            if(
                $enabledBriefFields[$key]['required'] &&
                trim((string)($input[$key]??''))===''
            ){
                $errors[]=self::BRIEF_FIELDS[$key].' is required.';
            }
        }

        foreach(
            DB::rows(
                'select *
                 from custom_service_questions
                 where custom_service_id=?
                 order by sort_order,id',
                [$service['id']]
            ) as $q
        ){
            $answer=trim(
                (string)($input['answers'][$q['id']]??'')
            );

            if(
                $q['is_required'] &&
                $answer===''
            ){
                $errors[]='Please answer: '.$q['question_text'];
            }
        }

        $selectedLicenses=$this->selectedServiceLicenses(
            (int)$service['id'],
            $input['license_type']??[]
        );

        if(!$selectedLicenses){
            $errors[]=
                'Please choose only licenses currently available for this custom design.';
        }

        try{
            $uploads=$this->validateUploads(
                $files['references']??[],
                'image_or_pdf'
            );
        }catch(\InvalidArgumentException $e){
            $errors[]=$e->getMessage();
            $uploads=[];
        }

        if($errors){
            throw new \InvalidArgumentException(
                implode(' ',$errors)
            );
        }

        $this->cleanupExpiredCheckouts();

        $token=bin2hex(random_bytes(24));

        $dir=app_path(
            'storage/protected_uploads/custom_designs/checkout_temp/'.$token
        );

        if(
            $uploads &&
            !is_dir($dir) &&
            !mkdir($dir,0750,true) &&
            !is_dir($dir)
        ){
            throw new \RuntimeException(
                'Secure checkout upload storage is unavailable.'
            );
        }

        $stored=[];

        try{

            foreach($uploads as $upload){

                $name=bin2hex(random_bytes(24)).'.'.$upload['ext'];
                $absolute=$dir.'/'.$name;

                if(
                    !move_uploaded_file(
                        $upload['tmp'],
                        $absolute
                    )
                ){
                    throw new \RuntimeException(
                        'Reference file could not be prepared for checkout.'
                    );
                }

                @chmod($absolute,0640);

                $stored[]=[
                    'name'=>$upload['original_name'],
                    'path'=>$absolute,
                    'size'=>$upload['size'],
                    'mime'=>$upload['mime'],
                    'ext'=>$upload['ext']
                ];
            }

        }catch(\Throwable $e){

            foreach($stored as $file){
                @unlink($file['path']);
            }

            @rmdir($dir);

            throw $e;
        }

        $savedInput=[];

        foreach(self::BRIEF_FIELDS as $key=>$label){
            $savedInput[$key]=trim(
                (string)($input[$key]??'')
            );
        }

        $answers=[];

        foreach((array)($input['answers']??[]) as $key=>$value){
            $answers[(int)$key]=mb_substr(
                trim((string)$value),
                0,
                10000
            );
        }

        $savedInput['answers']=$answers;

        $savedInput['license_type']=array_values(
            array_map(
                'strval',
                (array)($input['license_type']??[])
            )
        );

        $_SESSION['custom_design_checkout']??=[];

        $_SESSION['custom_design_checkout'][$token]=[
            'buyer_id'=>$buyerId,
            'service_id'=>(int)$service['id'],
            'slug'=>(string)$service['slug'],
            'created_at'=>time(),
            'input'=>$savedInput,
            'references'=>$stored
        ];

        return $token;
    }

    public function checkoutPayload(
        string $token,
        int $buyerId,
        int $serviceId
    ):array
    {
        $this->cleanupExpiredCheckouts();

        $payload=
            $_SESSION['custom_design_checkout'][$token]
            ??null;

        if(
            !$payload ||
            (int)($payload['buyer_id']??0)!==$buyerId ||
            (int)($payload['service_id']??0)!==$serviceId
        ){
            H::abort(404);
        }

        return $payload;
    }

    public function preparedCheckoutFiles(array $payload):array
    {
        $files=[
            'name'=>[],
            'error'=>[],
            'tmp_name'=>[],
            'size'=>[]
        ];

        foreach(
            (array)($payload['references']??[])
            as $reference
        ){
            $files['name'][]=(string)$reference['name'];
            $files['error'][]=UPLOAD_ERR_OK;
            $files['tmp_name'][]=(string)$reference['path'];
            $files['size'][]=(int)$reference['size'];
        }

        return ['references'=>$files];
    }

    public function cleanupCheckout(string $token):void
    {
        if(!preg_match('/^[a-f0-9]{48}$/',$token)){
            return;
        }

        $payload=
            $_SESSION['custom_design_checkout'][$token]
            ??null;

        if($payload){

            foreach(
                (array)($payload['references']??[])
                as $reference
            ){
                $path=(string)($reference['path']??'');

                if($this->isPreparedCheckoutFile($path)){
                    @unlink($path);
                }
            }
        }

        $dir=app_path(
            'storage/protected_uploads/custom_designs/checkout_temp/'.$token
        );

        if(is_dir($dir)){
            @rmdir($dir);
        }

        unset(
            $_SESSION['custom_design_checkout'][$token]
        );
    }

    private function cleanupExpiredCheckouts():void
    {
        foreach(
            (array)($_SESSION['custom_design_checkout']??[])
            as $token=>$payload
        ){
            if(
                time()-
                (int)($payload['created_at']??0)
                >7200
            ){
                $this->cleanupCheckout(
                    (string)$token
                );
            }
        }
    }

    private function isPreparedCheckoutFile(string $path):bool
    {
        if($path===''){
            return false;
        }

        $base=realpath(
            app_path(
                'storage/protected_uploads/custom_designs/checkout_temp'
            )
        );

        $real=realpath($path);

        return
            $base &&
            $real &&
            is_file($real) &&
            str_starts_with(
                $real,
                $base.DIRECTORY_SEPARATOR
            );
    }

    public function submitRequest(array $service,int $buyerId,array $input,array $files):int
    {
        if(!$buyerId||$buyerId===(int)$service['seller_user_id'])H::abort(403);
        $errors=[];
        $briefConfig=json_decode((string)($service['brief_fields']??'[]'),true);
        if(!is_array($briefConfig))$briefConfig=[];
        $enabledBriefFields=[];
        foreach($briefConfig as $field){
            $key=(string)($field['key']??'');
            if(!isset(self::BRIEF_FIELDS[$key])||isset($enabledBriefFields[$key]))continue;
            $enabledBriefFields[$key]=[
                'required'=>!empty($field['required'])
            ];
            if(
                $enabledBriefFields[$key]['required'] &&
                trim((string)($input[$key]??''))===''
            ){
                $errors[]=self::BRIEF_FIELDS[$key].' is required.';
            }
        }
        $answers=[];foreach(DB::rows('select * from custom_service_questions where custom_service_id=? order by sort_order,id',[$service['id']]) as $q){$answer=trim((string)($input['answers'][$q['id']]??''));if($q['is_required']&&$answer==='')$errors[]='Please answer: '.$q['question_text'];$answers[]=['question_id'=>(int)$q['id'],'question'=>$q['question_text'],'required'=>(bool)$q['is_required'],'answer'=>$answer];}
        $uploads=$this->validateUploads($files['references']??[],'image_or_pdf');
        if($errors)throw new \InvalidArgumentException(implode(' ',$errors));
        $brief=[];
        foreach($enabledBriefFields as $key=>$config){
            $brief[$key]=trim((string)($input[$key]??''));
        }
        $brief['answers']=$answers;
        $billing=StripeService::normalizeBillingAddress(['line1'=>trim($input['billing_line1']??''),'line2'=>trim($input['billing_line2']??''),'city'=>trim($input['billing_city']??''),'state'=>trim($input['billing_state']??''),'postal_code'=>trim($input['billing_postal_code']??''),'country'=>strtoupper(trim($input['billing_country']??'US'))]);$checkout=new CheckoutOrderService;
        $selectedLicenses=$this->selectedServiceLicenses(
            (int)$service['id'],
            $input['license_type']??[]
        );

        if(!$selectedLicenses){
            throw new \InvalidArgumentException(
                'Please choose only licenses currently available for this custom design.'
            );
        }

        $basePrice=CreditService::formatCents(
            CreditService::parseCents((string)$service['price'])
        );

        $licensePrice=CreditService::formatCents(
            CreditService::parseCents(
                number_format(
                    self::serviceLicensePriceTotal($selectedLicenses),
                    2,
                    '.',
                    ''
                )
            )
        );

        $price=CreditService::formatCents(
            CreditService::parseCents($basePrice)
            + CreditService::parseCents($licensePrice)
        );

        $licenseSnapshot=self::serviceLicenseSnapshot($selectedLicenses);

        $licenseName=mb_substr(
            implode(', ',array_column($selectedLicenses,'name')),
            0,
            120
        );

        $licenseDescription=
            self::serviceLicenseDescriptionList($selectedLicenses);
$identity='custom-tax:'.$buyerId.':'.hash('sha256',json_encode([(int)$service['id'],$price,$billing],JSON_THROW_ON_ERROR));$tax=$checkout->calculateTax([['id'=>'custom-'.$service['id'],'total_price'=>$price]],$billing,$identity);$taxAmount=CreditService::formatCents($tax['tax_cents']);$grossCents=CreditService::parseCents($price)+$tax['tax_cents'];if((int)$tax['total_cents']!==$grossCents)throw new \RuntimeException('Stripe Tax total did not match the authoritative custom-service snapshot.');$creditService=new CreditService;$available=$creditService->balances($buyerId)['available'];$breakdown=CreditService::checkoutBreakdown($price,'0.00',$taxAmount,$available,($input['use_credits']??'')==='1');$creditCents=$breakdown['credit_cents'];$credits=CreditService::formatCents($creditCents);$total=CreditService::formatCents($breakdown['final_cents']);$rate=StripeService::commissionRate();$stored=[];
        DB::begin();try{
            $fresh=DB::row('select s.*,d.display_name,d.user_id seller_user_id from custom_design_services s join designers d on d.id=s.designer_id where s.id=? and s.is_active=1 and d.status="approved" for update',[$service['id']]);if(
                !$fresh ||
                CreditService::formatCents(
                    CreditService::parseCents((string)$fresh['price'])
                )!==$basePrice
            ){
                throw new \DomainException(
                    'The service price changed. Review the service and try again.'
                );
            }

            $freshLicenses=$this->selectedServiceLicenses(
                (int)$fresh['id'],
                array_column($selectedLicenses,'license_key')
            );

            if(
                !$freshLicenses ||
                self::serviceLicenseSnapshot($freshLicenses)!==$licenseSnapshot
            ){
                throw new \DomainException(
                    'The license options changed. Review the service and try again.'
                );
            }
            $fee=(MarketplaceFeeService::configured()->calculate([['id'=>1,'seller_id'=>(int)$fresh['designer_id'],'gross_cents'=>CreditService::parseCents($price)]]))[(int)$fresh['designer_id']];
            $order=$checkout->createOrder(['user_id'=>$buyerId,'subtotal'=>$price,'tax_amount'=>$taxAmount,'tax_snapshot'=>$tax['snapshot'],'tax_calculation_id'=>$tax['id'],'billing_snapshot'=>json_encode($billing,JSON_THROW_ON_ERROR),'credits'=>$credits,'total'=>$total,'currency'=>StripeService::currency(),'amount_cents'=>CreditService::parseCents($total),'stripe_paid_amount'=>'0.00','commission_total'=>CreditService::formatCents($fee['fee_cents'])]);DB::exec('update orders set marketplace_fee_model="percentage_plus_fixed",marketplace_fee_basis_points=?,marketplace_fixed_fee_cents=? where id=?',[StripeService::commissionBasisPoints(),StripeService::commissionFixedCents(),$order]);if($creditCents>0){$credits=$creditService->reserve($buyerId,$credits,$order,'order:'.$order.':credit:reserve');$creditCents=CreditService::parseCents($credits);$total=CreditService::formatCents($grossCents-$creditCents);DB::exec('update orders set credits_applied=?,credit_reserved=?,credit_payment_status=?,total=?,stripe_amount_total=?,stripe_paid_amount=? where id=?',[$credits,$credits,$creditCents>0?'reserved':'none',$total,$grossCents-$creditCents,'0.00',$order]);}
            $item=$checkout->addItem([
                'order_id'=>$order,
                'custom_service_id'=>$fresh['id'],
                'title'=>$fresh['title'],
                'slug'=>$fresh['slug'],
                'designer_id'=>$fresh['designer_id'],
                'seller_name'=>$fresh['display_name'],
                'license_type'=>'custom-design',
                'license_name'=>$licenseName,
                'license_price'=>$licensePrice,
                'license_description'=>$licenseDescription,
                'license_snapshot'=>$licenseSnapshot,
                'fulfillment_type'=>'custom_design',
                'unit_price'=>$basePrice,
                'total_price'=>$price,
                'commission_rate'=>$rate
            ]);
            $itemFee=$fee['items'][1];DB::exec('update order_items set platform_commission_amount=?,marketplace_percentage_fee_amount=?,marketplace_fixed_fee_amount=?,seller_payout_amount=? where id=?',[CreditService::formatCents($itemFee['fee_cents']),CreditService::formatCents($itemFee['percentage_fee_cents']),CreditService::formatCents($itemFee['fixed_fee_cents']),CreditService::formatCents($itemFee['seller_earnings_cents']),$item]);
            DB::exec('insert into custom_orders(order_id,order_item_id,custom_service_id,buyer_user_id,designer_id,status,service_snapshot,brief_snapshot,agreed_price,turnaround_days,included_revisions) values(?,?,?,?,?,"new",?,?,?,?,?)',[$order,$item,$fresh['id'],$buyerId,$fresh['designer_id'],json_encode(['title'=>$fresh['title'],'description'=>$fresh['description'],'buyer_instructions'=>$fresh['buyer_instructions'],'brief_fields'=>$briefConfig,'licenses'=>json_decode($licenseSnapshot,true)],JSON_THROW_ON_ERROR),json_encode($brief,JSON_THROW_ON_ERROR),$price,$fresh['turnaround_days'],$fresh['included_revisions']]);$custom=(int)DB::id();
            foreach($uploads as $upload){$path=$this->store($upload,'references',true);$stored[]=app_path('storage/protected_uploads/'.$path);DB::exec('insert into custom_order_files(custom_order_id,uploader_user_id,file_kind,original_name,storage_path,mime_type,file_size) values(?, ?,"reference",?,?,?,?)',[$custom,$buyerId,$upload['original_name'],$path,$upload['mime'],$upload['size']]);}DB::exec('insert into seller_earnings(order_id,product_id,designer_id,buyer_id,gross_sale,marketplace_commission,seller_earning,status) values (?,null,?,?,?,?,?,"pending_payment")',[$order,$fresh['designer_id'],$buyerId,$price,CreditService::formatCents($fee['fee_cents']),CreditService::formatCents($fee['seller_earnings_cents'])]);if($fee['capped'])DB::exec('insert ignore into seller_financial_adjustments(order_id,designer_id,adjustment_type,event_key,note) values (?,? ,"fee_cap_warning",?,?)',[$order,$fresh['designer_id'],'fee-cap:order:'.$order.':seller:'.$fresh['designer_id'],'Marketplace fee was capped at seller gross; seller earnings were not negative.']);$created=DB::row('select * from orders where id=?',[$order]);$items=DB::rows('select * from order_items where order_id=?',[$order]);if(CreditService::parseCents($total)===0){$finalizer=new OrderFinalizationService;$finalizer->finalize($order,'internal-credit-order:'.$order,true);DB::commit();if(!empty($input['checkout_token']))$this->cleanupCheckout((string)$input['checkout_token']);$finalizer->communicate($order);H::redirect('/dashboard/order/'.$order);}$session=$checkout->checkout($created,$items);DB::exec('update orders set stripe_checkout_session_id=?,stripe_payment_status="pending" where id=?',[$session['id']??null,$order]);DB::commit();if(!empty($input['checkout_token']))$this->cleanupCheckout((string)$input['checkout_token']);header('Location: '.$session['url'],true,303);exit;
        }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();foreach($stored as $path)@unlink($path);throw$e;}
    }

    public function transition(array $order,int $userId,string $action,array $files=[]):void
    {
        $stored=[];$historyId=0;$next='';$kind=null;DB::begin();try{$current=DB::row('select co.*,o.payment_status,d.user_id seller_user_id from custom_orders co join orders o on o.id=co.order_id join designers d on d.id=co.designer_id where co.id=? for update',[$order['id']])??H::abort(404);$seller=$userId===(int)$current['seller_user_id'];$buyer=$userId===(int)$current['buyer_user_id'];if(!$seller&&!$buyer)H::abort(404);if(!self::workflowPaymentEligible($current['payment_status']))throw new \DomainException('Payment must remain eligible for active custom work.');
            $side=$seller?'seller':'buyer';$next=self::transitionFor($current['status'],$action,$side);if($next===null)throw new \DomainException('The custom order changed; refresh before trying that action.');$from=$current['status'];$kind=$action==='proof'?'proof':($action==='final'?'final':null);$uploads=$kind?$this->validateUploads($files[$kind]??[],$kind==='proof'?'proof_image':'delivery'):[];if($kind&&!$uploads)throw new \InvalidArgumentException('A protected file is required.');$revision=$action==='revision';DB::exec('update custom_orders set status=?,revisions_used=revisions_used+?,completed_at=case when ?="completed" then now() else completed_at end where id=? and status=?',[$next,$revision?1:0,$next,$current['id'],$from]);if(DB::pdo()->query('select row_count()')->fetchColumn()!=1)throw new \DomainException('The custom order changed; refresh and try again.');
            DB::exec('insert into custom_order_status_history(custom_order_id,from_status,to_status,actor_user_id,transition_source,note) values(?,?,?,?,"user",?)',[$current['id'],$from,$next,$userId,mb_substr(trim($_POST['note']??''),0,1000)]);$historyId=(int)DB::id();foreach($uploads as $upload){
                if($kind==='proof'){
                    $watermarkErrors=[];

                    $saved=WatermarkService::storeCustomProof(
                        $upload,
                        $watermarkErrors
                    );

                    if(!$saved){
                        throw new \RuntimeException(
                            $watermarkErrors[0]
                            ??'Proof could not be watermarked.'
                        );
                    }

                    $path=$saved['storage_path'];
                    $size=(int)$saved['file_size'];

                    $stored[]=$saved['protected_abs'];
                    $stored[]=$saved['original_abs'];

                }else{
                    $path=$this->store(
                        $upload,
                        $kind.'s',
                        true
                    );

                    $size=(int)$upload['size'];

                    $stored[]=
                        app_path(
                            'storage/protected_uploads/'.$path
                        );
                }

                DB::exec(
                    'insert into custom_order_files
                     (
                         custom_order_id,
                         uploader_user_id,
                         file_kind,
                         original_name,
                         storage_path,
                         mime_type,
                         file_size,
                         revision_number
                     )
                     values(?,?,?,?,?,?,?,?)',
                    [
                        $current['id'],
                        $userId,
                        $kind,
                        $upload['original_name'],
                        $path,
                        $upload['mime'],
                        $size,
                        (int)$current['revisions_used']
                    ]
                );
            }DB::commit();
        }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::rollBack();foreach($stored as $path)@unlink($path);throw$e;}
        $recipient=$userId===(int)$current['seller_user_id']?(int)$current['buyer_user_id']:(int)$current['seller_user_id'];$audience=$recipient===(int)$current['buyer_user_id']?'buyer':'designer';$title=['proof'=>'Proof available','revision'=>'Revision requested','approve'=>'Proof approved','final'=>'Final file available'][$action]??'Custom-order status changed';self::containCommunicationFailure(fn()=>NotificationService::create($recipient,$action==='final'?'custom_final_available':'custom_order_status',$audience,$title,'Custom order #'.$current['id'].' is now '.str_replace('_',' ',$next).'.','custom-order-history:'.$historyId.':recipient:'.$recipient,$audience==='buyer'?'/buyer/custom-orders/'.$current['id']:'/seller/custom-orders/'.$current['id']),static fn(\Throwable $e)=>NotificationService::reportFailure('custom_order_notification_'.$historyId,$e));if($action==='final')self::containCommunicationFailure(fn()=>EmailQueueService::customFinalAvailable((int)$current['id'],$historyId),static fn(\Throwable $e)=>NotificationService::reportFailure('custom_order_final_email_'.$historyId,$e));
    }

    public function systemTransitionByOrder(int $orderId,string $to,string $source):bool
    {
        if(!in_array($to,['cancelled','refunded'],true)||!in_array($source,['stripe_cancel','stripe_expired','stripe_refund'],true))throw new \InvalidArgumentException('Invalid system transition.');$owns=!DB::pdo()->inTransaction();if($owns)DB::begin();try{$o=DB::row('select * from custom_orders where order_id=? for update',[$orderId]);if(!$o){if($owns)DB::commit();return false;}$from=$o['status'];if(in_array($from,['cancelled','refunded'],true)&&$from!==$to||$from==='completed'&&$to!=='refunded'){if($owns)DB::commit();return false;}$changed=$from!==$to;if($changed)DB::exec('update custom_orders set status=? where id=? and status=?',[$to,$o['id'],$from]);$key=$source.':'.$to;DB::exec('insert ignore into custom_order_status_history(custom_order_id,from_status,to_status,actor_user_id,transition_source,system_event_key,note) values(?,?,?,null,?,?,?)',[$o['id'],$from,$to,$source,$key,'Payment lifecycle transition.']);$historyAdded=(int)DB::pdo()->query('select row_count()')->fetchColumn()===1;if($owns)DB::commit();return $changed||$historyAdded;}catch(\Throwable $e){if($owns&&DB::pdo()->inTransaction())DB::rollBack();throw$e;}
    }

    public function order(int $id,int $userId,?string $side=null):array
    {$row=DB::row('select co.*,o.payment_status,o.total,d.user_id seller_user_id,u.name buyer_name,d.display_name,oi.license_name,oi.license_price,oi.license_description,oi.license_snapshot from custom_orders co join orders o on o.id=co.order_id join order_items oi on oi.id=co.order_item_id join designers d on d.id=co.designer_id join users u on u.id=co.buyer_user_id where co.id=?',[$id])??H::abort(404);if($side==='seller'&&($userId!==(int)$row['seller_user_id']||!in_array($row['payment_status'],self::HISTORICALLY_PAID,true))||$side==='buyer'&&$userId!==(int)$row['buyer_user_id'])H::abort(404);return$row;}
    public function file(int $id,int $userId):array
    {$f=DB::row('select f.*,co.buyer_user_id,co.status,d.user_id seller_user_id,o.payment_status from custom_order_files f join custom_orders co on co.id=f.custom_order_id join orders o on o.id=co.order_id join designers d on d.id=co.designer_id where f.id=?',[$id])??H::abort(404);$buyer=$userId===(int)$f['buyer_user_id'];$seller=$userId===(int)$f['seller_user_id']&&in_array($f['payment_status'],self::HISTORICALLY_PAID,true);if(!$seller&&!$buyer||$buyer&&$f['file_kind']==='final'&&!self::buyerFinalEligible($f['status'],$f['payment_status']))H::abort(404);return$f;}

    private function validateUploads(array $files,string $mode):array
    {
        if(!$files || empty($files['name'])) {
            return [];
        }

        foreach(['name','error','tmp_name','size'] as $key) {
            if(
                !is_array($files[$key] ?? null) ||
                array_keys($files[$key]) !== array_keys($files['name'])
            ) {
                throw new \InvalidArgumentException('Malformed upload.');
            }
        }

        if(count($files['name']) > self::MAX_FILES) {
            throw new \InvalidArgumentException(
                'Upload no more than '.self::MAX_FILES.' files at once.'
            );
        }

        $out = [];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach(array_keys($files['name']) as $i) {

            $name = (string)$files['name'][$i];
            $error = $files['error'][$i];

            if($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $tmp = $files['tmp_name'][$i];
            $size = (int)$files['size'][$i];

            if(
                $error !== UPLOAD_ERR_OK ||
                !is_string($tmp) ||
                (!is_uploaded_file($tmp)&&!$this->isPreparedCheckoutFile($tmp)) ||
                $size < 1 ||
                $size > self::MAX_SIZE
            ) {
                throw new \InvalidArgumentException(
                    'Uploads must be genuine files no larger than 25MB.'
                );
            }

            $ext = strtolower(
                pathinfo($name, PATHINFO_EXTENSION)
            );

            /*
             * Seller-facing Custom Design preview images intentionally
             * follow the SAME acceptance rules as regular product previews.
             *
             * Android images must not be subjected to the stricter
             * protected-file container checks used for buyer references,
             * proofs and final delivery.
             */
            if($mode === 'image') {

                if(
                    !in_array(
                        $ext,
                        ['jpg','jpeg','png','webp'],
                        true
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'Preview images must be valid JPG, PNG, or WEBP files.'
                    );
                }

                $image = @getimagesize($tmp);

                if(!$image) {
                    throw new \InvalidArgumentException(
                        'Preview images must be valid JPG, PNG, or WEBP files.'
                    );
                }

                $mime = (string)(
                    $finfo->file($tmp) ?: ''
                );

                $expected = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ][$ext];

                if(
                    !hash_equals($expected, $mime) ||
                    !hash_equals(
                        $expected,
                        (string)($image['mime'] ?? '')
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'Preview images must be valid JPG, PNG, or WEBP files.'
                    );
                }

                $out[] = [
                    'tmp' => $tmp,
                    'original_name' => mb_substr(
                        basename($name),
                        0,
                        190
                    ),
                    'size' => $size,
                    'mime' => $mime,
                    'ext' => $ext,
                ];

                continue;
            }

            if($mode === 'delivery') {

                if(
                    $ext === '' ||
                    !preg_match('/^[a-z0-9]{1,12}$/', $ext)
                ) {
                    throw new \InvalidArgumentException(
                        'Final delivery requires a safe filename extension.'
                    );
                }

                $out[] = [
                    'tmp' => $tmp,
                    'original_name' => mb_substr(
                        basename($name),
                        0,
                        190
                    ),
                    'size' => $size,
                    'mime' => 'application/octet-stream',
                    'ext' => $ext,
                ];

                continue;
            }

            $mime = (string)(
                $finfo->file($tmp) ?: ''
            );

            $allowed = self::IMAGE_MIMES;

            if($mode === 'image_or_pdf') {
                $allowed['application/pdf'] = 'pdf';
            }

            if(!isset($allowed[$mime])) {
                throw new \InvalidArgumentException(
                    'Upload a genuine JPG, PNG, WEBP'.
                    ($mode === 'image_or_pdf' ? ' or PDF' : '').
                    ' file.'
                );
            }

            $ext = $allowed[$mime];

            if($mime !== 'application/pdf') {

                $bytes = file_get_contents($tmp);
                $image = @getimagesizefromstring($bytes);

                if(
                    !$image ||
                    !hash_equals(
                        $mime,
                        (string)$image['mime']
                    ) ||
                    !SellerReceiptService::sourceDimensionsAllowed(
                        (int)$image[0],
                        (int)$image[1]
                    ) ||
                    !SellerReceiptService::hasExactImageContainer(
                        $bytes,
                        $mime
                    )
                ) {
                    throw new \InvalidArgumentException(
                        'Image validation failed.'
                    );
                }
            }

            $out[] = [
                'tmp' => $tmp,
                'original_name' => mb_substr(
                    basename($name),
                    0,
                    190
                ),
                'size' => $size,
                'mime' => $mime,
                'ext' => $ext,
            ];
        }

        return $out;
    }

    private function store(array $file,string $folder,bool $protected):string{$relative='custom_designs/'.$folder.'/'.bin2hex(random_bytes(24)).'.'.$file['ext'];$base=$protected?app_path('storage/protected_uploads'):public_path('uploads');$path=$base.'/'.$relative;if(!is_dir(dirname($path))&&!mkdir(dirname($path),$protected?0750:0755,true)&&!is_dir(dirname($path)))throw new \RuntimeException('Upload storage is unavailable.');$saved=is_uploaded_file($file['tmp'])
    ?move_uploaded_file($file['tmp'],$path)
    :($this->isPreparedCheckoutFile($file['tmp'])
        ?copy($file['tmp'],$path)
        :false);
if(!$saved)throw new \RuntimeException('Could not securely store upload.');return($protected?'':'/uploads/').$relative;}
    private function unlinkPublic(string $path):void
    {
        WatermarkService::deleteCustomDesignPreview($path);
    }
}
