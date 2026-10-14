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
        return \App\Services\I18n::localizeOne('page', $page);
    }

    public function about(Request $r): Response
    {
        $page = $this->page('a-propos');
        return view('pages/page', ['page' => $page, 'pageTitle' => $page['title'], 'metaDescription' => $page['meta_description'] ?: excerpt($page['body'], 158), 'canonical' => '/a-propos']);
    }

    public function credits(Request $r): Response
    {
        return view('pages/credits', ['pageTitle' => t('credits.title'), 'metaDescription' => t('credits.intro'), 'canonical' => '/credits-photos']);
    }

    public function legal(Request $r): Response
    {
        $slug = ltrim($r->path, '/');
        $page = $this->page($slug);
        return view('pages/page', ['page' => $page, 'pageTitle' => $page['title'], 'metaDescription' => $page['meta_description'] ?: excerpt($page['body'], 158), 'canonical' => '/' . $slug]);
    }

    public function faq(Request $r): Response
    {
        $faqs = \App\Services\I18n::localize('faq', DB::all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id'));
        $groups = [];
        foreach ($faqs as $f) {
            $groups[$f['category']][] = $f;
        }
        $ld = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn ($f) => [
            '@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => excerpt($f['answer'], 1000)],
        ], $faqs)];
        return view('pages/faq', ['groups' => $groups, 'pageTitle' => t('faq.title'), 'canonical' => '/faq', 'jsonLd' => $faqs ? [$ld] : [],
            'metaDescription' => t('faq.meta')]);
    }

    public function contact(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        if ($old === []) {
            $user = \App\Core\Auth::user();
            $old = ['reason' => in_array($r->str('motif'), ['product', 'order', 'delivery', 'payment', 'other'], true) ? $r->str('motif') : 'other',
                'subject' => mb_substr($r->str('objet'), 0, 160)];
            if ($user !== null) {
                $old += ['name' => trim($user['first_name'] . ' ' . $user['last_name']), 'email' => $user['email'], 'phone' => (string) ($user['phone'] ?? '')];
            }
        }
        return view('pages/contact', ['errors' => $errors, 'old' => $old, 'pageTitle' => t('nav.contact'), 'canonical' => '/contact',
            'metaDescription' => t('contact.meta')], $status);
    }

    public function contactSubmit(Request $r): Response
    {
        $v = Validator::make($r->post, [
            'name' => 'required|max:150', 'email' => 'required|email', 'phone' => 'phone', 'reason' => 'required|in:' . implode(',', array_keys(\App\Controllers\Admin\MessageController::REASONS)),
            'subject' => 'required|max:160', 'message' => 'required|min:10|max:5000', 'consent' => 'accepted',
        ], ['name' => t('field.your_name'), 'email' => t('field.email_label'), 'phone' => t('field.phone'), 'reason' => t('field.reason'), 'subject' => t('field.subject'), 'message' => t('field.message')]);
        $errors = $v->errors;
        if ($spam = spam_check($r)) {
            $errors['_'] = $spam;
        }
        if (!$errors && !RateLimiter::hit('contact:' . $r->ip(), 5, 3600)) {
            $errors['_'] = t('contact.throttled');
        }
        if ($errors) {
            return $this->contact($r, $errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::insert('contact_messages', [
            'name' => $v->data['name'], 'email' => mb_strtolower($v->data['email']), 'phone' => $v->data['phone'] ?: null, 'reason' => $v->data['reason'],
            'subject' => $v->data['subject'], 'message' => $v->data['message'], 'status' => 'new', 'ip' => $r->ip(), 'user_id' => \App\Core\Auth::id(),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        Notifier::staffNotice('Nouveau message : ' . $v->data['subject'], 'Message de ' . $v->data['name'] . ' (' . $v->data['email'] . ")\nMotif : "
            . (\App\Controllers\Admin\MessageController::REASONS[$v->data['reason']] ?? '') . "\n\n" . $v->data['message'] . "\n\n" . base_url() . '/admin/messages/' . $id);
        Session::flash('success', t('contact.thanks'));
        return redirect('/contact');
    }

    public const ACTIVITIES = ['hotel' => 'Hôtel', 'restaurant' => 'Restaurant / traiteur', 'patisserie' => 'Pâtisserie / boulangerie', 'epicerie' => 'Épicerie / supermarché',
        'cosmetique' => 'Cosmétique / savonnerie', 'pharmacie' => 'Pharmacie / parapharmacie', 'entreprise' => 'Cadeaux d\'entreprise', 'autre' => 'Autre'];

    public function pro(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        return view('pages/pro', ['errors' => $errors, 'old' => $old, 'activities' => self::ACTIVITIES, 'pageTitle' => t('nav.pro'), 'canonical' => '/commandes-professionnelles',
            'metaDescription' => t('pro.meta')], $status);
    }

    public function proSubmit(Request $r): Response
    {
        $v = Validator::make($r->post, [
            'company' => 'required|max:160', 'contact_name' => 'required|max:150', 'email' => 'required|email', 'phone' => 'required|phone',
            'activity' => 'required|in:' . implode(',', array_keys(self::ACTIVITIES)), 'products_interest' => 'max:255',
            'estimated_volume' => 'max:120', 'frequency' => 'in:ponctuelle,mensuelle,hebdomadaire,autre', 'city' => 'max:100',
            'message' => 'max:5000', 'consent' => 'accepted',
        ], ['company' => t('field.company'), 'contact_name' => t('field.contact_name'), 'email' => t('field.email_label'), 'phone' => t('field.phone'), 'activity' => t('field.activity'),
            'products_interest' => t('field.products_interest'), 'estimated_volume' => t('field.volume'), 'frequency' => t('field.frequency'), 'city' => t('field.city'), 'message' => t('field.message')]);
        $errors = $v->errors;
        if ($spam = spam_check($r)) {
            $errors['_'] = $spam;
        }
        if (!$errors && !RateLimiter::hit('quote:' . $r->ip(), 5, 3600)) {
            $errors['_'] = t('quote.throttled');
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
        Session::flash('success', t('quote.thanks'));
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
        return view('pages/tracking', ['errors' => $errors, 'old' => $old, 'order' => $order, 'history' => $history, 'pageTitle' => t('tracking.title'), 'noindex' => true], $status);
    }

    public function trackingLookup(Request $r): Response
    {
        $v = Validator::make($r->post, ['number' => 'required|max:40', 'email' => 'required|email'], ['number' => t('field.order_number'), 'email' => t('field.email_label')]);
        if ($v->fails()) {
            return $this->tracking($r, $v->errors, $r->post, null, 422);
        }
        if (!RateLimiter::hit('tracking:' . $r->ip(), 10, 900)) {
            return $this->tracking($r, ['_' => t('tracking.throttled')], $r->post, null, 429);
        }
        $order = DB::one('SELECT * FROM orders WHERE number = ? AND customer_email = ?', [strtoupper($v->data['number']), mb_strtolower($v->data['email'])]);
        if ($order === null) {
            return $this->tracking($r, ['_' => t('tracking.not_found')], $r->post, null, 404);
        }
        return $this->tracking($r, [], $r->post, $order);
    }

    public function newsletter(Request $r): Response
    {
        $email = mb_strtolower($r->str('email'));
        $back = safe_return($r->str('retour'), '/');
        if (spam_check($r, false) !== null || !filter_var($email, FILTER_VALIDATE_EMAIL) || $r->str('consent') !== '1') {
            Session::flash('error', t('newsletter.invalid'));
            return redirect($back);
        }
        if (!RateLimiter::hit('newsletter:' . $r->ip(), 10, 3600)) {
            Session::flash('error', t('newsletter.throttled'));
            return redirect($back);
        }
        $now = gmdate('Y-m-d H:i:s');
        $existing = DB::one('SELECT * FROM newsletter_subscribers WHERE email = ?', [$email]);
        if ($existing === null) {
            DB::insert('newsletter_subscribers', ['email' => $email, 'status' => 'subscribed', 'unsubscribe_token' => random_token(16), 'consent_at' => $now, 'ip' => $r->ip(), 'created_at' => $now]);
        } elseif ($existing['status'] !== 'subscribed') {
            DB::update('newsletter_subscribers', ['status' => 'subscribed', 'consent_at' => $now], 'id = ?', [(int) $existing['id']]);
        }
        Session::flash('success', t('newsletter.thanks'));
        return redirect($back);
    }

    /** Page « Plus » (navigation secondaire, langue, apparence), pensée pour la barre d'onglets mobile. */
    public function menu(Request $r): Response
    {
        return view('pages/menu', ['pageTitle' => t('menu.title'), 'canonical' => '/menu', 'noindex' => true]);
    }

    /** Préférence d'apparence (auto, clair, sombre) mémorisée dans un cookie ; fonctionne sans JavaScript. */
    public function theme(Request $r): Response
    {
        $theme = $r->str('theme');
        if (in_array($theme, ['light', 'dark'], true)) {
            \App\Core\Cookie::queue('mpci_theme', $theme, 365 * 86400, false);
        } else {
            \App\Core\Cookie::forget('mpci_theme');
        }
        return redirect(safe_return($r->str('retour'), '/'));
    }

    /** Sélecteur de langue : mémorise le choix (cookie, 1 an) et affiche la même page dans la langue choisie. */
    public function language(Request $r): Response
    {
        $code = $r->params['code'] === 'en' && \App\Services\Settings::bool('lang_en_enabled') ? 'en' : 'fr';
        \App\Core\Cookie::queue('mpci_lang', $code, 365 * 86400, false);
        $retour = safe_return($r->str('retour'), '/');
        if ($retour === '/en' || str_starts_with($retour, '/en/') || str_starts_with($retour, '/en?')) {
            $retour = substr($retour, 3) ?: '/';
        }
        if (\App\Core\Lang::isUnlocalized($retour)) {
            $retour = '/';
        }
        return Response::redirect($code === 'en' ? ($retour === '/' ? '/en' : '/en' . $retour) : $retour);
    }

    public function unsubscribe(Request $r): Response
    {
        $token = $r->params['token'];
        if (strlen($token) === 32) {
            DB::run("UPDATE newsletter_subscribers SET status = 'unsubscribed' WHERE unsubscribe_token = ?", [$token]);
        }
        Session::flash('success', t('newsletter.unsubscribed'));
        return redirect('/');
    }
}
