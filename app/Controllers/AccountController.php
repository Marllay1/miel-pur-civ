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
        return view('account/login', ['errors' => $errors, 'old' => $old, 'retour' => self::customerReturn($r->str('retour')), 'pageTitle' => t('account.login_title'), 'noindex' => true], $status);
    }

    /** Retour possible pour un client : page locale du site public uniquement (jamais l'administration ni les pages de connexion). */
    public static function customerReturn(?string $path): string
    {
        $path = safe_return($path, '/compte');
        $plain = (string) strtok($path, '?');
        foreach (['/admin', '/compte/connexion', '/compte/inscription', '/compte/deconnexion'] as $blocked) {
            if ($plain === $blocked || str_starts_with($plain, $blocked . '/')) {
                return '/compte';
            }
        }
        return $path;
    }

    /**
     * Destination après connexion selon le rôle :
     *  - client : la page d'où il venait (site public) ou son compte ;
     *  - équipe : la page publique d'où elle venait (ex. reprise d'une commande) ou, à défaut, l'administration.
     */
    public static function afterLogin(array $user, ?string $retour): string
    {
        $target = self::customerReturn($retour);
        if ($user['role'] !== 'customer') {
            return is_string($retour) && $retour !== '' && $target !== '/compte' ? $target : '/admin';
        }
        return $target;
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
        Session::flash('success', t('account.welcome_back', ['name' => $user['first_name']]));
        return redirect(self::afterLogin($user, $r->str('retour')));
    }

    public function registerForm(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('account/register', ['errors' => $errors, 'old' => $old, 'pageTitle' => t('account.register_title'), 'noindex' => true], $status);
    }

    public function register(Request $r): Response
    {
        $v = Validator::make($r->post, [
            'first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'email' => 'required|email',
            'phone' => 'phone', 'password' => 'required', 'password_confirmation' => 'required|same:password', 'consent' => 'accepted',
        ], ['first_name' => t('field.first_name'), 'last_name' => t('field.last_name'), 'email' => t('field.email_label'), 'phone' => t('field.phone'), 'password' => t('field.password'), 'password_confirmation' => t('field.password_confirmation')]);
        $errors = $v->errors;
        $password = (string) ($r->post['password'] ?? '');
        if (!isset($errors['password']) && ($problem = AccountService::passwordProblem($password))) {
            $errors['password'] = $problem;
        }
        if ($spam = spam_check($r)) {
            $errors['_'] = $spam;
        }
        if (!$errors && !\App\Core\RateLimiter::hit('register:' . $r->ip(), 5, 3600)) {
            $errors['_'] = t('account.register_throttled');
        }
        if (!$errors) {
            $result = AccountService::register($v->data + ['password' => $password, 'marketing_opt_in' => $r->str('marketing') === '1']);
            if ($result['ok']) {
                Auth::login($result['user']);
                CartService::mergeGuestCart($r, (int) $result['user']['id']);
                Session::flash('success', t('account.created'));
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
        Session::flash('success', t('account.logged_out'));
        return redirect('/');
    }

    public function forgotForm(Request $r): Response
    {
        return view('account/forgot', ['errors' => [], 'sent' => false, 'pageTitle' => t('account.forgot_title'), 'noindex' => true]);
    }

    public function forgot(Request $r): Response
    {
        $email = $r->str('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return view('account/forgot', ['errors' => ['email' => t('validation.email')], 'sent' => false, 'pageTitle' => t('account.forgot_title'), 'noindex' => true], 422);
        }
        AccountService::requestReset($email, $r->ip());
        return view('account/forgot', ['errors' => [], 'sent' => true, 'pageTitle' => t('account.forgot_title'), 'noindex' => true]);
    }

    public function resetForm(Request $r, array $errors = [], int $status = 200): Response
    {
        $valid = AccountService::findReset($r->params['token']) !== null;
        return view('account/reset', ['errors' => $errors, 'valid' => $valid, 'token' => $r->params['token'], 'pageTitle' => t('account.reset_title'), 'noindex' => true], $valid ? $status : 410);
    }

    public function reset(Request $r): Response
    {
        $password = (string) ($r->post['password'] ?? '');
        $errors = [];
        if ($problem = AccountService::passwordProblem($password)) {
            $errors['password'] = $problem;
        } elseif ($password !== (string) ($r->post['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = t('validation.passwords_mismatch');
        }
        if ($errors) {
            return $this->resetForm($r, $errors, 422);
        }
        if (!AccountService::resetPassword($r->params['token'], $password)) {
            return $this->resetForm($r, [], 410);
        }
        Session::flash('success', t('account.password_reset_done'));
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
            'pageTitle' => t('account.title'), 'noindex' => true,
        ]);
    }

    public function orders(Request $r): Response
    {
        return view('account/orders', [
            'orders' => DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC', [(int) Auth::id()]),
            'pageTitle' => t('account.orders'), 'noindex' => true,
        ]);
    }

    /** Messages envoyés depuis le formulaire de contact en étant connecté, avec les réponses envoyées par l'équipe. */
    public function messages(Request $r): Response
    {
        $messages = DB::all('SELECT id, reason, subject, message, status, created_at FROM contact_messages WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 100', [(int) Auth::id()]);
        $replies = [];
        if ($messages) {
            $ids = array_map('intval', array_column($messages, 'id'));
            foreach (DB::all('SELECT message_id, body, created_at FROM message_replies WHERE message_id IN (' . implode(',', $ids) . ') ORDER BY id') as $x) {
                $replies[(int) $x['message_id']][] = $x;
            }
        }
        return view('account/messages', ['messages' => $messages, 'replies' => $replies, 'pageTitle' => t('account.messages'), 'noindex' => true]);
    }

    public function profile(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        return view('account/profile', ['user' => Auth::user(), 'errors' => $errors, 'old' => $old ?? Auth::user(), 'pageTitle' => t('account.profile'), 'noindex' => true], $status);
    }

    public function profileUpdate(Request $r): Response
    {
        $v = Validator::make($r->post, ['first_name' => 'required|max:100', 'last_name' => 'required|max:100', 'phone' => 'phone'],
            ['first_name' => t('field.first_name'), 'last_name' => t('field.last_name'), 'phone' => t('field.phone')]);
        if ($v->fails()) {
            return $this->profile($r, $v->errors, $r->post, 422);
        }
        DB::update('users', [
            'first_name' => $v->data['first_name'], 'last_name' => $v->data['last_name'], 'phone' => $v->data['phone'] ?: null,
            'marketing_opt_in' => $r->str('marketing') === '1' ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = ?', [(int) Auth::id()]);
        Session::flash('success', t('account.profile_saved'));
        return redirect('/compte/profil');
    }

    public function passwordForm(Request $r, array $errors = [], int $status = 200): Response
    {
        return view('account/password', ['errors' => $errors, 'pageTitle' => t('account.password'), 'noindex' => true], $status);
    }

    public function passwordUpdate(Request $r): Response
    {
        $user = Auth::user();
        $current = (string) ($r->post['current_password'] ?? '');
        $new = (string) ($r->post['password'] ?? '');
        $errors = [];
        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = t('account.current_password_wrong');
        } elseif ($problem = AccountService::passwordProblem($new)) {
            $errors['password'] = $problem;
        } elseif ($new !== (string) ($r->post['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = t('validation.passwords_mismatch');
        }
        if ($errors) {
            return $this->passwordForm($r, $errors, 422);
        }
        $hash = password_hash($new, PASSWORD_DEFAULT);
        DB::update('users', ['password_hash' => $hash, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $user['id']]);
        Session::regenerate();
        Auth::rememberPassword($hash);
        Session::flash('success', t('account.password_changed'));
        return redirect('/compte');
    }

    public function addresses(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('account/addresses', [
            'addresses' => DB::all('SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id', [(int) Auth::id()]),
            'errors' => $errors, 'old' => $old, 'pageTitle' => t('account.addresses'), 'noindex' => true,
        ], $status);
    }

    public function addressStore(Request $r): Response
    {
        $uid = (int) Auth::id();
        $v = Validator::make($r->post, [
            'label' => 'required|max:60', 'recipient_name' => 'required|max:150', 'phone' => 'required|phone',
            'city' => 'required|max:100', 'district' => 'max:150', 'address_line' => 'required|max:255', 'landmark' => 'max:255',
        ], ['label' => t('field.label'), 'recipient_name' => t('field.recipient'), 'phone' => t('field.phone'), 'city' => t('field.city'), 'district' => t('field.district'), 'address_line' => t('field.address'), 'landmark' => t('field.landmark')]);
        if ((int) DB::value('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$uid]) >= 10) {
            $v->errors['_'] = t('account.address_limit');
        }
        if ($v->fails()) {
            return $this->addresses($r, $v->errors, $r->post, 422);
        }
        $default = $r->str('is_default') === '1';
        if ($default) {
            DB::run('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$uid]);
        }
        DB::insert('addresses', $v->data + ['user_id' => $uid, 'is_default' => $default ? 1 : 0, 'created_at' => gmdate('Y-m-d H:i:s')]);
        Session::flash('success', t('account.address_saved'));
        return redirect('/compte/adresses');
    }

    public function addressDelete(Request $r): Response
    {
        $n = DB::run('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int) $r->params['id'], (int) Auth::id()])->rowCount();
        if ($n === 0) {
            throw new HttpException(404);
        }
        Session::flash('success', t('account.address_deleted'));
        return redirect('/compte/adresses');
    }
}
