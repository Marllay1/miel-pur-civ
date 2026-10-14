<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\HomeHighlights;
use App\Services\Slides;

/** Blocs de réassurance de la page d'accueil : titre, texte, icône, lien, ordre, visibilité (FR/EN). */
final class HighlightsController extends Base
{
    public function index(Request $r, array $errors = [], array $old = [], ?int $editId = null, int $status = 200): Response
    {
        return admin_view('content/highlights', ['items' => HomeHighlights::all(), 'icons' => HomeHighlights::ICONS, 'errors' => $errors, 'old' => $old,
            'editId' => $editId, 'shown' => setting('home_show_highlights') === '1', 'pageTitle' => 'Blocs de réassurance'], $status);
    }

    /** @return array{0:array, 1:array} */
    private function validate(Request $r): array
    {
        $errors = [];
        $data = [
            'icon' => $r->str('icon'), 'title' => mb_substr(trim($r->str('title')), 0, 80), 'title_en' => mb_substr(trim($r->str('title_en')), 0, 80) ?: null,
            'body' => mb_substr(trim($r->str('body')), 0, 200) ?: null, 'body_en' => mb_substr(trim($r->str('body_en')), 0, 200) ?: null,
            'link_url' => trim($r->str('link_url')) ?: null, 'is_active' => $r->str('is_active') === '1' ? 1 : 0,
        ];
        if (!array_key_exists($data['icon'], HomeHighlights::ICONS)) {
            $errors['icon'] = 'Icône inconnue.';
        }
        if ($data['title'] === '') {
            $errors['title'] = 'Le titre est obligatoire.';
        }
        if ($data['link_url'] !== null && (mb_strlen($data['link_url']) > 255 || !Slides::validLink($data['link_url']))) {
            $errors['link_url'] = 'Lien invalide : page du site (ex. /contact) ou adresse complète en https://.';
        }
        return [$data, $errors];
    }

    public function store(Request $r): Response
    {
        [$data, $errors] = $this->validate($r);
        if ($errors) {
            return $this->index($r, $errors, $r->post, null, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::insert('home_highlights', $data + ['sort_order' => count(HomeHighlights::all()), 'created_at' => $now, 'updated_at' => $now]);
        Audit::log('highlight.create', 'highlight', $id, ['title' => $data['title']]);
        Session::flash('success', 'Bloc ajouté.');
        return redirect('/admin/reassurance');
    }

    private function find(Request $r): array
    {
        $h = DB::one('SELECT * FROM home_highlights WHERE id = ?', [(int) $r->params['id']]);
        if ($h === null) {
            throw new HttpException(404, 'Bloc introuvable.');
        }
        return $h;
    }

    public function update(Request $r): Response
    {
        $h = $this->find($r);
        [$data, $errors] = $this->validate($r);
        if ($errors) {
            return $this->index($r, $errors, $r->post, (int) $h['id'], 422);
        }
        DB::update('home_highlights', $data + ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $h['id']]);
        Audit::log('highlight.update', 'highlight', (int) $h['id'], ['title' => $data['title'], 'active' => $data['is_active']]);
        Session::flash('success', 'Bloc enregistré.');
        return redirect('/admin/reassurance#bloc-' . $h['id']);
    }

    public function move(Request $r): Response
    {
        $h = $this->find($r);
        HomeHighlights::renumber();
        $ids = array_map(static fn ($x) => (int) $x['id'], HomeHighlights::all());
        $pos = array_search((int) $h['id'], $ids, true);
        $to = $r->params['dir'] === 'haut' ? $pos - 1 : $pos + 1;
        if ($pos !== false && $to >= 0 && $to < count($ids)) {
            [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
            foreach ($ids as $i => $id) {
                DB::update('home_highlights', ['sort_order' => $i], 'id = ?', [$id]);
            }
        }
        return redirect('/admin/reassurance');
    }

    public function delete(Request $r): Response
    {
        $h = $this->find($r);
        DB::run('DELETE FROM home_highlights WHERE id = ?', [(int) $h['id']]);
        HomeHighlights::renumber();
        Audit::log('highlight.delete', 'highlight', (int) $h['id'], ['title' => $h['title']]);
        Session::flash('success', 'Bloc supprimé.');
        return redirect('/admin/reassurance');
    }
}
