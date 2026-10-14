<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\ImageService;
use App\Services\Media;

/**
 * Photothèque de l'entreprise : uniquement des photographies issues de ses ressources (page Facebook officielle,
 * photos fournies par l'entreprise), déclarées comme telles avec leur provenance.
 */
final class MediaController extends Base
{
    public function index(Request $r): Response
    {
        return admin_view('media/index', ['media' => Media::all(), 'facebook' => setting('social_facebook'), 'pageTitle' => 'Photos de l\'entreprise']);
    }

    private function find(Request $r): array
    {
        $m = Media::find((int) $r->params['id']);
        if ($m === null) {
            throw new HttpException(404, 'Photo introuvable.');
        }
        return $m;
    }

    /** @return array{0:array, 1:?string} */
    private function fields(Request $r): array
    {
        $data = [
            'alt' => mb_substr(trim($r->str('alt')), 0, 200), 'alt_en' => mb_substr(trim($r->str('alt_en')), 0, 200) ?: null,
            'source_note' => mb_substr(trim($r->str('source_note')), 0, 255),
            'in_home_gallery' => $r->str('in_home_gallery') === '1' ? 1 : 0, 'in_about_gallery' => $r->str('in_about_gallery') === '1' ? 1 : 0,
            'is_story' => $r->str('is_story') === '1' ? 1 : 0,
        ];
        if ($data['alt'] === '') {
            return [$data, 'Le texte alternatif (description de la photo) est obligatoire.'];
        }
        if ($data['source_note'] === '') {
            return [$data, 'Indiquez la provenance de la photo (ex. « Page Facebook, publication du 12/03/2025 »).'];
        }
        return [$data, null];
    }

    public function store(Request $r): Response
    {
        [$data, $error] = $this->fields($r);
        if ($error === null && $r->str('source_confirm') !== '1') {
            $error = 'Confirmez que la photo provient des ressources de l\'entreprise.';
        }
        if ($error !== null) {
            Session::flash('error', $error);
            return redirect('/admin/photos');
        }
        $result = ImageService::store($r->files['image'] ?? [], 'media', 2000, ['minWidth' => 400, 'minHeight' => 300]);
        if (!$result['ok']) {
            Session::flash('error', 'Photo refusée : ' . $result['error']);
            return redirect('/admin/photos');
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($data['is_story']) {
            DB::run('UPDATE media SET is_story = 0');
        }
        $id = DB::insert('media', $data + ['path' => $result['path'], 'width' => $result['width'] ?? null, 'height' => $result['height'] ?? null,
            'sort_order' => (int) DB::value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM media'), 'created_at' => $now, 'updated_at' => $now]);
        Audit::log('media.create', 'media', $id, ['source' => $data['source_note']]);
        Session::flash('success', 'Photo ajoutée à la photothèque.');
        return redirect('/admin/photos');
    }

    public function update(Request $r): Response
    {
        $m = $this->find($r);
        [$data, $error] = $this->fields($r);
        if ($error !== null) {
            Session::flash('error', $error);
            return redirect('/admin/photos#photo-' . $m['id']);
        }
        if ($data['is_story']) {
            DB::run('UPDATE media SET is_story = 0 WHERE id <> ?', [(int) $m['id']]);
        }
        DB::update('media', $data + ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $m['id']]);
        Audit::log('media.update', 'media', (int) $m['id'], ['home' => $data['in_home_gallery'], 'about' => $data['in_about_gallery'], 'story' => $data['is_story']]);
        Session::flash('success', 'Photo enregistrée.');
        return redirect('/admin/photos#photo-' . $m['id']);
    }

    public function move(Request $r): Response
    {
        $m = $this->find($r);
        Media::renumber();
        $ids = array_map('intval', array_column(DB::all('SELECT id FROM media ORDER BY sort_order, id'), 'id'));
        $pos = array_search((int) $m['id'], $ids, true);
        $to = $r->params['dir'] === 'haut' ? $pos - 1 : $pos + 1;
        if ($pos !== false && $to >= 0 && $to < count($ids)) {
            [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
            foreach ($ids as $i => $id) {
                DB::update('media', ['sort_order' => $i], 'id = ?', [$id]);
            }
        }
        return redirect('/admin/photos#photo-' . $m['id']);
    }

    public function delete(Request $r): Response
    {
        $m = $this->find($r);
        DB::run('DELETE FROM media WHERE id = ?', [(int) $m['id']]);
        ImageService::delete((string) $m['path']);
        Media::renumber();
        Audit::log('media.delete', 'media', (int) $m['id'], ['path' => $m['path']]);
        Session::flash('success', 'Photo retirée de la photothèque.');
        return redirect('/admin/photos');
    }
}
