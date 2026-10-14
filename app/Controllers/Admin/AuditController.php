<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

final class AuditController extends Base
{
    public function index(Request $r): Response
    {
        $action = preg_replace('/[^a-z_.]/', '', $r->str('action')) ?? '';
        $where = $action !== '' ? 'WHERE a.action LIKE ?' : '';
        $params = $action !== '' ? [$action . '%'] : [];
        $per = 50;
        $total = (int) DB::value("SELECT COUNT(*) FROM audit_log a $where", $params);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($this->page($r), $pages);
        $rows = DB::all("SELECT a.*, u.first_name, u.last_name, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id $where ORDER BY a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        $actions = array_column(DB::all('SELECT DISTINCT action FROM audit_log ORDER BY action'), 'action');
        return admin_view('audit', ['rows' => $rows, 'action' => $action, 'actions' => $actions, 'page' => $page, 'pages' => $pages, 'total' => $total, 'pageTitle' => 'Journal d\'audit']);
    }
}
