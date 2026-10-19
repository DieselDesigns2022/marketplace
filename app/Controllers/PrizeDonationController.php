<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\PrizeDonationService;
use DomainException;
use InvalidArgumentException;
use Throwable;

class PrizeDonationController
{
    private function seller(): array
    {
        H::requireSeller();

        $designer=DB::row(
            'select * from designers
             where user_id=? and status="approved"',
            [(int)H::user()['id']]
        );

        if(!$designer){
            H::flash(
                'warning',
                'Prize donations require an approved seller account.'
            );

            H::redirect('/apply');
        }

        return $designer;
    }

    private function message(Throwable $e): string
    {
        if(
            $e instanceof InvalidArgumentException ||
            $e instanceof DomainException
        ){
            return $e->getMessage();
        }

        return 'Prize donation action could not be completed.';
    }

    public function sellerIndex(): void
    {
        $designer=$this->seller();
        $service=new PrizeDonationService();

        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::verifyCsrf();

            try{
                $service->submit(
                    (int)$designer['id'],
                    $_POST,
                    $_FILES
                );

                H::flash(
                    'success',
                    'Your prize donation was submitted for review.'
                );

                H::redirect('/seller/prize-donations');
            }catch(Throwable $e){
                H::flash(
                    'error',
                    $this->message($e)
                );

                H::redirect('/seller/prize-donations');
            }
        }

        H::view(
            'seller/prize_donations',
            [
                'designer'=>$designer,
                'donations'=>$service->sellerDonations(
                    (int)$designer['id']
                ),
                'types'=>PrizeDonationService::TYPES,
                'usages'=>PrizeDonationService::USAGES,
            ]
        );
    }

    public function sellerCancel(string $id): void
    {
        $designer=$this->seller();
        H::verifyCsrf();

        try{
            $ok=(new PrizeDonationService())->sellerCancel(
                (int)$id,
                (int)$designer['id']
            );

            H::flash(
                $ok?'success':'warning',
                $ok
                    ? 'Prize donation withdrawn.'
                    : 'Prize donation could not be withdrawn.'
            );
        }catch(Throwable $e){
            H::flash(
                'error',
                $this->message($e)
            );
        }

        H::redirect('/seller/prize-donations');
    }

    private function servePrizeFile(array $file): never
    {
        $base=realpath(
            app_path(
                'storage/protected_uploads/prize_donations'
            )
        );

        $real=realpath(
            app_path(
                'storage/protected_uploads/'.
                ltrim(
                    (string)$file['prize_file_storage_path'],
                    '/'
                )
            )
        );

        if(
            !$base ||
            !$real ||
            !str_starts_with(
                $real,
                $base.DIRECTORY_SEPARATOR
            ) ||
            !is_file($real) ||
            !is_readable($real)
        ){
            H::abort(404);
        }

        $mime=trim(
            (string)($file['prize_file_mime_type']??'')
        );

        if($mime===''){
            $mime='application/octet-stream';
        }

        $name=(string)(
            $file['prize_file_original_name']
            ??'creative-moth-prize'
        );

        header('Content-Type: '.$mime);
        header('Content-Length: '.filesize($real));
        header(
            "Content-Disposition: attachment; filename*=UTF-8''".
            rawurlencode($name)
        );
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($real);
        exit;
    }


    public function sellerFile(string $id): void
    {
        $designer=$this->seller();

        $file=(new PrizeDonationService())->sellerFile(
            (int)$id,
            (int)$designer['id']
        );

        if(!$file){
            H::abort(404);
        }

        $this->servePrizeFile($file);
    }


    public function adminFile(string $id): void
    {
        H::requireAdminPermission('prizes.view');

        $file=(new PrizeDonationService())->adminFile(
            (int)$id
        );

        if(!$file){
            H::abort(404);
        }

        $this->servePrizeFile($file);
    }


    public function adminIndex(): void
    {
        H::requireAdminPermission('prizes.view');

        $service=new PrizeDonationService();

        H::view(
            'admin/prize_donations',
            [
                'donations'=>$service->adminDonations(),
                'assignments'=>$service->assignments(),
                'types'=>PrizeDonationService::TYPES,
                'usages'=>PrizeDonationService::USAGES,
                'totals'=>$service->totals(),
                'canManage'=>H::canAdmin('prizes.manage'),
            ]
        );
    }

    public function approve(string $id): void
    {
        $this->manage(function()use($id){
            return (new PrizeDonationService())->approve(
                (int)$id,
                (int)H::user()['id']
            );
        },'Prize donation approved.');
    }

    public function cancel(string $id): void
    {
        $this->manage(function()use($id){
            return (new PrizeDonationService())->adminCancel(
                (int)$id
            );
        },'Prize donation cancelled.');
    }

    public function assign(string $id): void
    {
        $this->manage(function()use($id){
            $quantity=filter_var(
                $_POST['quantity']??null,
                FILTER_VALIDATE_INT,
                [
                    'options'=>[
                        'min_range'=>1,
                        'max_range'=>10000,
                    ],
                ]
            );

            if($quantity===false){
                throw new InvalidArgumentException(
                    'Assignment quantity must be a whole number of at least 1.'
                );
            }

            return (new PrizeDonationService())->assign(
                (int)$id,
                (int)$quantity,
                trim((string)($_POST['event_type']??'')),
                trim((string)($_POST['event_reference']??'')),
                (int)H::user()['id'],
                'admin-prize-assignment:'.
                    (int)$id.':'.
                    bin2hex(random_bytes(12))
            );
        },'Prize inventory assigned.');
    }

    public function award(string $id): void
    {
        $this->manage(function()use($id){
            $rawUser=trim(
                (string)($_POST['winner_user_id']??'')
            );

            $winnerUserId=null;

            if($rawUser!==''){
                if(!preg_match('/^[1-9][0-9]*$/D',$rawUser)){
                    throw new InvalidArgumentException(
                        'Winner user ID must be a positive whole number.'
                    );
                }

                $winnerUserId=(int)$rawUser;
            }

            return (new PrizeDonationService())->award(
                (int)$id,
                (int)H::user()['id'],
                $winnerUserId,
                $_POST['winner_name']??null,
                $_POST['winner_email']??null
            );
        },'Prize awarded to winner.');
    }

    public function fulfill(string $id): void
    {
        $this->manage(function()use($id){
            return (new PrizeDonationService())->fulfill(
                (int)$id,
                (int)H::user()['id'],
                trim(
                    (string)($_POST['fulfillment_notes']??'')
                )
            );
        },'Prize marked fulfilled.');
    }

    public function release(string $id): void
    {
        $this->manage(function()use($id){
            return (new PrizeDonationService())
                ->releaseAssignment((int)$id);
        },'Prize assignment returned to available inventory.');
    }

    private function manage(
        callable $action,
        string $success
    ): void {
        H::requireAdminPermission('prizes.manage');
        H::verifyCsrf();

        try{
            $result=$action();

            H::flash(
                $result?'success':'warning',
                $result
                    ? $success
                    : 'No prize inventory change was needed.'
            );
        }catch(Throwable $e){
            H::flash(
                'error',
                $this->message($e)
            );
        }

        H::redirect('/admin/prize-donations');
    }
}
