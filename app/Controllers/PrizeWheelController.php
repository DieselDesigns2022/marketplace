<?php

namespace App\Controllers;

use App\Core\Database as DB;
use App\Core\Helpers as H;
use App\Services\PrizeWheelService;
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

            try{
                $enabled=($_POST['is_enabled']??'')==='1';

                DB::begin();

                $service->updateSettings(
                    $enabled,
                    (string)($_POST['heading']??''),
                    (string)($_POST['description']??''),
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
                        ? 'Prize Wheel is ON. It is now visible on opted-in seller stores.'
                        : 'Prize Wheel is OFF. It is no longer visible on any storefront.'
                );

                H::redirect('/admin/prize-wheel');
            }catch(Throwable $error){
                if(DB::pdo()->inTransaction()){
                    DB::rollBack();
                }

                error_log(
                    'Prize Wheel settings update failed: '.
                    $error->getMessage()
                );

                H::flash(
                    'error',
                    'Prize Wheel settings could not be updated.'
                );

                H::redirect('/admin/prize-wheel');
            }
        }

        H::view(
            'admin/prize_wheel',
            [
                'settings'=>$service->settings(),
                'optedInStores'=>$service->optedInStoreCount(),
                'availablePrizes'=>$service->availableWheelPrizeCount(),
            ]
        );
    }
}
