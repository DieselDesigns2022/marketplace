<?php

namespace App\Controllers;

use App\Core\Helpers as H;
use App\Services\BattleService;
use Throwable;

final class BattleController
{
    private function message(Throwable $error): string
    {
        if(
            $error instanceof \DomainException ||
            $error instanceof \InvalidArgumentException
        ){
            return $error->getMessage();
        }

        error_log(
            'Battle action failed: '.
            get_class($error).
            ': '.
            $error->getMessage()
        );

        return 'Battle action could not be completed.';
    }

    public function index(): void
    {
        H::view(
            'battles/index',
            [
                'events'=>
                    (new BattleService())
                        ->publicEvents(),
            ]
        );
    }

    public function show($slug): void
    {
        $service=new BattleService();

        $event=$service->eventBySlug(
            (string)$slug
        );

        if(!$event){
            H::abort(404);
        }

        $votes=[];

        if(H::user()){
            $votes=$service->userVotes(
                (int)$event['id'],
                (int)H::user()['id']
            );
        }

        H::view(
            'battles/show',
            [
                'event'=>$event,
                'participants'=>$service->participants(
                    (int)$event['id']
                ),
                'rounds'=>$service->bracket(
                    (int)$event['id']
                ),
                'votes'=>$votes,
                'previousRoundWinners'=>
                    !empty($event['current_round'])
                        ? $service->previousRoundWinners(
                            (int)$event['id'],
                            (int)$event['current_round']
                        )
                        : [],
            ]
        );
    }

    public function vote($slug,$matchupId): void
    {
        H::requireLogin();
        H::verifyCsrf();

        $service=new BattleService();

        $event=$service->eventBySlug(
            (string)$slug
        );

        if(!$event){
            H::abort(404);
        }

        try{
            $service->vote(
                (int)$matchupId,
                (int)($_POST['participant_id']??0),
                (int)H::user()['id'],
                (string)($_SERVER['REMOTE_ADDR']??''),
                (string)($_SERVER['HTTP_USER_AGENT']??'')
            );

            H::flash(
                'success',
                'Your Battle vote has been recorded.'
            );
        }catch(Throwable $error){
            H::flash(
                'error',
                $this->message($error)
            );
        }

        H::redirect(
            '/battle-of-the-designers/'.
            $event['slug']
        );
    }

    public function seller(): void
    {
        H::requireSeller();

        H::view(
            'seller/battles',
            [
                'events'=>
                    (new BattleService())
                        ->sellerEvents(
                            (int)H::user()['id']
                        ),
            ]
        );
    }

    public function signup($id): void
    {
        H::requireSeller();
        H::verifyCsrf();

        try{
            (new BattleService())->signup(
                (int)$id,
                (int)H::user()['id'],
                $_FILES['logo']??[]
            );

            H::flash(
                'success',
                'You are registered for Battle of the Designers.'
            );
        }catch(Throwable $error){
            H::flash(
                'error',
                $this->message($error)
            );
        }

        H::redirect(
            '/seller/battle-of-the-designers'
        );
    }

    public function withdraw($id): void
    {
        H::requireSeller();
        H::verifyCsrf();

        try{
            $changed=(new BattleService())
                ->withdrawSignup(
                    (int)$id,
                    (int)H::user()['id']
                );

            H::flash(
                $changed?'success':'warning',
                $changed
                    ? 'Your Battle registration was withdrawn.'
                    : 'No active Battle registration was found.'
            );
        }catch(Throwable $error){
            H::flash(
                'error',
                $this->message($error)
            );
        }

        H::redirect(
            '/seller/battle-of-the-designers'
        );
    }

    public function adminIndex(): void
    {
        H::requireAdminPermission(
            'prizes.view'
        );

        H::view(
            'admin/battles',
            [
                'events'=>
                    (new BattleService())
                        ->adminEvents(),
            ]
        );
    }

    public function adminCreatePreview(): void
    {
        H::requireAdminPermission(
            'prizes.manage'
        );

        H::verifyCsrf();

        try{
            $id=(new BattleService())
                ->createPreview(
                    (int)H::user()['id']
                );

            H::flash(
                'success',
                'Battle preview created with two fake designers.'
            );

            H::redirect(
                '/admin/battles/'.$id
            );

        }catch(Throwable $error){
            H::flash(
                'error',
                $this->message($error)
            );

            H::redirect('/admin/battles');
        }
    }

