<?php
namespace App\Services;
final class UserDirectoryFilter
{
    public static function state(array $input): array
    {
        $role=$input['user_role']??'';$status=$input['user_status']??'';$tester=$input['user_tester']??'';$q=$input['user_q']??'';
        return ['user_role'=>in_array($role,['admin','seller','buyer'],true)?$role:'',
            'user_status'=>in_array($status,['active','disabled'],true)?$status:'',
            'user_tester'=>in_array($tester,['yes','no'],true)?$tester:'',
            'user_q'=>is_string($q)?mb_substr(trim($q),0,150):''];
    }
    public static function apply(array $users,array $filters): array
    {
        $f=self::state($filters);
        return array_values(array_filter($users,static function(array $u) use ($f): bool {
            $roleMatches=match($f['user_role']) {
                'admin'=>($u['role']??'')==='admin',
                'seller'=>($u['role']??'')==='designer' || !empty($u['designer_id']),
                'buyer'=>($u['role']??'')==='buyer',
                default=>true,
            };
            $testerMatches=$f['user_tester']===''
                || ($f['user_tester']==='yes' && !empty($u['is_tester']))
                || ($f['user_tester']==='no' && empty($u['is_tester']));
            return $roleMatches && ($f['user_status']==='' || ($u['status']??'')===$f['user_status'])
                && $testerMatches
                && ($f['user_q']==='' || mb_stripos(($u['name']??'').' '.($u['email']??''),$f['user_q'])!==false);
        }));
    }
}
