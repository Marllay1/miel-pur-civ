<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\PageController;
use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class QuoteController extends Base
{
    public const STATUSES = ['new' => 'Nouvelle', 'in_progress' => 'En cours', 'answered' => 'Devis envoyé', 'closed' => 'Clôturée'];

    public function index(Request $r): Response
    {
        $status = $r->str('statut');
        $quotes = isset(self::STATUSES[$status])
            ? DB::all('SELECT * FROM quote_requests WHERE status = ? ORDER BY created_at DESC LIMIT 200', [$status])
            : DB::all('SELECT * FROM quote_requests ORDER BY created_at DESC LIMIT 200');
        return admin_view('quotes', ['quotes' => $quotes, 'status' => $status, 'statuses' => self::STATUSES, 'activities' => PageController::ACTIVITIES, 'pageTitle' => 'Devis professionnels']);
    }

    public function update(Request $r): Response
    {
        $q = DB::one('SELECT * FROM quote_requests WHERE id = ?', [(int) $r->params['id']]);
        if ($q === null) {
            throw new HttpException(404);
        }
        $status = $r->str('status');
        if (!isset(self::STATUSES[$status])) {
            Session::flash('error', 'Statut invalide.');
            return redirect('/admin/devis');
        }
        DB::update('quote_requests', ['status' => $status, 'admin_note' => mb_substr($r->str('admin_note'), 0, 5000) ?: null, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $q['id']]);
        Audit::log('quote.update', 'quote', (int) $q['id'], ['status' => $status]);
        Session::flash('success', 'Demande de devis mise à jour.');
        return redirect('/admin/devis');
    }
}
