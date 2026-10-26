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

    public function adminCreate(): void
    {
        H::requireAdminPermission(
            'prizes.manage'
        );

        H::verifyCsrf();

        $minutes=trim(
            (string)(
                $_POST['voting_minutes']??
                ''
            )
        );

        try{
            $id=(new BattleService())->createEvent(
                (int)H::user()['id'],
                (string)($_POST['title']??''),
                $minutes!==''
                    ? (int)$minutes
                    : null
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
            if($action==='voting_minutes'){
                $service->setVotingMinutes(
                    (int)$id,
                    (int)(
                        $_POST['voting_minutes']??
                        0
                    ),
                    (int)H::user()['id']
                );

                H::flash(
                    'success',
                    'Battle voting duration updated.'
                );
            }elseif($action==='generate'){
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
            }elseif($action==='open_round'){
                $service->openRound(
                    (int)$id,
                    (int)(
                        $_POST['round_number']??
                        0
                    ),
                    (int)H::user()['id']
                );

                H::flash(
                    'success',
                    'Battle round opened for voting.'
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
