<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class CustomerController extends Base
{
    public function index(Request $r): Response
    {
        $q = mb_substr($r->str('q'), 0, 80);
        $params = [];
        $where = "r.name = 'customer'";
        if ($q !== '') {
            $where .= ' AND (u.email LIKE ? OR u.last_name LIKE ? OR u.first_name LIKE ? OR u.phone LIKE ?)';
            $params = array_fill(0, 4, '%' . $q . '%');
        }
        $customers = DB::all(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.is_active, u.created_at, u.last_login_at,
               (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS order_count,
               (SELECT COALESCE(SUM(o.total), 0) FROM orders o WHERE o.user_id = u.id AND o.payment_status = 'paid') AS paid_total
             FROM users u JOIN roles r ON r.id = u.role_id WHERE $where ORDER BY u.created_at DESC LIMIT 300",
            $params
        );
        return admin_view('customers/index', ['customers' => $customers, 'q' => $q, 'pageTitle' => 'Clients']);
    }

    public function show(Request $r): Response
    {
        $u = DB::one("SELECT u.* FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND r.name = 'customer'", [(int) $r->params['id']]);
        if ($u === null) {
            throw new HttpException(404);
        }
        Audit::log('customer.view', 'user', (int) $u['id']);
        return admin_view('customers/show', [
            'customer' => $u,
            'orders' => DB::all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', [(int) $u['id']]),
            'addresses' => DB::all('SELECT * FROM addresses WHERE user_id = ?', [(int) $u['id']]),
            'pageTitle' => $u['first_name'] . ' ' . $u['last_name'],
        ]);
    }
}
