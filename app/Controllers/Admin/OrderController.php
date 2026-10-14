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
use App\Services\PaymentLedger;
use App\Services\PaymentMethods;

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
        if ($f['paiement'] !== '' && ($f['paiement'] === 'demo' || PaymentMethods::find($f['paiement']) !== null)) {
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
            'transactions' => PaymentLedger::transactions((int) $o['id']),
            'balance' => PaymentLedger::balance($o),
            'refundable' => PaymentLedger::refundable($o),
            'ledgerMethods' => PaymentLedger::methods(),
            'pro' => $o['user_id'] !== null ? DB::one('SELECT * FROM pro_accounts WHERE user_id = ?', [(int) $o['user_id']]) : null,
            'pageTitle' => 'Commande ' . $o['number'],
        ]);
    }

    public function transition(Request $r): Response
    {
        $o = $this->find($r);
        try {
            OrderService::transition((int) $o['id'], $r->str('status'), $this->uid(), mb_substr($r->str('note'), 0, 1000),
                ['collected' => $r->str('collected') === '1', 'reference' => mb_substr($r->str('reference'), 0, 120)]);
            Session::flash('success', 'Statut mis à jour : ' . status_label($r->str('status')) . '.');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        return redirect('/admin/commandes/' . $o['id']);
    }

    /** Encaissement constaté par l'équipe (Mobile Money reçu, espèces, virement…). Le montant est contrôlé par le serveur. */
    public function recordPayment(Request $r): Response
    {
        $o = $this->find($r);
        $amount = $r->int('amount', 0);
        $method = $r->str('method');
        try {
            if (!array_key_exists($method, PaymentLedger::methods())) {
                throw new \DomainException('Moyen d\'encaissement inconnu.');
            }
            PaymentLedger::record((int) $o['id'], (int) $amount, $method, mb_substr($r->str('reference'), 0, 120), $this->uid(), mb_substr($r->str('note'), 0, 255));
            Session::flash('success', 'Encaissement de ' . money((int) $amount) . ' enregistré.');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        return redirect('/admin/commandes/' . $o['id'] . '#paiements');
    }

    public function refund(Request $r): Response
    {
        $o = $this->find($r);
        $method = $r->str('method');
        try {
            if (!array_key_exists($method, PaymentLedger::methods())) {
                throw new \DomainException('Moyen de remboursement inconnu.');
            }
            PaymentLedger::refund((int) $o['id'], (int) $r->int('amount', 0), mb_substr($r->str('reason'), 0, 255), $method, mb_substr($r->str('reference'), 0, 120),
                $this->uid(), $r->str('completed') === '1');
            Session::flash('success', $r->str('completed') === '1' ? 'Remboursement enregistré.' : 'Remboursement à effectuer enregistré.');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        return redirect('/admin/commandes/' . $o['id'] . '#paiements');
    }

    public function refundComplete(Request $r): Response
    {
        $tx = DB::one("SELECT * FROM order_transactions WHERE id = ? AND type = 'refund'", [(int) $r->params['id']]);
        if ($tx === null) {
            throw new HttpException(404);
        }
        try {
            PaymentLedger::completeRefund((int) $tx['id'], mb_substr($r->str('reference'), 0, 120), $this->uid());
            Session::flash('success', 'Remboursement marqué comme effectué.');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        return redirect('/admin/commandes/' . $tx['order_id'] . '#paiements');
    }

    /** Journal des encaissements et remboursements, pour le rapprochement avec les relevés des prestataires. */
    public function journal(Request $r): Response
    {
        [$rows, $f] = $this->journalRows($r, 300);
        return admin_view('payments/journal', ['rows' => $rows, 'f' => $f, 'methods' => PaymentLedger::methods(), 'pageTitle' => 'Journal des paiements',
            'totals' => [
                'in' => array_sum(array_map(static fn ($t) => $t['type'] === 'payment' && $t['status'] === 'completed' ? (int) $t['amount'] : 0, $rows)),
                'out' => array_sum(array_map(static fn ($t) => $t['type'] === 'refund' && $t['status'] === 'completed' ? (int) $t['amount'] : 0, $rows)),
                'pending' => array_sum(array_map(static fn ($t) => $t['type'] === 'refund' && $t['status'] === 'pending' ? (int) $t['amount'] : 0, $rows)),
            ]]);
    }

    public function journalExport(Request $r): Response
    {
        [$rows] = $this->journalRows($r, 10000);
        Audit::log('payments.export', 'payment', null, ['count' => count($rows)]);
        $methods = PaymentLedger::methods();
        $out = array_map(static fn ($t) => [$t['created_at'], $t['number'], $t['type'] === 'payment' ? 'Encaissement' : 'Remboursement', $t['status'] === 'completed' ? 'Effectué' : ($t['status'] === 'pending' ? 'À effectuer' : $t['status']),
            $methods[$t['method']] ?? $t['method'], $t['amount'], $t['reference'], $t['reason'], trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? '')) ?: 'Prestataire / système'], $rows);
        return $this->csv('paiements-' . gmdate('Ymd-His') . '.csv', ['Date (UTC)', 'Commande', 'Type', 'État', 'Moyen', 'Montant (FCFA)', 'Référence', 'Motif / note', 'Saisi par'], $out);
    }

    private function journalRows(Request $r, int $limit): array
    {
        $f = ['type' => in_array($r->str('type'), ['payment', 'refund'], true) ? $r->str('type') : '', 'du' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $r->str('du')) ? $r->str('du') : '',
            'au' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $r->str('au')) ? $r->str('au') : ''];
        $where = ['1 = 1'];
        $params = [];
        if ($f['type'] !== '') {
            $where[] = 't.type = ?';
            $params[] = $f['type'];
        }
        if ($f['du'] !== '') {
            $where[] = 't.created_at >= ?';
            $params[] = $f['du'] . ' 00:00:00';
        }
        if ($f['au'] !== '') {
            $where[] = 't.created_at <= ?';
            $params[] = $f['au'] . ' 23:59:59';
        }
        $rows = DB::all('SELECT t.*, o.number, u.first_name, u.last_name FROM order_transactions t JOIN orders o ON o.id = t.order_id LEFT JOIN users u ON u.id = t.user_id WHERE '
            . implode(' AND ', $where) . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ' . $limit, $params);
        return [$rows, $f];
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
