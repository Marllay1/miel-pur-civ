<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PaymentPolicy;
use App\Services\Settings;

/**
 * Clients professionnels. Le statut « professionnel » ne donne aucun droit au paiement différé :
 * l'autorisation de paiement sur facture, le plafond et le délai sont accordés ici par un administrateur.
 */
final class ProAccountController extends Base
{
    public function index(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        $pros = DB::all('SELECT p.*, u.first_name, u.last_name, u.email, u.phone FROM pro_accounts p JOIN users u ON u.id = p.user_id ORDER BY p.company');
        foreach ($pros as &$p) {
            $p['outstanding'] = PaymentPolicy::outstanding((int) $p['user_id']);
        }
        unset($p);
        return admin_view('pros/index', ['pros' => $pros, 'errors' => $errors, 'old' => $old, 'pageTitle' => 'Clients professionnels'], $status);
    }

    private function find(Request $r): array
    {
        $p = DB::one('SELECT p.*, u.first_name, u.last_name, u.email, u.phone FROM pro_accounts p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [(int) $r->params['id']]);
        if ($p === null) {
            throw new HttpException(404, 'Compte professionnel introuvable.');
        }
        return $p;
    }

    /** @return array{0:array, 1:array} données validées, erreurs */
    private function validate(Request $r): array
    {
        $errors = [];
        $company = mb_substr(trim($r->str('company')), 0, 160);
        if ($company === '') {
            $errors['company'] = 'Indiquez le nom de l\'entreprise.';
        }
        $limit = trim($r->str('credit_limit'));
        if ($limit !== '' && !preg_match('/^\d{1,9}$/', $limit)) {
            $errors['credit_limit'] = 'Plafond : montant entier en FCFA, ou vide.';
        }
        $days = $r->str('payment_days') === '' ? Settings::int('invoice_default_days') : (int) $r->int('payment_days', -1);
        if ($days < 0 || $days > 365) {
            $errors['payment_days'] = 'Délai : de 0 à 365 jours.';
        }
        return [[
            'company' => $company, 'is_active' => $r->str('is_active') === '1' ? 1 : 0, 'invoice_allowed' => $r->str('invoice_allowed') === '1' ? 1 : 0,
            'credit_limit' => $limit === '' ? null : (int) $limit, 'payment_days' => $days, 'conditions' => mb_substr(trim($r->str('conditions')), 0, 2000) ?: null,
        ], $errors];
    }

    public function store(Request $r): Response
    {
        [$data, $errors] = $this->validate($r);
        $email = mb_strtolower(trim($r->str('email')));
        $user = DB::one("SELECT u.* FROM users u JOIN roles ro ON ro.id = u.role_id WHERE u.email = ? AND ro.name = 'customer'", [$email]);
        if ($user === null) {
            $errors['email'] = 'Aucun compte client avec cette adresse : le client doit d\'abord créer son compte sur le site.';
        } elseif (DB::value('SELECT id FROM pro_accounts WHERE user_id = ?', [(int) $user['id']]) !== null) {
            $errors['email'] = 'Ce client a déjà un compte professionnel.';
        }
        if ($errors) {
            return $this->index($r, $errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::insert('pro_accounts', $data + ['user_id' => (int) $user['id'], 'approved_by' => $data['invoice_allowed'] ? $this->uid() : null,
            'approved_at' => $data['invoice_allowed'] ? $now : null, 'created_at' => $now, 'updated_at' => $now]);
        Audit::log('pro.create', 'pro_account', $id, ['user' => (int) $user['id'], 'invoice_allowed' => $data['invoice_allowed'], 'credit_limit' => $data['credit_limit'], 'days' => $data['payment_days']]);
        Session::flash('success', 'Compte professionnel créé pour ' . $data['company'] . '.');
        return redirect('/admin/professionnels/' . $id);
    }

    public function edit(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        $p = $this->find($r);
        $orders = DB::all("SELECT id, number, status, payment_terms, payment_status, total, amount_paid, balance_due_at, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 50", [(int) $p['user_id']]);
        return admin_view('pros/edit', ['pro' => $p, 'old' => $old ?? $p, 'errors' => $errors, 'orders' => $orders, 'outstanding' => PaymentPolicy::outstanding((int) $p['user_id']),
            'pageTitle' => $p['company']], $status);
    }

    public function update(Request $r): Response
    {
        $p = $this->find($r);
        [$data, $errors] = $this->validate($r);
        if ($errors) {
            return $this->edit($r, $errors, $r->post + $p, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($data['invoice_allowed'] && !(int) $p['invoice_allowed']) {
            $data += ['approved_by' => $this->uid(), 'approved_at' => $now];
        }
        DB::update('pro_accounts', $data + ['updated_at' => $now], 'id = ?', [(int) $p['id']]);
        Audit::log('pro.update', 'pro_account', (int) $p['id'], ['invoice_allowed' => [(int) $p['invoice_allowed'], $data['invoice_allowed']],
            'credit_limit' => [$p['credit_limit'], $data['credit_limit']], 'days' => [(int) $p['payment_days'], $data['payment_days']], 'active' => $data['is_active']]);
        Session::flash('success', 'Conditions enregistrées.');
        return redirect('/admin/professionnels/' . $p['id']);
    }
}
