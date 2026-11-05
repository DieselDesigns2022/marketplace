<?php
namespace App\Services;

use App\Core\Database as DB;

final class FeedbackAttachmentService
{
    private const MAX_FILES=3;
    private const MAX_SIZE=8388608;

    private static function validate(array $files): array
    {
        if(!isset($files['name']))return [];
        foreach(['name','tmp_name','size','error'] as $key){
            if(!isset($files[$key])||!is_array($files[$key])){
                throw new \InvalidArgumentException('Invalid attachment upload.');
            }
        }

        if(count($files['name'])>self::MAX_FILES){
            throw new \InvalidArgumentException('Maximum 3 attachments allowed.');
        }

        $result=[];
        $finfo=new \finfo(FILEINFO_MIME_TYPE);
        $allowed=[
            'jpg'=>'image/jpeg',
            'jpeg'=>'image/jpeg',
            'png'=>'image/png',
            'webp'=>'image/webp',
            'pdf'=>'application/pdf'
        ];

        foreach($files['name'] as $i=>$name){
            $error=$files['error'][$i]??UPLOAD_ERR_NO_FILE;
            if($error===UPLOAD_ERR_NO_FILE)continue;
            if($error!==UPLOAD_ERR_OK){
                throw new \InvalidArgumentException('Attachment upload failed.');
            }

            $tmp=$files['tmp_name'][$i]??'';
            $size=$files['size'][$i]??0;
            if(!is_string($name)||!is_string($tmp)||
               !is_int($size)||$size<1||$size>self::MAX_SIZE||
               !is_uploaded_file($tmp)||filesize($tmp)!==$size){
                throw new \InvalidArgumentException('Each attachment must be 8 MB or smaller.');
            }

            $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
            $mime=$finfo->file($tmp);
            if(!isset($allowed[$ext])||$allowed[$ext]!==$mime){
                throw new \InvalidArgumentException('Only JPG, PNG, WEBP, and PDF files are allowed.');
            }

            if($ext==='pdf'){
                $handle=fopen($tmp,'rb');
                $header=fread($handle,5);
                fclose($handle);
                if($header!=='%PDF-'){
                    throw new \InvalidArgumentException('Invalid PDF attachment.');
                }
            }else{
                $image=@getimagesize($tmp);
                if(!$image||($image['mime']??'')!==$mime||
                   (int)$image[0]<1||(int)$image[1]<1||
                   (int)$image[0]*(int)$image[1]>25000000){
                    throw new \InvalidArgumentException('Invalid image attachment.');
                }
            }

            $result[]=[
                'tmp'=>$tmp,
                'name'=>mb_substr(basename($name),0,190),
                'size'=>$size,
                'mime'=>$mime,
                'ext'=>$ext
            ];
        }
        return $result;
    }

    public static function createSubmission(
        array $user,string $type,string $message,?string $context,array $files
    ): int {
        $uploads=self::validate($files);
        $dir=app_path('storage/protected_uploads/feedback');
        if($uploads&&(!is_dir($dir)||!is_writable($dir))){
            throw new \RuntimeException('Private attachment storage is unavailable.');
        }

        $stored=[];
        DB::begin();
        try{
            DB::exec(
                'insert into feedback_submissions
                 (user_id,user_name_snapshot,user_email_snapshot,submission_type,message,context_url)
                 values (?,?,?,?,?,?)',
                [(int)$user['id'],(string)$user['name'],(string)$user['email'],
                 $type,$message,$context]
            );
            $id=(int)DB::id();

            foreach($uploads as $file){
                $storedName=bin2hex(random_bytes(24)).'.'.$file['ext'];
                $destination=$dir.'/'.$storedName;
                if(!move_uploaded_file($file['tmp'],$destination)){
                    throw new \RuntimeException('Attachment could not be stored.');
                }
                $stored[]=$destination;
                chmod($destination,0640);

                DB::exec(
                    'insert into feedback_attachments
                     (feedback_submission_id,original_name,stored_name,mime_type,byte_size)
                     values (?,?,?,?,?)',
                    [$id,$file['name'],$storedName,$file['mime'],$file['size']]
                );
            }
            DB::commit();
            return $id;
        }catch(\Throwable $e){
            if(DB::pdo()->inTransaction())DB::rollBack();
            foreach($stored as $path)@unlink($path);
            throw $e;
        }
    }
}
