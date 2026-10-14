<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Services\Settings;

/** Indicateurs calculés uniquement à partir des données réelles de la base. */
final class DashboardController extends Base
{
    public function index(Request $r): Response
    {
        $data = ['pageTitle' => 'Tableau de bord', 'kpi' => null, 'recent' => [], 'lowStock' => [], 'todo' => []];
        if (Auth::can('reports.view') || Auth::can('orders.manage')) {
            $since30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
            $since7 = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
            $today = gmdate('Y-m-d 00:00:00');
            $data['kpi'] = [
                'orders_today' => (int) DB::value('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$today]),
                'orders_7d' => (int) DB::value('SELECT COUNT(*) FROM orders WHERE created_at >= ?', [$since7]),
                'orders_30d' => (int) DB::value("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled')", [$since30]),
                'paid_30d' => (int) DB::value("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid' AND COALESCE(paid_at, created_at) >= ?", [$since30]),
                'pending_value' => (int) DB::value("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('pending_confirmation', 'preparing', 'shipped') AND payment_status <> 'paid'"),
                'to_confirm' => (int) DB::value("SELECT COUNT(*) FROM orders WHERE status = 'pending_confirmation'"),
                'to_prepare' => (int) DB::value("SELECT COUNT(*) FROM orders WHERE status IN ('paid', 'preparing')"),
                'awaiting_payment' => (int) DB::value("SELECT COUNT(*) FROM orders WHERE status = 'pending_payment'"),
                'customers' => (int) DB::value("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'customer'"),
            ];
            $data['recent'] = DB::all('SELECT * FROM orders ORDER BY created_at DESC, id DESC LIMIT 8');
        }
        if (Auth::can('catalog.manage') || Auth::can('reports.view')) {
            $data['lowStock'] = DB::all(
                'SELECT v.id, v.label, v.sku, v.stock, p.name AS product_name, p.id AS product_id FROM product_variants v JOIN products p ON p.id = v.product_id
                 WHERE v.deleted_at IS NULL AND v.is_active = 1 AND p.deleted_at IS NULL AND v.stock <= ? ORDER BY v.stock, p.name LIMIT 10',
                [Settings::int('low_stock_threshold')]
            );
        }
        if (Auth::can('content.manage')) {
            $data['todo']['Messages non lus'] = [(int) DB::value("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"), '/admin/messages'];
            $data['todo']['Avis à modérer'] = [(int) DB::value("SELECT COUNT(*) FROM reviews WHERE status = 'pending'"), '/admin/avis'];
            $data['todo']['Pages légales non validées'] = [(int) DB::value('SELECT COUNT(*) FROM pages WHERE is_legal = 1 AND is_validated = 0'), '/admin/pages'];
        }
        if (Auth::can('orders.manage')) {
            $data['todo']['Demandes de devis nouvelles'] = [(int) DB::value("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"), '/admin/devis'];
        }
        if (Auth::can('settings.manage')) {
            $missing = 0;
            foreach (Settings::definitions() as $key => $def) {
                if (str_contains((string) $def['default'], '[À COMPLÉTER') && Settings::isPlaceholder($key)) {
                    $missing++;
                }
            }
            $data['todo']['Réglages à compléter'] = [$missing, '/admin/parametres'];
        }
        return admin_view('dashboard', $data);
    }
}