    public function adminPreview($id): void
    {
        H::requireAdminPermission(
            'prizes.view'
        );

        $service=new BattleService();

        $event=$service->eventById(
            (int)$id
        );

        if(
            !$event ||
            (int)($event['is_preview']??0)!==1
        ){
            H::abort(404);
        }

        H::view(
            'battles/show',
            [
                'event'=>$event,
                'participants'=>
                    $service->participants(
                        (int)$id
                    ),
                'rounds'=>
                    $service->bracket(
                        (int)$id
                    ),
                'votes'=>[],
                'previousRoundWinners'=>
                    !empty($event['current_round'])
                        ? $service->previousRoundWinners(
                            (int)$event['id'],
                            (int)$event['current_round']
                        )
                        : [],
                'adminPreview'=>true,
            ]
        );
    }

    public function adminDeletePreview($id): void
    {
        H::requireAdminPermission(
            'prizes.manage'
        );

        H::verifyCsrf();

        try{
            $deleted=(new BattleService())
                ->deletePreview(
                    (int)$id,
                    (int)H::user()['id']
                );

            H::flash(
                $deleted?'success':'warning',
                $deleted
                    ? 'Battle preview and fake designers deleted.'
                    : 'Battle preview was not found.'
            );

        }catch(Throwable $error){
            H::flash(
                'error',
                $this->message($error)
            );
        }

        H::redirect('/admin/battles');
    }

    public function adminCreate(): void
    {
        H::requireAdminPermission(
            'prizes.manage'
        );

        H::verifyCsrf();

        try{
            $id=(new BattleService())->createEvent(
                (int)H::user()['id'],
                (string)($_POST['title']??'')
            );

            H::flash(
                'success',
                'Battle created.'
            );

            H::redirect(
                '/admin/battles/'.$id
            );
        }catch(Throwable $error){
            H::flash(
                'error',
                $this->message($error)
            );

            H::redirect('/admin/battles');
        }
    }

    public function adminShow($id): void
    {
        H::requireAdminPermission(
            'prizes.view'
        );

        $service=new BattleService();

        $event=$service->eventById(
            (int)$id
        );

        if(!$event){
            H::abort(404);
        }

        H::view(
            'admin/battle',
            [
                'event'=>$event,
                'participants'=>
                    $service->participants(
                        (int)$id
                    ),
                'rounds'=>$service->bracket(
                    (int)$id
                ),
                'flags'=>$service->voteFlags(
                    (int)$id
                ),
                'voterLedger'=>$service->voterLedger(
                    (int)$id
                ),
            ]
        );
    }

    public function adminAction($id): void
    {
        H::requireAdminPermission(
            'prizes.manage'
        );

        H::verifyCsrf();

        $service=new BattleService();
        $action=(string)(
            $_POST['action']??''
        );

        try{
            if($action==='generate'){
                $result=$service->generateBracket(
                    (int)$id,
                    (int)H::user()['id']
                );

                H::flash(
                    'success',
                    'Bracket generated for '.
                    $result['participants'].
                    ' designers.'
                );
            }elseif($action==='schedule_round'){
                $result=$service->scheduleRound(
                    (int)$id,
                    (int)(
                        $_POST['round_number']??
                        0
                    ),
                    (int)H::user()['id'],
                    (string)(
                        $_POST['starts_at']??
                        ''
                    )
                );

                H::flash(
                    'success',
                    'Battle round scheduled. Designers will receive their 12-hour warning before voting opens automatically.'
                );
            }elseif($action==='close_round'){
                $result=$service->closeRound(
                    (int)$id,
                    (int)(
                        $_POST['round_number']??
                        0
                    ),
                    (int)H::user()['id']
                );

                H::flash(
                    $result['ties']>0
                        ? 'warning'
                        : 'success',
                    $result['ties']>0
                        ? $result['ties'].
                            ' matchup(s) ended in a tie and require review.'
                        : 'Battle round completed.'
                );
            }elseif($action==='resolve_tie'){
                $service->resolveTie(
                    (int)(
                        $_POST['matchup_id']??
                        0
                    ),
                    (int)(
                        $_POST['winner_participant_id']??
                        0
                    ),
                    (int)H::user()['id']
                );

                H::flash(
                    'success',
                    'Battle tie resolved.'
                );
            }elseif($action==='review_flag'){
                $service->reviewVoteFlag(
                    (int)(
                        $_POST['flag_id']??
                        0
                    ),
                    (string)(
                        $_POST['decision']??
                        ''
                    ),
                    (string)(
                        $_POST['notes']??
                        ''
                    ),
                    (int)H::user()['id']
                );

                H::flash(
                    'success',
                    'Voting flag reviewed.'
                );
            }else{
                throw new \InvalidArgumentException(
                    'Choose a valid Battle action.'
                );
            }
        }catch(Throwable $error){
            H::flash(
                'error',
                $this->message($error)
            );
        }

        H::redirect(
            '/admin/battles/'.(int)$id
        );
    }
}
