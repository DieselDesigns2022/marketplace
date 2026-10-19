<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\PrizeWheelService;
use DomainException;
use Throwable;

final class PrizeWheelController
{
    public function admin(): void
    {
        H::requireAdminPermission('prizes.view');

        $service=new PrizeWheelService();

        if($_SERVER['REQUEST_METHOD']==='POST'){
            H::requireAdminPermission('prizes.manage');
            H::verifyCsrf();

            $action=(string)($_POST['action']??'settings');

            try{
                if($action==='new_round'){
                    DB::begin();

                    $service->startNewRound(
                        (int)H::user()['id']
                    );

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
                            (int)H::user()['id'],
                            'prize_wheel_new_round',
                            'prize_wheel_settings',
                            1,
                            json_encode(
                                ['started_at'=>date(DATE_ATOM)],
                                JSON_THROW_ON_ERROR
                            ),
                        ]
                    );

                    DB::commit();

                    H::flash(
                        'success',
                        'A new Prize Wheel giveaway round was started. Previous winner history was preserved.'
                    );

                    H::redirect('/admin/prize-wheel');
                }

                $enabled=($_POST['is_enabled']??'')==='1';

                $minimumRaw=trim(
                    (string)(
                        $_POST['minimum_purchase']
                        ??'5.00'
                    )
                );

                if(
                    !preg_match(
                        '/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/',
                        $minimumRaw
                    )
                ){
                    throw new DomainException(
                        'Enter a valid minimum purchase amount.'
                    );
                }

                $minimumPurchaseCents=
                    (int)round(
                        ((float)$minimumRaw)*100
                    );

                if(
                    $enabled
                    && $service->optedInStoreCount()<1
                ){
                    throw new DomainException(
                        'At least one approved seller must opt into the Prize Wheel before turning it ON.'
                    );
                }

                DB::begin();

                $service->updateSettings(
                    $enabled,
                    (string)($_POST['heading']??''),
                    (string)($_POST['description']??''),
                    $minimumPurchaseCents,
                    (int)H::user()['id']
                );

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
                        (int)H::user()['id'],
                        $enabled
                            ? 'prize_wheel_enabled'
                            : 'prize_wheel_disabled',
                        'prize_wheel_settings',
                        1,
                        json_encode(
                            [
                                'is_enabled'=>$enabled,
                                'minimum_purchase_cents'=>$minimumPurchaseCents,
                                'heading'=>mb_substr(
                                    trim(
                                        (string)(
                                            $_POST['heading']??''
                                        )
                                    ),
                                    0,
                                    190
                                ),
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ]
                );

                DB::commit();

                H::flash(
                    'success',
                    $enabled
                        ? 'Prize Wheel is ON. It is live on opted-in seller stores.'
                        : 'Prize Wheel is OFF. It is hidden from all storefronts.'
                );

                H::redirect('/admin/prize-wheel');
            }catch(Throwable $error){
                if(DB::pdo()->inTransaction()){
                    DB::rollBack();
                }

                H::flash(
                    'error',
                    $error instanceof DomainException
                        ? $error->getMessage()
                        : 'Prize Wheel settings could not be updated.'
                );

                H::redirect('/admin/prize-wheel');
            }
        }

        H::view(
            'admin/prize_wheel',
            [
                'settings'=>$service->settings(),
                'optedInStores'=>$service->optedInStoreCount(),
                'participatingStores'=>$service->participatingStores(),
                'availablePrizes'=>$service->availableWheelPrizeCount(),
                'recentSpins'=>$service->recentSpins(),
            ]
        );
    }

    public function spin($designerId): void
    {
        H::requireLogin();
        H::verifyCsrf();

        $designerId=(int)$designerId;

        $store=DB::row(
            'select id,store_slug
             from designers
             where id=?
             limit 1',
            [$designerId]
        );

        if(!$store){
            H::abort(404);
        }

        try{
            $result=(new PrizeWheelService())->spin(
                $designerId,
                (int)H::user()['id']
            );

            $_SESSION['prize_wheel_result']=[
                'storefront_designer_id'=>$designerId,
                'result'=>$result,
            ];

            H::flash(
                'success',
                'You won '.$result['title'].'!'
            );
        }catch(Throwable $error){
            H::flash(
                'error',
                $error instanceof DomainException
                    ? $error->getMessage()
                    : 'Your Prize Wheel spin could not be completed.'
            );
        }

        H::redirect(
            '/store/'.
            rawurlencode((string)$store['store_slug']).
            '#creative-moth-prize-wheel'
        );
    }

    public function winnerFile($spinId): void
    {
        H::requireLogin();

        try{
            $file=(new PrizeWheelService())->winnerFile(
                (int)$spinId,
                (int)H::user()['id']
            );
        }catch(Throwable $error){
            H::abort(404);
        }

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
            !$base
            || !$real
            || !str_starts_with(
                $real,
                $base.DIRECTORY_SEPARATOR
            )
            || !is_file($real)
            || !is_readable($real)
        ){
            H::abort(404);
        }

        $name=
            trim(
                (string)$file['prize_file_original_name']
            ) ?: 'prize-download';

        header(
            'Content-Type: '.
            (
                trim(
                    (string)$file['prize_file_mime_type']
                ) ?: 'application/octet-stream'
            )
        );

        header(
            'Content-Length: '.filesize($real)
        );

        header(
            "Content-Disposition: attachment; filename*=UTF-8''".
            rawurlencode($name)
        );

        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');

        readfile($real);
        exit;
    }
}
