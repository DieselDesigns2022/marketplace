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
        $this->gate(false);

        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'category' => trim((string)($_GET['category'] ?? '')),
            'status' => in_array(($_GET['status'] ?? 'all'), ['all','enabled','disabled'], true)
                ? (string)$_GET['status']
                : 'all',
        ];

        $perPage = 50;
        $total = $this->repo->termCount($filters);
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min((int)($_GET['page'] ?? 1), $pages));
        $offset = ($page - 1) * $perPage;

        H::view('admin/ip-risk-terms/index', [
            'terms' => $this->repo->terms($filters, $perPage, $offset),
            'filters' => $filters,
            'categories' => IpRiskRepository::CATEGORIES,
            'categoryCounts' => $this->repo->categoryCounts(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ]);
    }

    public function quickStore(): void
    {
        $this->gate(true);

        $values = [
            'term' => trim((string)($_POST['term'] ?? '')),
            'category' => in_array(($_POST['category'] ?? ''), IpRiskRepository::CATEGORIES, true)
                ? (string)$_POST['category']
                : 'other_protected_term',
            'aliases' => '',
            'internal_note' => 'Quick-added from the IP Risk Terms dashboard.',
            'is_enabled' => '1',
        ];

        $errors = $this->repo->saveTerm($values, (int)H::user()['id']);

        if ($errors) {
            H::flash('error', implode(' ', $errors));
        } else {
            H::flash('success', 'IP risk term added.');
        }

        H::redirect('/admin/ip-risk-terms');
    }

    public function quickAlias($id): void
    {
        $this->gate(true);

        $errors = $this->repo->addAlias(
            (int)$id,
            trim((string)($_POST['alias'] ?? ''))
        );

        if ($errors) {
            H::flash('error', implode(' ', $errors));
        } else {
            H::flash('success', 'Alias added.');
        }

        H::redirect('/admin/ip-risk-terms');
    }

    public function create(): void
    {
        $this->gate(true);
        H::view('admin/ip-risk-terms/form', [
            'term' => null,
            'aliases' => [],
            'errors' => [],
            'categories' => IpRiskRepository::CATEGORIES,
        ]);
    }

    public function store(): void
    {
        $this->gate(true);
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
        $this->gate(true);
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
        $this->gate(true);
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
        $this->gate(true);
        if (!$this->repo->setTermEnabled((int)$id, true, (int)H::user()['id'])) {
            H::abort(404);
        }
        H::flash('success', 'IP risk term enabled.');
        H::redirect('/admin/ip-risk-terms');
    }

    public function disable($id): void
    {
        $this->gate(true);
        if (!$this->repo->setTermEnabled((int)$id, false, (int)H::user()['id'])) {
            H::abort(404);
        }
        H::flash('success', 'IP risk term disabled.');
        H::redirect('/admin/ip-risk-terms');
    }

    private function gate(bool $manage): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') H::verifyCsrf();
        H::requireAdminPermission($manage ? 'ip_risk.manage' : 'ip_risk.view');
    }
}
