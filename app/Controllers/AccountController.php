<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AccountService;
use App\Services\CartService;

final class AccountController
{
    public function loginForm(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('account/login', ['errors' => $errors, 'old' => $old, 'retour' => safe_return($r->str('retour'), '/compte'), 'pageTitle' => 'Connexion', 'noindex' => true], $status);
    }

    public function login(Request $r): Response
    {
        $result = Auth::attempt($r->str('email'), (string) ($r->post['password'] ?? ''), $r->ip());
        if (!$result['ok']) {
            return $this->loginForm($r, ['_' => $result['error']], ['email' => $r->str('email')], 422);
        }
        $user = $result['user'];
        Auth::login($user);
        CartService::mergeGuestCart($r, (int) $user['id']);
        Session::flash('success', 'Bonjour ' . $user['first_name'] . ', vous êtes connecté(e).');
        if ($user['role'] !== 'customer') {
            return redirect('/admin');
        }
        return redirect(safe_return($r->str('retour'), '/compte'));
    }

    public function registerForm(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('account/register', ['errors' => $errors, 'old' => $old, 'pageTitle' => 'Créer un compte', 'noindex' => true], $status);
    }

    public function register(Request $r): Response
    {
        $v = Validator::make($r->post, [
            'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'email' => 'required|email',
            'phone' => 'phone', 'password' => 'required', 'password_confirmation' => 'required|same:password', 'consent' => 'accepted',
        ], ['first_name' => 'Le prénom', 'last_name' => 'Le nom', 'email' => 'L\'e-mail', 'phone' => 'Le téléphone', 'password' => 'Le mot de passe', 'password_confirmation' => 'La confirmation']);
        $errors = $v->errors;
        $password = (string) ($r->post['password'] ?? '');
        if (!isset($errors['password']) && ($problem = AccountService::passwordProblem($password))) {
            $errors['password'] = $problem;
        }
        if ($spam = spam_check($r)) {
            $errors['_'] = $spam;
        }
        if (!$errors && !\App\Core\RateLimiter::hit('register:' . $r->ip(), 5, 3600)) {
            $errors['_'] = 'Trop de créations de compte depuis cette connexion. Réessayez plus tard.';
        }
        if (!$errors) {
            $result = AccountService::register($v->data + ['password' => $password, 'marketing_opt_in' => $r->str('marketing') === '1']);
            if ($result['ok']) {
                Auth::login($result['user']);
                CartService::mergeGuestCart($r, (int) $result['user']['id']);
                Session::flash('success', 'Votre compte est créé. Bienvenue !');
                return redirect('/compte');
            }
            $errors['email'] = $result['error'];
        }
        $old = $r->post;
        unset($old['password'], $old['password_confirmation']);
        return $this->registerForm($r, $errors, $old, 422);
    }

    public function logout(Request $r): Response
    {
        Auth::logout();
        Session::flash('success', 'Vous êtes déconnecté(e).');
        return redirect('/');
    }

    public function forgotForm(Request $r): Response
    {
        return view('account/forgot', ['errors' => [], 'sent' => false, 'pageTitle' => 'Mot de passe oublié', 'noindex' => true]);
    }

    public function forgot(Request $r): Response
    {
        $email = $r->str('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return view('account/forgot', ['errors' => ['email' => 'Adresse e-mail invalide.'], 'sent' => false, 'pageTitle' => 'Mot de passe oublié', 'noindex' => true], 422);
        }
        AccountService::requestReset($email, $r->ip());
        return view('account/forgot', ['errors' => [], 'sent' => true, 'pageTitle' => 'Mot de passe oublié', 'noindex' => true]);
    }

    public function resetForm(Request $r, array $errors = [], int $status = 200): Response
    {
        $valid = AccountService::findReset($r->params['token']) !== null;
        return view('account/reset', ['errors' => $errors, 'valid' => $valid, 'token' => $r->params['token'], 'pageTitle' => 'Nouveau mot de passe', 'noindex' => true], $valid ? $status : 410);
    }

