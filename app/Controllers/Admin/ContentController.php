<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\I18n;
use App\Services\Settings;

final class ContentController extends Base
{
    public const HOME_KEYS = [
        'home_hero_title', 'home_hero_title_en', 'home_hero_text', 'home_hero_text_en', 'home_story_title', 'home_story_title_en',
        'home_story_text', 'home_story_text_en', 'home_pro_text', 'home_pro_text_en', 'store_tagline', 'store_tagline_en',
    ];

    // ---- Messages de contact
    // ---- Newsletter
    public function newsletter(Request $r): Response
    {
        return admin_view('content/newsletter', [
            'subscribers' => DB::all('SELECT * FROM newsletter_subscribers ORDER BY created_at DESC LIMIT 500'),
            'count' => (int) DB::value("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'"),
            'pageTitle' => 'Newsletter',
        ]);
    }

    public function newsletterExport(Request $r): Response
    {
        $rows = DB::all("SELECT email, consent_at FROM newsletter_subscribers WHERE status = 'subscribed' ORDER BY email");
        Audit::log('newsletter.export', 'newsletter', null, ['count' => count($rows)]);
        return $this->csv('newsletter-' . gmdate('Ymd') . '.csv', ['E-mail', 'Consentement (UTC)'], array_map(static fn ($r) => [$r['email'], $r['consent_at']], $rows));
    }

    // ---- Accueil
    public function home(Request $r, array $errors = []): Response
    {
        $defs = Settings::definitions();
        $fields = [];
        foreach (self::HOME_KEYS as $k) {
            $fields[$k] = ['label' => $defs[$k]['label'], 'value' => Settings::get($k), 'long' => str_contains($k, 'text')];
        }
        return admin_view('content/home', ['fields' => $fields, 'errors' => $errors, 'pageTitle' => 'Contenu de l\'accueil'], $errors ? 422 : 200);
    }

    public function homeSave(Request $r): Response
    {
        $errors = [];
        foreach (self::HOME_KEYS as $k) {
            try {
                Settings::set($k, (string) ($r->post[$k] ?? ''));
            } catch (\InvalidArgumentException $e) {
                $errors[$k] = $e->getMessage();
            }
        }
        if ($errors) {
            return $this->home($r, $errors);
        }
        Audit::log('content.home', 'settings', null, ['keys' => self::HOME_KEYS]);
        Session::flash('success', 'Contenu de l\'accueil enregistré.');
        return redirect('/admin/contenu');
    }

    // ---- Pages
    public function pages(Request $r): Response
    {
        return admin_view('content/pages', ['pages' => DB::all('SELECT * FROM pages ORDER BY is_legal, title'), 'pageTitle' => 'Pages et documents légaux']);
    }

    public function pageEdit(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        $page = DB::one('SELECT * FROM pages WHERE id = ?', [(int) $r->params['id']]);
        if ($page === null) {
            throw new HttpException(404);
        }
        return admin_view('content/page-form', ['page' => $page, 'old' => $old ?? $page, 'errors' => $errors, 'pageTitle' => $page['title'],
            'translation' => is_array($old['en'] ?? null) ? $old['en'] : I18n::get('page', (int) $page['id'])], $status);
    }

    public function pageSave(Request $r): Response
    {
        $page = DB::one('SELECT * FROM pages WHERE id = ?', [(int) $r->params['id']]);
        if ($page === null) {
            throw new HttpException(404);
        }
        $v = Validator::make($r->post, ['title' => 'required|max:160', 'body' => 'required|max:60000', 'meta_description' => 'max:300'],
            ['title' => 'Le titre', 'body' => 'Le contenu', 'meta_description' => 'La description SEO']);
        if ($v->fails()) {
            return $this->pageEdit($r, $v->errors, $r->post + $page, 422);
        }
        $validated = (int) $page['is_legal'] === 1 && $r->str('is_validated') === '1' ? 1 : 0;
        DB::update('pages', [
            'title' => $v->data['title'], 'body' => $v->data['body'], 'meta_description' => $v->data['meta_description'] ?: null,
            'is_validated' => $validated, 'updated_by' => $this->uid(), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $page['id']]);
        Audit::log('page.update', 'page', $page['slug'], ['validated' => $validated]);
        $this->saveTranslation($r, 'page', (int) $page['id']);
        Session::flash('success', 'Page enregistrée.');
        return redirect('/admin/pages/' . $page['id']);
    }

