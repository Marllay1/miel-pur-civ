<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AccountService;

final class TeamController extends Base
{
    private function staffRoles(): array
    {
        return DB::all("SELECT * FROM roles WHERE name <> 'customer' ORDER BY id");
    }

    public function index(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        $roles = $this->staffRoles();
        $perms = [];
        foreach (DB::all('SELECT rp.role_id, p.name, p.label FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id ORDER BY p.id') as $row) {
            $perms[(int) $row['role_id']][] = $row['label'];
        }
        return admin_view('team', [
            'staff' => DB::all("SELECT u.*, r.name AS role, r.label AS role_label FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name <> 'customer' ORDER BY u.is_active DESC, u.last_name"),
            'roles' => $roles, 'perms' => $perms, 'errors' => $errors, 'old' => $old, 'pageTitle' => 'Équipe',
        ], $status);
    }

    public function store(Request $r): Response
    {
        $roles = array_column($this->staffRoles(), 'name');
        $v = Validator::make($r->post, [
            'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'email' => 'required|email',
            'role' => 'required|in:' . implode(',', $roles), 'password' => 'required',
        ], ['first_name' => 'Le prénom', 'last_name' => 'Le nom', 'email' => 'L\'e-mail', 'role' => 'Le rôle', 'password' => 'Le mot de passe']);
        $password = (string) ($r->post['password'] ?? '');
        if (!isset($v->errors['password']) && ($p = AccountService::passwordProblem($password))) {
            $v->errors['password'] = $p;
        }
        if (!$v->fails()) {
            $res = AccountService::register($v->data + ['password' => $password, 'phone' => null], $v->data['role']);
            if ($res['ok']) {
                Audit::log('user.create', 'user', (int) $res['user']['id'], ['email' => $res['user']['email'], 'role' => $v->data['role']]);
                Session::flash('success', 'Compte créé pour ' . $res['user']['email'] . '. Transmettez le mot de passe de façon sûre et demandez sa modification.');
                return redirect('/admin/equipe');
            }
            $v->errors['email'] = $res['error'];
        }
        $old = $r->post;
        unset($old['password']);
        return $this->index($r, $v->errors, $old, 422);
    }

    public function update(Request $r): Response
    {
        $id = (int) $r->params['id'];
        $user = DB::one("SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.name <> 'customer'", [$id]);
        if ($user === null) {
            throw new HttpException(404);
        }
        $role = $r->str('role') ?: 'admin'; // rôle d'équipe unique
        $active = $r->str('is_active') === '1' ? 1 : 0;
        $roleId = DB::value("SELECT id FROM roles WHERE name = ? AND name <> 'customer'", [$role]);
        if ($roleId === null) {
            Session::flash('error', 'Rôle invalide.');
            return redirect('/admin/equipe');
        }
        if ($id === $this->uid() && ($role !== $user['role'] || $active === 0)) {
            Session::flash('error', 'Vous ne pouvez pas modifier votre propre rôle ni désactiver votre propre compte.');
            return redirect('/admin/equipe');
        }
        if ($user['role'] === 'admin' && ($role !== 'admin' || $active === 0)) {
            $admins = (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'admin' AND u.is_active = 1");
            if ($admins <= 1) {
                Session::flash('error', 'Il doit rester au moins un administrateur actif.');
                return redirect('/admin/equipe');
            }
        }
        DB::update('users', ['role_id' => (int) $roleId, 'is_active' => $active, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [$id]);
        Audit::log('user.update', 'user', $id, ['role' => [$user['role'], $role], 'active' => [(int) $user['is_active'], $active]]);
        Session::flash('success', 'Compte mis à jour.');
        return redirect('/admin/equipe');
    }
}
