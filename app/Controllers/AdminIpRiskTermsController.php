<?php
namespace App\Controllers;

use App\Core\Helpers as H;
use App\Repositories\IpRiskRepository;

class AdminIpRiskTermsController
{
    private IpRiskRepository $repo;

    public function __construct()
    {
        $this->repo = new IpRiskRepository();
    }

    public function index(): void
    {
        $this->gate();
        H::view('admin/ip-risk-terms/index', ['terms' => $this->repo->terms()]);
    }

    public function create(): void
    {
        $this->gate();
        H::view('admin/ip-risk-terms/form', [
            'term' => null,
            'aliases' => [],
            'errors' => [],
            'categories' => IpRiskRepository::CATEGORIES,
        ]);
    }

    public function store(): void
    {
        $this->gate();
        $errors = $this->repo->saveTerm($_POST, (int)H::user()['id']);
        if ($errors) {
            H::view('admin/ip-risk-terms/form', [
                'term' => $_POST,
                'aliases' => [],
                'errors' => $errors,
                'categories' => IpRiskRepository::CATEGORIES,
            ]);
            return;
        }
        H::flash('success', 'IP risk term created.');
        H::redirect('/admin/ip-risk-terms');
    }

    public function edit($id): void
    {
        $this->gate();
        $term = $this->repo->term((int)$id) ?? H::abort(404);
        H::view('admin/ip-risk-terms/form', [
            'term' => $term,
            'aliases' => $this->repo->aliases((int)$id),
            'errors' => [],
            'categories' => IpRiskRepository::CATEGORIES,
        ]);
    }

    public function update($id): void
    {
        $this->gate();
        if (!$this->repo->term((int)$id)) {
            H::abort(404);
        }
        $errors = $this->repo->saveTerm($_POST, (int)H::user()['id'], (int)$id);
        if ($errors) {
            $term = $_POST;
            $term['id'] = (int)$id;
            H::view('admin/ip-risk-terms/form', [
                'term' => $term,
                'aliases' => [],
                'errors' => $errors,
                'categories' => IpRiskRepository::CATEGORIES,
            ]);
            return;
        }
        H::flash('success', 'IP risk term updated.');
        H::redirect('/admin/ip-risk-terms');
    }

    public function enable($id): void
    {
        $this->gate();
        if (!$this->repo->setTermEnabled((int)$id, true, (int)H::user()['id'])) {
            H::abort(404);
        }
        H::flash('success', 'IP risk term enabled.');
        H::redirect('/admin/ip-risk-terms');
    }

    public function disable($id): void
    {
        $this->gate();
        if (!$this->repo->setTermEnabled((int)$id, false, (int)H::user()['id'])) {
            H::abort(404);
        }
        H::flash('success', 'IP risk term disabled.');
        H::redirect('/admin/ip-risk-terms');
    }

    private function gate(): void
    {
        H::requireRole('admin');
    }
}