    // ---- FAQ
    public function faqs(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        $edit = $r->int('id') ? DB::one('SELECT * FROM faqs WHERE id = ?', [(int) $r->int('id')]) : null;
        return admin_view('content/faqs', [
            'faqs' => DB::all('SELECT * FROM faqs ORDER BY category, sort_order, id'),
            'edit' => $edit, 'old' => $old ?? ($edit ?? ['is_active' => 1, 'category' => 'Général']), 'errors' => $errors, 'pageTitle' => 'FAQ',
            'translation' => is_array($old['en'] ?? null) ? $old['en'] : ($edit ? I18n::get('faq', (int) $edit['id']) : []),
        ], $status);
    }

    public function faqSave(Request $r): Response
    {
        $id = (int) $r->int('id', 0);
        $v = Validator::make($r->post, ['question' => 'required|max:255', 'answer' => 'required|max:10000', 'category' => 'required|max:80', 'sort_order' => 'int|min:0|max:1000'],
            ['question' => 'La question', 'answer' => 'La réponse', 'category' => 'La rubrique', 'sort_order' => 'L\'ordre']);
        if ($v->fails()) {
            return $this->faqs($r, $v->errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = ['question' => $v->data['question'], 'answer' => $v->data['answer'], 'category' => $v->data['category'], 'sort_order' => $v->data['sort_order'] ?? 0,
            'is_active' => $r->str('is_active') === '1' ? 1 : 0, 'updated_at' => $now];
        if ($id > 0 && DB::value('SELECT id FROM faqs WHERE id = ?', [$id]) !== null) {
            DB::update('faqs', $data, 'id = ?', [$id]);
        } else {
            $id = DB::insert('faqs', $data + ['created_at' => $now]);
        }
        Audit::log('faq.save', 'faq', $id);
        $this->saveTranslation($r, 'faq', $id);
        Session::flash('success', 'Question enregistrée.');
        return redirect('/admin/faq');
    }

    public function faqDelete(Request $r): Response
    {
        DB::run('DELETE FROM faqs WHERE id = ?', [(int) $r->params['id']]);
        DB::run('DELETE FROM translations WHERE entity_type = ? AND entity_id = ?', ['faq', (int) $r->params['id']]);
        Audit::log('faq.delete', 'faq', (int) $r->params['id']);
        Session::flash('success', 'Question supprimée.');
        return redirect('/admin/faq');
    }

    // ---- Avis
    public function reviews(Request $r): Response
    {
        $status = in_array($r->str('statut'), ['pending', 'approved', 'rejected'], true) ? $r->str('statut') : 'pending';
        return admin_view('content/reviews', [
            'reviews' => DB::all('SELECT rv.*, p.name AS product_name, p.slug AS product_slug FROM reviews rv JOIN products p ON p.id = rv.product_id WHERE rv.status = ? ORDER BY rv.created_at DESC LIMIT 200', [$status]),
            'status' => $status, 'pageTitle' => 'Avis clients',
        ]);
    }

    public function reviewModerate(Request $r): Response
    {
        $status = $r->str('status');
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            throw new HttpException(422);
        }
        $n = DB::update('reviews', ['status' => $status, 'moderated_by' => $this->uid(), 'moderated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $r->params['id']]);
        if ($n === 0) {
            throw new HttpException(404);
        }
        Audit::log('review.moderate', 'review', (int) $r->params['id'], ['status' => $status]);
        Session::flash('success', $status === 'approved' ? 'Avis publié.' : ($status === 'rejected' ? 'Avis refusé.' : 'Avis remis en attente.'));
        return redirect('/admin/avis');
    }
}
