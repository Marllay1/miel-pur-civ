<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\ImageService;
use App\Services\PaymentMethods;
use App\Services\PaymentService;

/**
 * Administration → Moyens de paiement : activation, logo, nom, ordre, description et instructions de chaque moyen,
 * ajout de moyens de paiement manuels. Un moyen « prestataire » n'est proposé que si ses clés sont configurées (.env).
 */
final class PaymentMethodsController extends Base
{
    public function index(Request $r): Response
    {
        $rows = [];
        foreach (PaymentMethods::all() as $code => $m) {
            $gateway = $m['kind'] === 'gateway' ? PaymentService::gateway($code) : null;
            $env = [];
            foreach ($gateway?->requiredEnv() ?? [] as $var) {
                $env[$var] = (string) Env::get($var, '') !== '';
            }
            $rows[$code] = $m + ['status' => PaymentMethods::status($m), 'env' => $env, 'logos' => PaymentMethods::logos($m + ['show_logo' => 1])];
        }
        return admin_view('payments/index', ['methods' => $rows, 'kinds' => PaymentMethods::KINDS, 'pageTitle' => 'Moyens de paiement']);
    }

    private function find(string $code): array
    {
        $m = PaymentMethods::find($code);
        if ($m === null) {
            throw new HttpException(404, 'Moyen de paiement introuvable.');
        }
        return $m;
    }

    public function edit(Request $r, array $errors = [], ?array $old = null): Response
    {
        $m = $this->find((string) $r->params['code']);
        return admin_view('payments/form', [
            'method' => $m, 'old' => $old ?? $m, 'errors' => $errors, 'status' => PaymentMethods::status($m),
            'logos' => PaymentMethods::logos($m + ['show_logo' => 1]), 'kinds' => PaymentMethods::KINDS, 'pageTitle' => $m['name'],
        ], $errors ? 422 : 200);
    }

    private function validate(Request $r): array
    {
        $v = Validator::make($r->post, [
            'name' => 'required|max:80', 'name_en' => 'max:80', 'description' => 'max:255', 'description_en' => 'max:255', 'sort_order' => 'int|min:0|max:1000',
            'merchant_number' => 'max:60', 'receiver_number' => 'max:60', 'beneficiary' => 'max:120', 'instructions' => 'max:3000', 'instructions_en' => 'max:3000',
        ], ['name' => 'Le nom affiché', 'sort_order' => 'L\'ordre', 'receiver_number' => 'Le numéro de réception', 'beneficiary' => 'Le bénéficiaire']);
        foreach (['merchant_number', 'receiver_number'] as $f) {
            $val = (string) ($v->data[$f] ?? '');
            if ($val !== '' && !preg_match('/^[0-9 +().\/-]{4,60}$/', $val)) {
                $v->errors[$f] = 'Numéro invalide (chiffres, espaces, + . - ( ) uniquement).';
            }
        }
        $data = [];
        foreach (['name', 'name_en', 'description', 'description_en', 'merchant_number', 'receiver_number', 'beneficiary', 'instructions', 'instructions_en'] as $f) {
            $data[$f] = trim((string) ($v->data[$f] ?? '')) ?: null;
        }
        $data['name'] = (string) $data['name'];
        $data['sort_order'] = (int) ($v->data['sort_order'] ?? 0);
        $data['is_enabled'] = $r->str('is_enabled') === '1' ? 1 : 0;
        $data['show_logo'] = $r->str('show_logo') === '1' ? 1 : 0;
        return [$data, $v->errors];
    }

