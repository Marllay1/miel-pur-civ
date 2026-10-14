<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Currencies;

/**
 * Administration → Devises d'affichage. Le paiement reste en francs CFA ; les devises activées ne servent qu'à afficher
 * un équivalent indicatif, avec le taux saisi ici (aucune conversion automatique).
 */
final class CurrenciesController extends Base
{
    public function index(Request $r, array $errors = []): Response
    {
        return admin_view('currencies/index', ['currencies' => Currencies::all(), 'errors' => $errors, 'pageTitle' => 'Devises d\'affichage'], $errors ? 422 : 200);
    }

    public function update(Request $r): Response
    {
        $c = DB::one('SELECT * FROM currencies WHERE id = ?', [(int) $r->params['id']]);
        if ($c === null) {
            throw new HttpException(404);
        }
        $rate = str_replace([' ', ','], ['', '.'], $r->str('xof_per_unit'));
        $enabled = $r->str('is_enabled') === '1' ? 1 : 0;
        $errors = [];
        if ($rate !== '' && (!is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 1000000)) {
            $errors['rate_' . $c['id']] = $c['code'] . ' : taux invalide (nombre de FCFA pour 1 ' . $c['code'] . ').';
        }
        if ($enabled && $rate === '') {
            $errors['rate_' . $c['id']] = $c['code'] . ' : renseignez le taux avant d\'activer la devise.';
        }
        $symbol = trim($r->str('symbol'));
        if ($symbol === '' || mb_strlen($symbol) > 8) {
            $errors['symbol_' . $c['id']] = $c['code'] . ' : symbole requis (8 caractères maximum).';
        }
        $decimals = (int) $r->str('decimals');
        if ($decimals < 0 || $decimals > 3) {
            $errors['decimals_' . $c['id']] = $c['code'] . ' : 0 à 3 décimales.';
        }
        if ($errors) {
            return $this->index($r, $errors);
        }
        DB::update('currencies', ['xof_per_unit' => $rate === '' ? null : round((float) $rate, 4), 'symbol' => $symbol, 'decimals' => $decimals,
            'is_enabled' => $enabled, 'sort_order' => max(0, (int) $r->str('sort_order')), 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $c['id']]);
        Currencies::flush();
        Audit::log('currency.update', 'currency', $c['code'], ['rate' => $rate, 'enabled' => $enabled]);
        Session::flash('success', $c['code'] . ' enregistrée.' . ($enabled ? ' Les équivalents indicatifs sont affichés sur le site.' : ''));
        return redirect('/admin/devises');
    }

    public function store(Request $r): Response
    {
        $code = strtoupper(trim($r->str('code')));
        $name = trim($r->str('name'));
        $symbol = trim($r->str('symbol'));
        if (!preg_match('/^[A-Z]{3}$/', $code) || $code === 'XOF') {
            return $this->index($r, ['code' => 'Code ISO 4217 à 3 lettres attendu (ex. GBP), autre que XOF.']);
        }
        if ($name === '' || mb_strlen($name) > 40 || $symbol === '' || mb_strlen($symbol) > 8) {
            return $this->index($r, ['code' => 'Nom (40 caractères maximum) et symbole (8 caractères maximum) requis.']);
        }
        if (DB::value('SELECT id FROM currencies WHERE code = ?', [$code]) !== null) {
            return $this->index($r, ['code' => 'Cette devise existe déjà.']);
        }
        DB::insert('currencies', ['code' => $code, 'name' => $name, 'symbol' => $symbol, 'xof_per_unit' => null, 'decimals' => 2, 'is_enabled' => 0,
            'sort_order' => count(Currencies::all()), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        Audit::log('currency.create', 'currency', $code);
        Session::flash('success', $code . ' ajoutée (désactivée) : renseignez son taux puis activez-la.');
        return redirect('/admin/devises');
    }

    public function delete(Request $r): Response
    {
        $c = DB::one('SELECT * FROM currencies WHERE id = ?', [(int) $r->params['id']]);
        if ($c !== null) {
            DB::run('DELETE FROM currencies WHERE id = ?', [(int) $c['id']]);
            Currencies::flush();
            Audit::log('currency.delete', 'currency', $c['code']);
            Session::flash('success', $c['code'] . ' supprimée.');
        }
        return redirect('/admin/devises');
    }
}
