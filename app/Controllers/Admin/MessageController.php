<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Settings;

/**
 * Boîte de réception des messages envoyés depuis le formulaire « Contactez-nous » (table contact_messages).
 * Réponse par e-mail seulement si l'envoi d'e-mails est réellement configuré ; sinon, lien mailto: vers l'adresse du visiteur.
 */
final class MessageController extends Base
{
    public const STATUSES = ['new' => 'Nouveau', 'read' => 'Lu', 'in_progress' => 'En cours de traitement', 'resolved' => 'Résolu', 'archived' => 'Archivé'];
    public const REASONS = ['product' => 'Question sur un produit', 'order' => 'Commande', 'delivery' => 'Livraison', 'payment' => 'Paiement', 'other' => 'Autre demande'];

    public function index(Request $r): Response
    {
        $status = array_key_exists($r->str('statut'), self::STATUSES) ? $r->str('statut') : '';
        $reason = array_key_exists($r->str('motif'), self::REASONS) ? $r->str('motif') : '';
        $q = mb_substr(trim($r->str('q')), 0, 80);
        $where = [$status === '' ? "m.status <> 'archived'" : 'm.status = ?'];
        $params = $status === '' ? [] : [$status];
        if ($reason !== '') {
            $where[] = 'm.reason = ?';
            $params[] = $reason;
        }
        if ($q !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $where[] = "(m.name LIKE ? ESCAPE '!' OR m.email LIKE ? ESCAPE '!' OR m.subject LIKE ? ESCAPE '!' OR m.message LIKE ? ESCAPE '!' OR m.phone LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like, $like, $like);
        }
        $per = 30;
        $total = (int) DB::value('SELECT COUNT(*) FROM contact_messages m WHERE ' . implode(' AND ', $where), $params);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($this->page($r), $pages);
        $messages = DB::all('SELECT m.*, (SELECT COUNT(*) FROM message_replies x WHERE x.message_id = m.id) AS reply_count FROM contact_messages m WHERE ' . implode(' AND ', $where)
            . " ORDER BY (m.status = 'new') DESC, m.created_at DESC, m.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
        $counts = [];
        foreach (DB::all('SELECT status, COUNT(*) AS n FROM contact_messages GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        return admin_view('content/messages', ['messages' => $messages, 'counts' => $counts, 'f' => ['statut' => $status, 'motif' => $reason, 'q' => $q],
            'total' => $total, 'page' => $page, 'pages' => $pages, 'statuses' => self::STATUSES, 'reasons' => self::REASONS,
            'notify' => self::notification(), 'pageTitle' => 'Messages']);
    }

    /** État réel de la notification des nouveaux messages (jamais annoncée active si l'envoi n'est pas configuré). */
    public static function notification(): array
    {
        $to = Settings::get('order_notification_email');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'text' => 'Notification par e-mail inactive : aucune adresse de notification (Paramètres du site → Coordonnées et contact).'];
        }
        if (!Mailer::deliversForReal()) {
            return ['ok' => false, 'text' => 'Notification par e-mail inactive : l\'envoi d\'e-mails n\'est pas configuré sur le serveur (voir docs/PARAMETRES.md).'];
        }
        return ['ok' => true, 'text' => 'Chaque nouveau message est signalé par e-mail à ' . $to . '.'];
    }

    private function find(Request $r): array
    {
        $m = DB::one('SELECT * FROM contact_messages WHERE id = ?', [(int) $r->params['id']]);
        if ($m === null) {
            throw new HttpException(404, 'Message introuvable.');
        }
        return $m;
    }

    public function show(Request $r): Response
    {
        $m = $this->find($r);
        if ($m['status'] === 'new') {
            DB::update('contact_messages', ['status' => 'read', 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $m['id']]);
            $m['status'] = 'read';
        }
        $replies = DB::all('SELECT x.*, u.first_name, u.last_name FROM message_replies x LEFT JOIN users u ON u.id = x.user_id WHERE x.message_id = ? ORDER BY x.id', [(int) $m['id']]);
        $customer = $m['user_id'] !== null ? DB::one('SELECT id, first_name, last_name FROM users WHERE id = ?', [(int) $m['user_id']]) : null;
        return admin_view('content/message', ['message' => $m, 'replies' => $replies, 'customer' => $customer, 'statuses' => self::STATUSES, 'reasons' => self::REASONS,
            'canEmail' => Mailer::deliversForReal(), 'pageTitle' => 'Message : ' . $m['subject']]);
    }

    public function status(Request $r): Response
    {
        $m = $this->find($r);
        $status = $r->str('status');
        if (!array_key_exists($status, self::STATUSES)) {
            throw new HttpException(422);
        }
        DB::update('contact_messages', ['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $m['id']]);
        Audit::log('message.status', 'message', (int) $m['id'], ['from' => $m['status'], 'to' => $status]);
        Session::flash('success', 'Message marqué « ' . self::STATUSES[$status] . ' ».');
        return redirect($r->str('retour') === 'liste' ? '/admin/messages' : '/admin/messages/' . $m['id']);
    }

    public function reply(Request $r): Response
    {
        $m = $this->find($r);
        $body = trim(mb_substr($r->str('body'), 0, 5000));
        if (mb_strlen($body) < 2) {
            Session::flash('error', 'La réponse est vide.');
            return redirect('/admin/messages/' . $m['id']);
        }
        if (!Mailer::deliversForReal()) {
            Session::flash('error', 'L\'envoi d\'e-mails n\'est pas configuré : répondez avec votre messagerie (bouton « Répondre avec ma messagerie »).');
            return redirect('/admin/messages/' . $m['id']);
        }
        $subject = 'Re : ' . $m['subject'];
        $text = 'Bonjour ' . $m['name'] . ",\n\n" . $body . "\n\n— " . Settings::get('store_name')
            . "\n\n---\nVotre message du " . date_fr($m['created_at']) . " :\n" . $m['message'];
        if (!Mailer::send((string) $m['email'], $subject, $text)) {
            Session::flash('error', 'L\'e-mail n\'a pas pu être envoyé (voir le journal). La réponse n\'a pas été enregistrée.');
            return redirect('/admin/messages/' . $m['id']);
        }
        $now = gmdate('Y-m-d H:i:s');
        DB::insert('message_replies', ['message_id' => (int) $m['id'], 'user_id' => $this->uid(), 'body' => $body, 'sent_by_email' => 1, 'created_at' => $now]);
        if (in_array($m['status'], ['new', 'read'], true)) {
            DB::update('contact_messages', ['status' => 'in_progress', 'updated_at' => $now], 'id = ?', [(int) $m['id']]);
        }
        Audit::log('message.reply', 'message', (int) $m['id'], ['length' => mb_strlen($body)]);
        Session::flash('success', 'Réponse envoyée par e-mail à ' . $m['email'] . '.');
        return redirect('/admin/messages/' . $m['id']);
    }

    /** Suppression définitive (données personnelles) : réservée aux messages résolus ou archivés. */
    public function delete(Request $r): Response
    {
        $m = $this->find($r);
        if (!in_array($m['status'], ['resolved', 'archived'], true)) {
            Session::flash('error', 'Seul un message résolu ou archivé peut être supprimé.');
            return redirect('/admin/messages/' . $m['id']);
        }
        DB::run('DELETE FROM message_replies WHERE message_id = ?', [(int) $m['id']]);
        DB::run('DELETE FROM contact_messages WHERE id = ?', [(int) $m['id']]);
        Audit::log('message.delete', 'message', (int) $m['id'], ['created_at' => $m['created_at']]);
        Session::flash('success', 'Message supprimé définitivement.');
        return redirect('/admin/messages');
    }
}