    public function update(Request $r): Response
    {
        $m = $this->find((string) $r->params['code']);
        [$data, $errors] = $this->validate($r);
        if ($errors) {
            return $this->edit($r, $errors, $r->post + $m);
        }
        DB::update('payment_methods', $data + ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $m['id']]);
        PaymentMethods::flush();
        Audit::log('payment_method.update', 'payment_method', $m['code'], ['enabled' => $data['is_enabled'], 'show_logo' => $data['show_logo']]);
        $fresh = PaymentMethods::find($m['code']);
        [$state, $label] = PaymentMethods::status($fresh);
        Session::flash($state === 'missing' ? 'error' : 'success', 'Moyen de paiement enregistré. État : ' . $label . '.');
        return redirect('/admin/paiements/' . $m['code']);
    }

    /** Activation rapide depuis la liste. */
    public function toggle(Request $r): Response
    {
        $m = $this->find((string) $r->params['code']);
        $enabled = (int) $m['is_enabled'] === 1 ? 0 : 1;
        DB::update('payment_methods', ['is_enabled' => $enabled, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $m['id']]);
        PaymentMethods::flush();
        Audit::log('payment_method.toggle', 'payment_method', $m['code'], ['enabled' => $enabled]);
        Session::flash('success', $m['name'] . ' : ' . PaymentMethods::status(PaymentMethods::find($m['code']))[1] . '.');
        return redirect('/admin/paiements');
    }

    /** Nouveau moyen de paiement manuel (ex. Moov Money, virement) : réglé hors du site, confirmé par l'équipe. */
    public function store(Request $r): Response
    {
        $name = trim($r->str('name'));
        $code = trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(slugify($name))) ?? '', '_');
        if ($name === '' || mb_strlen($name) > 80) {
            Session::flash('error', 'Indiquez le nom du moyen de paiement (80 caractères maximum).');
            return redirect('/admin/paiements');
        }
        if ($code === '' || PaymentMethods::find($code) !== null || in_array($code, ['demo', 'cod'], true)) {
            Session::flash('error', 'Un moyen de paiement porte déjà ce nom.');
            return redirect('/admin/paiements');
        }
        $now = gmdate('Y-m-d H:i:s');
        DB::insert('payment_methods', ['code' => $code, 'kind' => 'manual', 'name' => $name, 'show_logo' => 1, 'is_enabled' => 0,
            'sort_order' => count(PaymentMethods::all()), 'created_at' => $now, 'updated_at' => $now]);
        PaymentMethods::flush();
        Audit::log('payment_method.create', 'payment_method', $code, ['name' => $name]);
        Session::flash('success', 'Moyen de paiement ajouté (désactivé). Renseignez les instructions puis activez-le.');
        return redirect('/admin/paiements/' . $code);
    }

    /** Logo personnalisé (PNG, JPEG, WebP ; ré-encodé). Les logos officiels fournis restent utilisables. */
    public function uploadLogo(Request $r): Response
    {
        $m = $this->find((string) $r->params['code']);
        $file = $r->files['logo'] ?? null;
        $result = is_array($file) ? ImageService::store($file, 'brand/payments', 600, ['minWidth' => 80, 'minHeight' => 24]) : ['ok' => false, 'error' => 'Choisissez une image.'];
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            return redirect('/admin/paiements/' . $m['code']);
        }
        foreach (PaymentMethods::logos($m + ['show_logo' => 1]) as $old) {
            ImageService::delete($old); // seuls les fichiers téléversés (/uploads/) sont supprimés
        }
        DB::update('payment_methods', ['logo_paths' => $result['path'], 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $m['id']]);
        PaymentMethods::flush();
        Audit::log('payment_method.logo', 'payment_method', $m['code'], ['path' => $result['path']]);
        Session::flash('success', 'Logo enregistré.');
        return redirect('/admin/paiements/' . $m['code']);
    }

    /** Suppression : moyens manuels ajoutés et jamais utilisés seulement (sinon : désactiver). */
    public function delete(Request $r): Response
    {
        $m = $this->find((string) $r->params['code']);
        if ($m['kind'] !== 'manual' || $m['code'] === 'moov_money') {
            Session::flash('error', 'Ce moyen de paiement fait partie du site : désactivez-le plutôt que de le supprimer.');
            return redirect('/admin/paiements/' . $m['code']);
        }
        if ((int) DB::value('SELECT COUNT(*) FROM orders WHERE payment_method = ?', [$m['code']]) > 0) {
            Session::flash('error', 'Des commandes utilisent ce moyen de paiement : désactivez-le plutôt que de le supprimer.');
            return redirect('/admin/paiements/' . $m['code']);
        }
        foreach (PaymentMethods::logos($m + ['show_logo' => 1]) as $old) {
            ImageService::delete($old);
        }
        DB::run('DELETE FROM payment_methods WHERE id = ?', [(int) $m['id']]);
        PaymentMethods::flush();
        Audit::log('payment_method.delete', 'payment_method', $m['code']);
        Session::flash('success', 'Moyen de paiement supprimé.');
        return redirect('/admin/paiements');
    }
}