    public function reset(Request $r): Response
    {
        $password = (string) ($r->post['password'] ?? '');
        $errors = [];
        if ($problem = AccountService::passwordProblem($password)) {
            $errors['password'] = $problem;
        } elseif ($password !== (string) ($r->post['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }
        if ($errors) {
            return $this->resetForm($r, $errors, 422);
        }
        if (!AccountService::resetPassword($r->params['token'], $password)) {
            return $this->resetForm($r, [], 410);
        }
        Session::flash('success', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');
        return redirect('/compte/connexion');
    }

    public function dashboard(Request $r): Response
    {
        if (Auth::isStaff()) {
            return redirect('/admin');
        }
        $uid = (int) Auth::id();
        return view('account/dashboard', [
            'user' => Auth::user(),
            'orders' => DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5', [$uid]),
            'orderCount' => (int) DB::value('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$uid]),
            'addressCount' => (int) DB::value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$uid]),
            'pageTitle' => 'Mon compte', 'noindex' => true,
        ]);
    }

    public function orders(Request $r): Response
    {
        return view('account/orders', [
            'orders' => DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC', [(int) Auth::id()]),
            'pageTitle' => 'Mes commandes', 'noindex' => true,
        ]);
    }

    public function profile(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        return view('account/profile', ['user' => Auth::user(), 'errors' => $errors, 'old' => $old ?? Auth::user(), 'pageTitle' => 'Mon profil', 'noindex' => true], $status);
    }

    public function profileUpdate(Request $r): Response
    {
        $v = Validator::make($r->post, ['first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'phone' => 'phone'],
            ['first_name' => 'Le prénom', 'last_name' => 'Le nom', 'phone' => 'Le téléphone']);
        if ($v->fails()) {
            return $this->profile($r, $v->errors, $r->post, 422);
        }
        DB::update('users', [
            'first_name' => $v->data['first_name'], 'last_name' => $v->data['last_name'], 'phone' => $v->data['phone'] ?: null,
            'marketing_opt_in' => $r->str('marketing') === '1' ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = ?', [(int) Auth::id()]);
        Session::flash('success', 'Profil mis à jour.');
        return redirect('/compte/profil');
    }

    public function passwordForm(Request $r, array $errors = [], int $status = 200): Response
    {
        return view('account/password', ['errors' => $errors, 'pageTitle' => 'Mot de passe', 'noindex' => true], $status);
    }

    public function passwordUpdate(Request $r): Response
    {
        $user = Auth::user();
        $current = (string) ($r->post['current_password'] ?? '');
        $new = (string) ($r->post['password'] ?? '');
        $errors = [];
        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Mot de passe actuel incorrect.';
        } elseif ($problem = AccountService::passwordProblem($new)) {
            $errors['password'] = $problem;
        } elseif ($new !== (string) ($r->post['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Les deux mots de passe ne correspondent pas.';
        }
        if ($errors) {
            return $this->passwordForm($r, $errors, 422);
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        DB::update('users', ['password_hash' => $hash, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $user['id']]);
        Session::regenerate();
        Auth::rememberPassword($hash);
        Session::flash('success', 'Mot de passe modifié.');
        return redirect('/compte');
    }

    public function addresses(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('account/addresses', [
            'addresses' => DB::all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id', [(int) Auth::id()]),
            'errors' => $errors, 'old' => $old, 'pageTitle' => 'Mes adresses', 'noindex' => true,
        ], $status);
    }

    public function addressStore(Request $r): Response
    {
        $uid = (int) Auth::id();
        $v = Validator::make($r->post, [
            'label' => 'required|max:60', 'recipient_name' => 'required|max:150', 'phone' => 'required|phone',
            'city' => 'required|max:100', 'district' => 'max:150', 'address_line' => 'required|max:255', 'landmark' => 'max:255',
        ], ['label' => 'Le libellé', 'recipient_name' => 'Le destinataire', 'phone' => 'Le téléphone', 'city' => 'La ville', 'district' => 'La commune / le quartier', 'address_line' => 'L\'adresse', 'landmark' => 'Le repère']);
        if ((int) DB::value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$uid]) >= 10) {
            $v->errors['_'] = 'Vous pouvez enregistrer 10 adresses au maximum.';
        }
        if ($v->fails()) {
            return $this->addresses($r, $v->errors, $r->post, 422);
        }
        $default = $r->str('is_default') === '1';
        if ($default) {
            DB::run('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$uid]);
        }
        DB::insert('addresses', $v->data + ['user_id' => $uid, 'is_default' => $default ? 1 : 0, 'created_at' => gmdate('Y-m-d H:i:s')]);
        Session::flash('success', 'Adresse enregistrée.');
        return redirect('/compte/adresses');
    }

    public function addressDelete(Request $r): Response
    {
        $n = DB::run('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int) $r->params['id'], (int) Auth::id()])->rowCount();
        if ($n === 0) {
            throw new HttpException(404);
        }
        Session::flash('success', 'Adresse supprimée.');
        return redirect('/compte/adresses');
    }
}
