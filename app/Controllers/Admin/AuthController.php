<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthController extends Base
{
    /** Page de retour après connexion : uniquement une page d'administration (jamais externe, jamais la page de connexion). */
    public static function adminReturn(?string $path): string
    {
        $path = safe_return($path, '/admin');
        $plain = (string) strtok($path, '?');
        if (($plain !== '/admin' && !str_starts_with($plain, '/admin/')) || $plain === '/admin/connexion' || $plain === '/admin/deconnexion') {
            return '/admin';
        }
        return $path;
    }

    public function form(Request $r, array $errors = [], string $email = '', int $status = 200): Response
    {
        if (Auth::isStaff()) {
            return redirect(self::adminReturn($r->str('retour')));
        }
        if (Auth::check()) {
            // Client connecté : l'administration ne lui est pas destinée.
            throw new HttpException(403, '', 'admin_denied');
        }
        return view('admin/login', ['errors' => $errors, 'email' => $email, 'retour' => self::adminReturn($r->str('retour'))], $status, null);
    }

    public function login(Request $r): Response
    {
        if (Auth::check() && !Auth::isStaff()) {
            throw new HttpException(403, '', 'admin_denied');
        }
        $email = mb_strtolower($r->str('email'));
        $result = Auth::attempt($email, (string) ($r->post['password'] ?? ''), $r->ip(), true);
        if (!$result['ok']) {
            Audit::log('admin.login_failed', 'user', null, ['email' => mb_substr($email, 0, 190)], null);
            return $this->form($r, [$result['error']], $email, 422);
        }
        Auth::login($result['user']);
        Audit::log('admin.login', 'user', (int) $result['user']['id'], [], (int) $result['user']['id']);
        Session::flash('success', 'Bienvenue ' . $result['user']['first_name'] . '.');
        return redirect(self::adminReturn($r->str('retour')));
    }

    public function logout(Request $r): Response
    {
        Audit::log('admin.logout', 'user', Auth::id());
        Auth::logout();
        Session::flash('success', 'Vous êtes déconnecté(e).');
        return redirect('/admin/connexion');
    }
}
