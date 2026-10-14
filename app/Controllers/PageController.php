<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\DB;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Notifier;

final class PageController
{
    private function page(string $slug): array
    {
        $page = DB::one('SELECT * FROM pages WHERE slug = ?', [$slug]);
        if ($page === null) {
            throw new HttpException(404);
        }
        return $page;
    }

    public function about(Request $r): Response
    {
        $page = $this->page('a-propos');
        return view('pages/page', ['page' => $page, 'pageTitle' => $page['title'], 'metaDescription' => $page['meta_description'] ?: excerpt($page['body'], 158), 'canonical' => '/a-propos']);
    }

    public function legal(Request $r): Response
    {
        $slug = ltrim($r->path, '/');
        $page = $this->page($slug);
        return view('pages/page', ['page' => $page, 'pageTitle' => $page['title'], 'metaDescription' => $page['meta_description'] ?: excerpt($page['body'], 158), 'canonical' => '/' . $slug]);
    }

    public function faq(Request $r): Response
    {
        $faqs = DB::all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id');
        $groups = [];
        foreach ($faqs as $f) {
            $groups[$f['category']][] = $f;
        }
        $ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn ($f) => [
            '@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => excerpt($f['answer'], 1000)],
        ], $faqs)];
        return view('pages/faq', ['groups' => $groups, 'pageTitle' => 'Questions fréquentes', 'canonical' => '/faq', 'jsonLd' => $faqs ? [$ld] : [],
            'metaDescription' => 'Commande, paiement à la livraison, zones de livraison, conservation du miel : les réponses à vos questions.']);
    }

    public function contact(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('pages/contact', ['errors' => $errors, 'old' => $old, 'pageTitle' => 'Contact', 'canonical' => '/contact',
            'metaDescription' => 'Contactez Miel Pur de Côte d\'Ivoire : questions sur les produits, les commandes ou la livraison.'], $status);
    }

    public function contactSubmit(Request $r): Response
    {
        $v = Validator::make($r->post, [
            'name' => 'required|max:150', 'email' => 'required|email', 'phone' => 'phone',
            'subject' => 'required|max:160', 'message' => 'required|min:10|max:5000', 'consent' => 'accepted',
        ], ['name' => 'Votre nom', 'email' => 'L\'e-mail', 'phone' => 'Le téléphone', 'subject' => 'L\'objet', 'message' => 'Le message']);
        $errors = $v->errors;
        if ($spam = spam_check($r)) {
            $errors['_'] = $spam;
        }
        if (!$errors && !RateLimiter::hit('contact:' . $r->ip(), 5, 3600)) {
            $errors['_'] = 'Trop de messages envoyés. Réessayez dans une heure.';
        }
        if ($errors) {
            return $this->contact($r, $errors, $r->post, 422);
        }
        DB::insert('contact_messages', [
            'name' => $v->data['name'], 'email' => mb_strtolower($v->data['email']), 'phone' => $v->data['phone'] ?: null,
            'subject' => $v->data['subject'], 'message' => $v->data['message'], 'status' => 'new', 'ip' => $r->ip(), 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        Notifier::staffNotice('Nouveau message : ' . $v->data['subject'], 'Message de ' . $v->data['name'] . ' (' . $v->data['email'] . ")\n\n" . $v->data['message']);
        Session::flash('success', 'Merci, votre message a bien été envoyé. Nous vous répondrons dès que possible.');
        return redirect('/contact');
    }

    public const ACTIVITIES = ['hotel' => 'Hôtel', 'restaurant' => 'Restaurant / traiteur', 'patisserie' => 'Pâtisserie / boulangerie', 'epicerie' => 'Épicerie / supermarché',
        'cosmetique' => 'Cosmétique / savonnerie', 'pharmacie' => 'Pharmacie / parapharmacie', 'entreprise' => 'Cadeaux d\'entreprise', 'autre' => 'Autre'];

    public function pro(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('pages/pro', ['errors' => $errors, 'old' => $old, 'activities' => self::ACTIVITIES, 'pageTitle' => 'Commandes professionnelles', 'canonical' => '/commandes-professionnelles',
            'metaDescription' => 'Hôtels, restaurants, pâtisseries, épiceries, cosmétique : demandez un devis pour du miel et de la cire d\'abeille en volume.'], $status);
    }

    public function proSubmit(Request $r): Response
    {
        $v = Validator::make($r->post, [
            'company' => 'required|max:160', 'contact_name' => 'required|max:150', 'email' => 'required|email', 'phone' => 'required|phone',
            'activity' => 'required|in:' . implode(',', array_keys(self::ACTIVITIES)), 'products_interest' => 'max:255',
            'estimated_volume' => 'max:120', 'frequency' => 'in:ponctuelle,mensuelle,hebdomadaire,autre', 'city' => 'max:100',
            'message' => 'max:5000', 'consent' => 'accepted',
        ], ['company' => 'L\'établissement', 'contact_name' => 'Le nom du contact', 'email' => 'L\'e-mail', 'phone' => 'Le téléphone', 'activity' => 'Le type d\'activité',
            'products_interest' => 'Les produits', 'estimated_volume' => 'Le volume', 'frequency' => 'La fréquence', 'city' => 'La ville', 'message' => 'Le message']);
        $errors = $v->errors;
        if ($spam = spam_check($r)) {
            $errors['_'] = $spam;
        }
        if (!$errors && !RateLimiter::hit('quote:' . $r->ip(), 5, 3600)) {
            $errors['_'] = 'Trop de demandes envoyées. Réessayez dans une heure.';
        }
        if ($errors) {
            return $this->pro($r, $errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        DB::insert('quote_requests', [
            'company' => $v->data['company'], 'contact_name' => $v->data['contact_name'], 'email' => mb_strtolower($v->data['email']), 'phone' => $v->data['phone'],
            'activity' => $v->data['activity'], 'products_interest' => $v->data['products_interest'] ?: null, 'estimated_volume' => $v->data['estimated_volume'] ?: null,
            'frequency' => $v->data['frequency'] ?: null, 'city' => $v->data['city'] ?: null, 'message' => $v->data['message'] ?: null,
            'status' => 'new', 'admin_note' => null, 'ip' => $r->ip(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        Notifier::staffNotice('Nouvelle demande de devis : ' . $v->data['company'], 'Contact : ' . $v->data['contact_name'] . ' — ' . $v->data['phone'] . "\nVolume : " . $v->data['estimated_volume']);
        Session::flash('success', 'Merci, votre demande de devis a bien été transmise. Nous vous recontactons rapidement.');
        return redirect('/commandes-professionnelles');
    }

    public function tracking(Request $r, array $errors = [], array $old = [], ?array $order = null, int $status = 200): Response
    {
        $history = [];
        if ($order !== null) {
            $history = array_values(array_filter(
                DB::all('SELECT to_status, created_at, note FROM order_status_history WHERE order_id = ? ORDER BY id', [(int) $order['id']]),
                static fn ($h) => !str_starts_with((string) $h['note'], 'Note interne')
            ));
        }
        return view('pages/tracking', ['errors' => $errors, 'old' => $old, 'order' => $order, 'history' => $history, 'pageTitle' => 'Suivi de commande', 'noindex' => true], $status);
    }

    public function trackingLookup(Request $r): Response
    {
        $v = Validator::make($r->post, ['number' => 'required|max:40', 'email' => 'required|email'], ['number' => 'Le numéro de commande', 'email' => 'L\'e-mail']);
        if ($v->fails()) {
            return $this->tracking($r, $v->errors, $r->post, null, 422);
        }
        if (!RateLimiter::hit('tracking:' . $r->ip(), 10, 900)) {
            return $this->tracking($r, ['_' => 'Trop de recherches. Réessayez dans 15 minutes.'], $r->post, null, 429);
        }
        $order = DB::one('SELECT * FROM orders WHERE number = ? AND customer_email = ?', [strtoupper($v->data['number']), mb_strtolower($v->data['email'])]);
        if ($order === null) {
            return $this->tracking($r, ['_' => 'Aucune commande ne correspond à ce numéro et à cet e-mail.'], $r->post, null, 404);
        }
        return $this->tracking($r, [], $r->post, $order);
    }

    public function newsletter(Request $r): Response
    {
        $email = mb_strtolower($r->str('email'));
        $back = safe_return($r->str('retour'), '/');
        if (spam_check($r, false) !== null || !filter_var($email, FILTER_VALIDATE_EMAIL) || $r->str('consent') !== '1') {
            Session::flash('error', 'Inscription impossible : vérifiez votre e-mail et cochez la case de consentement.');
            return redirect($back);
        }
        if (!RateLimiter::hit('newsletter:' . $r->ip(), 10, 3600)) {
            Session::flash('error', 'Trop de tentatives. Réessayez plus tard.');
            return redirect($back);
        }
        $now = gmdate('Y-m-d H:i:s');
        $existing = DB::one('SELECT * FROM newsletter_subscribers WHERE email = ?', [$email]);
        if ($existing === null) {
            DB::insert('newsletter_subscribers', ['email' => $email, 'status' => 'subscribed', 'unsubscribe_token' => random_token(16), 'consent_at' => $now, 'ip' => $r->ip(), 'created_at' => $now]);
        } elseif ($existing['status'] !== 'subscribed') {
            DB::update('newsletter_subscribers', ['status' => 'subscribed', 'consent_at' => $now], 'id = ?', [(int) $existing['id']]);
        }
        Session::flash('success', 'Merci ! Votre inscription à la lettre d\'information est enregistrée.');
        return redirect($back);
    }

    public function unsubscribe(Request $r): Response
    {
        $token = $r->params['token'];
        if (strlen($token) === 32) {
            DB::run("UPDATE newsletter_subscribers SET status = 'unsubscribed' WHERE unsubscribe_token = ?", [$token]);
        }
        Session::flash('success', 'Vous êtes désinscrit(e) de la lettre d\'information.');
        return redirect('/');
    }
}
