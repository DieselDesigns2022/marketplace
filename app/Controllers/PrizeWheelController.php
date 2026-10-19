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
                        'A new Prize Wheel promotion round was started.'
                    );

                    H::redirect('/admin/prize-wheel');
                }

                if($action==='save_prize'){
                    $id=
                        (int)($_POST['prize_id']??0);

                    $service->savePrize(
                        $id>0?$id:null,
                        (string)($_POST['title']??''),
                        (string)($_POST['prize_type']??''),
                        isset($_POST['prize_value'])
                            ? (string)$_POST['prize_value']
                            : null,
                        (int)($_POST['weight']??1),
                        (int)($_POST['sort_order']??0),
                        isset($_POST['is_active']),
                        (int)H::user()['id']
                    );

                    H::flash(
                        'success',
                        $id>0
                            ? 'Prize Wheel prize updated.'
                            : 'Prize Wheel prize added.'
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
                        'At least one approved seller must opt into Creative Moth giveaways before turning the Prize Wheel ON.'
                    );
                }

                if(
                    $enabled
                    && $service->availableWheelPrizeCount()<1
                ){
                    throw new DomainException(
                        'At least one active Prize Wheel prize is required before turning the promotion ON.'
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
                                'minimum_purchase_cents'=>
                                    $minimumPurchaseCents,
                            ],
                            JSON_THROW_ON_ERROR
                        ),
                    ]
                );

                DB::commit();

                H::flash(
                    'success',
                    $enabled
                        ? 'Prize Wheel is ON for sellers who opted into Creative Moth giveaways.'
                        : 'Prize Wheel is OFF and hidden from all storefronts.'
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
                        : 'Prize Wheel could not be updated.'
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
                'wheelPrizes'=>$service->prizes(true),
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

            $message='You won '.$result['title'].'!';

            if(!empty($result['reward_message'])){
                $message.=' '.$result['reward_message'];
            }

            H::flash(
                'success',
                $message
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


}
