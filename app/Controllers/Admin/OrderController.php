<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\OrderService;

final class OrderController extends Base
{
    private function filters(Request $r): array
    {
        $where = ['1 = 1'];
        $params = [];
        $f = ['statut' => $r->str('statut'), 'q' => mb_substr($r->str('q'), 0, 80), 'du' => $r->str('du'), 'au' => $r->str('au'), 'paiement' => $r->str('paiement')];
        if (in_array($f['statut'], OrderService::STATUSES, true)) {
            $where[] = 'o.status = ?';
            $params[] = $f['statut'];
        } else {
            $f['statut'] = '';
        }
        if (in_array($f['paiement'], ['cod', 'demo', 'orange_money', 'mtn_momo', 'wave', 'card'], true)) {
            $where[] = 'o.payment_method = ?';
            $params[] = $f['paiement'];
        } else {
            $f['paiement'] = '';
        }
        if ($f['q'] !== '') {
            $like = '%' . $f['q'] . '%';
            $where[] = '(o.number LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR o.customer_last_name LIKE ? OR o.customer_first_name LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['du'])) {
            $where[] = 'o.created_at >= ?';
            $params[] = $f['du'] . ' 00:00:00';
        } else {
            $f['du'] = '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['au'])) {
            $where[] = 'o.created_at <= ?';
            $params[] = $f['au'] . ' 23:59:59';
        } else {
            $f['au'] = '';
        }
        return [$f, implode(' AND ', $where), $params];
    }

    public function index(Request $r): Response
    {
        [$f, $where, $params] = $this->filters($r);
        $per = 25;
        $total = (int) DB::value("SELECT COUNT(*) FROM orders o WHERE $where", $params);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($this->page($r), $pages);
        $orders = DB::all("SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items i WHERE i.order_id = o.id) AS item_count FROM orders o WHERE $where ORDER BY o.created_at DESC, o.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        $counts = [];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return admin_view('orders/index', ['orders' => $orders, 'f' => $f, 'total' => $total, 'page' => $page, 'pages' => $pages, 'counts' => $counts, 'pageTitle' => 'Commandes']);
    }

    public function export(Request $r): Response
    {
        [$f, $where, $params] = $this->filters($r);
        $rows = DB::all("SELECT o.* FROM orders o WHERE $where ORDER BY o.created_at DESC LIMIT 5000", $params);
        Audit::log('orders.export', 'order', null, ['filters' => array_filter($f), 'count' => count($rows)]);
        $out = [];
        foreach ($rows as $o) {
            $out[] = [$o['number'], $o['created_at'], status_label($o['status']), payment_method_label($o['payment_method']), payment_status_label($o['payment_status']),
                $o['customer_first_name'] . ' ' . $o['customer_last_name'], $o['customer_phone'], $o['customer_email'], $o['shipping_zone_name'], $o['shipping_city'],
                $o['subtotal'], $o['discount'], $o['shipping_fee'], $o['total'], $o['promo_code']];
        }
        return $this->csv('commandes-' . gmdate('Ymd-His') . '.csv',
            ['Numéro', 'Date (UTC)', 'Statut', 'Paiement', 'État du paiement', 'Client', 'Téléphone', 'E-mail', 'Zone', 'Ville', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Code promo'], $out);
    }

    private function find(Request $r): array
    {
        $o = OrderService::find((int) $r->params['id']);
        if ($o === null) {
            throw new HttpException(404, 'Commande introuvable.');
        }
        return $o;
    }

    public function show(Request $r): Response
    {
        $o = $this->find($r);
        return admin_view('orders/show', [
            'order' => $o,
            'items' => OrderService::items((int) $o['id']),
            'history' => OrderService::history((int) $o['id']),
            'payments' => DB::all('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [(int) $o['id']]),
            'shipment' => DB::one('SELECT * FROM shipments WHERE order_id = ?', [(int) $o['id']]),
            'transitions' => OrderService::allowedTransitions($o),
            'pageTitle' => 'Commande ' . $o['number'],
        ]);
    }

    public function transition(Request $r): Response
    {
        $o = $this->find($r);
        try {
            OrderService::transition((int) $o['id'], $r->str('status'), $this->uid(), mb_substr($r->str('note'), 0, 1000));
            Session::flash('success', 'Statut mis à jour : ' . status_label($r->str('status')) . '.');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        return redirect('/admin/commandes/' . $o['id']);
    }

    public function note(Request $r): Response
    {
        $o = $this->find($r);
        $note = mb_substr($r->str('note'), 0, 2000);
        if ($note === '') {
            Session::flash('error', 'La note est vide.');
        } else {
            OrderService::addNote((int) $o['id'], $note, $this->uid());
            Session::flash('success', 'Note interne ajoutée (non visible du client).');
        }
        return redirect('/admin/commandes/' . $o['id']);
    }

    public function shipment(Request $r): Response
    {
        $o = $this->find($r);
        $data = ['carrier' => mb_substr($r->str('carrier'), 0, 120) ?: null, 'tracking_number' => mb_substr($r->str('tracking_number'), 0, 120) ?: null, 'note' => mb_substr($r->str('note'), 0, 255) ?: null];
        OrderService::upsertShipment((int) $o['id'], $data);
        Audit::log('order.shipment', 'order', $o['number'], $data);
        Session::flash('success', 'Informations de livraison enregistrées.');
        return redirect('/admin/commandes/' . $o['id']);
    }
}
