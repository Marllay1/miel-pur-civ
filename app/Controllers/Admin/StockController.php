<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Settings;

final class StockController extends Base
{
    public function index(Request $r): Response
    {
        $onlyLow = $r->str('faible') === '1';
        $threshold = Settings::int('low_stock_threshold');
        $variants = DB::all(
            'SELECT v.*, p.name AS product_name FROM product_variants v JOIN products p ON p.id = v.product_id
             WHERE v.deleted_at IS NULL AND p.deleted_at IS NULL' . ($onlyLow ? ' AND v.stock <= ' . $threshold : '') . ' ORDER BY p.name, v.sort_order, v.id'
        );
        $movements = DB::all(
            'SELECT m.*, v.sku, v.label, p.name AS product_name, u.first_name, u.last_name FROM stock_movements m
             JOIN product_variants v ON v.id = m.variant_id JOIN products p ON p.id = v.product_id LEFT JOIN users u ON u.id = m.user_id
             ORDER BY m.id DESC LIMIT 40'
        );
        return admin_view('stock', ['variants' => $variants, 'movements' => $movements, 'threshold' => $threshold, 'onlyLow' => $onlyLow, 'pageTitle' => 'Stock']);
    }

    /** Ajustement : nouvelle quantité constatée + motif. Verrou optimiste contre les ventes simultanées. */
    public function adjust(Request $r): Response
    {
        $variant = DB::one('SELECT * FROM product_variants WHERE id = ? AND deleted_at IS NULL', [(int) $r->params['id']]);
        if ($variant === null) {
            throw new HttpException(404);
        }
        $new = $r->int('stock');
        $expected = $r->int('current');
        $note = mb_substr($r->str('note'), 0, 255);
        if ($new === null || $new < 0 || $new > 1000000) {
            Session::flash('error', 'Quantité invalide.');
            return redirect('/admin/stock');
        }
        if ($note === '') {
            Session::flash('error', 'Indiquez le motif de l\'ajustement (inventaire, casse, réception…).');
            return redirect('/admin/stock');
        }
        $delta = $new - (int) $variant['stock'];
        if ($delta === 0) {
            Session::flash('info', 'Aucun changement.');
            return redirect('/admin/stock');
        }
        $n = DB::transaction(static function () use ($variant, $new, $expected, $delta, $note): int {
            $n = DB::run('UPDATE product_variants SET stock = ?, updated_at = ? WHERE id = ? AND stock = ?', [$new, gmdate('Y-m-d H:i:s'), (int) $variant['id'], $expected ?? (int) $variant['stock']])->rowCount();
            if ($n === 1) {
                DB::insert('stock_movements', ['variant_id' => (int) $variant['id'], 'quantity' => $delta, 'reason' => 'adjust', 'reference' => null, 'user_id' => \App\Core\Auth::id(), 'note' => $note, 'created_at' => gmdate('Y-m-d H:i:s')]);
            }
            return $n;
        });
        if ($n !== 1) {
            Session::flash('error', 'Le stock a changé entre-temps (vente en cours ?). Vérifiez la nouvelle valeur et recommencez.');
            return redirect('/admin/stock');
        }
        Audit::log('stock.adjust', 'variant', (int) $variant['id'], ['sku' => $variant['sku'], 'from' => (int) $variant['stock'], 'to' => $new, 'note' => $note]);
        Session::flash('success', sprintf('Stock de %s : %d → %d.', $variant['sku'], (int) $variant['stock'], $new));
        return redirect('/admin/stock');
    }
}
