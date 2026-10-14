<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\PromoService;

final class PromoController extends Base
{
    public function index(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        $edit = $r->int('id') ? DB::one('SELECT * FROM promo_codes WHERE id = ?', [(int) $r->int('id')]) : null;
        return admin_view('promos', [
            'promos' => DB::all('SELECT * FROM promo_codes ORDER BY is_active DESC, created_at DESC'),
            'edit' => $edit, 'old' => $old ?? ($edit ?? ['is_active' => 1, 'type' => 'percent']), 'errors' => $errors, 'pageTitle' => 'Codes promo',
        ], $status);
    }

    public function save(Request $r): Response
    {
        $id = (int) $r->int('id', 0);
        $v = Validator::make($r->post, [
            'code' => 'required|max:40', 'description' => 'max:255', 'type' => 'required|in:percent,fixed,free_shipping',
            'value' => 'int|min:0|max:100000000', 'min_subtotal' => 'int|min:0|max:100000000', 'max_uses' => 'int|min:1|max:1000000',
            'starts_at' => 'date', 'ends_at' => 'date',
        ], ['code' => 'Le code', 'type' => 'Le type', 'value' => 'La valeur', 'min_subtotal' => 'Le minimum', 'max_uses' => 'Le plafond']);
        $code = PromoService::normalize((string) $v->data['code']);
        if ($code === '' && !isset($v->errors['code'])) {
            $v->errors['code'] = 'Code invalide (lettres, chiffres, tirets).';
        }
        if (DB::value('SELECT id FROM promo_codes WHERE code = ? AND id <> ?', [$code, $id]) !== null) {
            $v->errors['code'] = 'Ce code existe déjà.';
        }
        $type = (string) $v->data['type'];
        $value = (int) ($v->data['value'] ?? 0);
        if ($type === 'percent' && ($value < 1 || $value > 100)) {
            $v->errors['value'] = 'Un pourcentage doit être compris entre 1 et 100.';
        }
        if ($type === 'fixed' && $value < 1) {
            $v->errors['value'] = 'Indiquez le montant de la remise.';
        }
        $starts = $this->datetime($r->str('starts_at'));
        $ends = $this->datetime($r->str('ends_at'));
        if ($starts && $ends && $ends <= $starts) {
            $v->errors['ends_at'] = 'La fin doit être postérieure au début.';
        }
        if ($v->fails()) {
            return $this->index($r, $v->errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'code' => $code, 'description' => $v->data['description'] ?: null, 'type' => $type, 'value' => $type === 'free_shipping' ? 0 : $value,
            'min_subtotal' => (int) ($v->data['min_subtotal'] ?? 0), 'starts_at' => $starts, 'ends_at' => $ends, 'max_uses' => $v->data['max_uses'],
            'is_active' => $r->str('is_active') === '1' ? 1 : 0, 'updated_at' => $now,
        ];
        if ($id > 0 && DB::value('SELECT id FROM promo_codes WHERE id = ?', [$id]) !== null) {
            DB::update('promo_codes', $data, 'id = ?', [$id]);
            Audit::log('promo.update', 'promo', $id, $data);
        } else {
            $id = DB::insert('promo_codes', $data + ['used_count' => 0, 'created_at' => $now]);
            Audit::log('promo.create', 'promo', $id, $data);
        }
        Session::flash('success', 'Code promo « ' . $code . ' » enregistré.');
        return redirect('/admin/promotions');
    }
}
