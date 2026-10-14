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
use App\Services\ImageService;
use App\Services\Settings;
use App\Services\Slides;

/** Administration → Carrousel de l'accueil (table home_slides). Permission content.manage. */
final class SlidesController extends Base
{
    public function index(Request $r, array $errors = []): Response
    {
        $slides = Slides::all();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($slides as &$s) {
            $s['live'] = Slides::isLive($s, $now);
        }
        unset($s);
        return admin_view('slides/index', [
            'slides' => $slides, 'errors' => $errors, 'pageTitle' => 'Carrousel de l\'accueil',
            'autoplay' => Settings::bool('carousel_autoplay'), 'interval' => Settings::int('carousel_interval'),
        ], $errors ? 422 : 200);
    }

    public function create(Request $r, array $errors = [], array $old = []): Response
    {
        return admin_view('slides/form', ['slide' => null, 'old' => $old + ['is_active' => 1], 'errors' => $errors, 'pageTitle' => 'Nouvelle diapositive'], $errors ? 422 : 200);
    }

    public function edit(Request $r, array $errors = [], ?array $old = null): Response
    {
        $slide = $this->find((int) $r->params['id']);
        return admin_view('slides/form', ['slide' => $slide, 'old' => $old ?? $slide, 'errors' => $errors, 'pageTitle' => 'Diapositive « ' . $slide['title'] . ' »'], $errors ? 422 : 200);
    }

    private function find(int $id): array
    {
        $s = Slides::find($id);
        if ($s === null) {
            throw new HttpException(404, 'Diapositive introuvable.');
        }
        return $s;
    }

    /** @return array{0:array,1:array} [données, erreurs] — images traitées séparément */
    private function validate(Request $r): array
    {
        $v = Validator::make($r->post, [
            'title' => 'required|max:120', 'title_en' => 'max:120', 'body' => 'max:300', 'body_en' => 'max:300',
            'button_label' => 'max:60', 'button_label_en' => 'max:60', 'link_url' => 'max:255', 'image_alt' => 'max:200', 'image_alt_en' => 'max:200',
        ], ['title' => 'Le titre', 'body' => 'Le texte', 'button_label' => 'Le texte du bouton', 'link_url' => 'Le lien']);
        $errors = $v->errors;
        $data = [];
        foreach (['title', 'title_en', 'body', 'body_en', 'button_label', 'button_label_en', 'link_url', 'image_alt', 'image_alt_en'] as $f) {
            $data[$f] = trim((string) ($v->data[$f] ?? '')) ?: null;
        }
        $data['title'] = (string) $data['title'];
        if (!Slides::validLink((string) $data['link_url'])) {
            $errors['link_url'] = 'Lien invalide : chemin du site (ex. /boutique) ou adresse complète en https://.';
        }
        if (($data['button_label'] === null) !== ($data['link_url'] === null)) {
            $errors['button_label'] = 'Le bouton nécessite à la fois un texte et un lien.';
        }
        $data['starts_at'] = $this->datetime($r->str('starts_at'));
        $data['ends_at'] = $this->datetime($r->str('ends_at'));
        if ($data['starts_at'] !== null && $data['ends_at'] !== null && $data['ends_at'] <= $data['starts_at']) {
            $errors['ends_at'] = 'La fin de publication doit suivre le début.';
        }
        $data['is_active'] = $r->str('is_active') === '1' ? 1 : 0;
        $data['source_note'] = mb_substr(trim($r->str('source_note')), 0, 255) ?: null;
        return [$data, $errors];
    }

    /** Photo du carrousel : uniquement une photo de l'entreprise, déclarée comme telle avec sa provenance. */
    private function sourceErrors(Request $r, array $data, bool $newImage): array
    {
        if (!$newImage) {
            return [];
        }
        $errors = [];
        if ($r->str('source_confirm') !== '1') {
            $errors['source_confirm'] = 'Confirmez que la photo provient des ressources de l\'entreprise (page Facebook officielle ou photo fournie par l\'entreprise).';
        }
        if ($data['source_note'] === null) {
            $errors['source_note'] = 'Indiquez la provenance de la photo.';
        }
        return $errors;
    }

    /** Image principale (≥ 1200 × 500) ou image mobile (≥ 600 × 700), contrôlées et ré-encodées en WebP. */
    private function storeImage(Request $r, string $field, bool $mobile): array
    {
        $file = $r->files[$field] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => null];
        }
        return ImageService::store($file, 'slides', $mobile ? 1200 : 2400, $mobile ? ['minWidth' => 600, 'minHeight' => 700] : ['minWidth' => 1200, 'minHeight' => 500]);
    }

    public function store(Request $r): Response
    {
        [$data, $errors] = $this->validate($r);
        $image = $this->storeImage($r, 'image', false);
        $mobile = $this->storeImage($r, 'image_mobile', true);
        if (!$image['ok']) {
            $errors['image'] = $image['error'];
        } elseif ($image['path'] === null) {
            $errors['image'] = 'Une image est obligatoire (1200 × 500 px minimum).';
        }
        $errors += $this->sourceErrors($r, $data, true);
        if (!$mobile['ok']) {
            $errors['image_mobile'] = $mobile['error'];
        }
        if ($errors) {
            foreach ([$image, $mobile] as $img) {
                if (!empty($img['path'])) {
                    ImageService::delete($img['path']);
                }
            }
            return $this->create($r, $errors, $r->post);
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::insert('home_slides', $data + ['image_path' => $image['path'], 'image_mobile_path' => $mobile['path'],
            'sort_order' => count(Slides::all()), 'created_at' => $now, 'updated_at' => $now]);
        Audit::log('slide.create', 'slide', $id, ['title' => $data['title']]);
        Session::flash('success', 'Diapositive ajoutée.' . ($data['is_active'] ? ' Elle est publiée sur l\'accueil.' : ' Elle est désactivée.'));
        return redirect('/admin/carrousel');
    }

    public function update(Request $r): Response
    {
        $slide = $this->find((int) $r->params['id']);
        [$data, $errors] = $this->validate($r);
        $image = $this->storeImage($r, 'image', false);
        $mobile = $this->storeImage($r, 'image_mobile', true);
        if (!$image['ok']) {
            $errors['image'] = $image['error'];
        }
        if (!$mobile['ok']) {
            $errors['image_mobile'] = $mobile['error'];
        }
        $errors += $this->sourceErrors($r, $data, !empty($image['path']) || !empty($mobile['path']));
        if ($data['source_note'] === null) {
            $data['source_note'] = $slide['source_note'];
        }
        if ($errors) {
            foreach ([$image, $mobile] as $img) {
                if (!empty($img['path'])) {
                    ImageService::delete($img['path']);
                }
            }
            return $this->edit($r, $errors, $r->post + $slide);
        }
        if ($image['path'] !== null) {
            $data['image_path'] = $image['path'];
            ImageService::delete($slide['image_path']);
        }
        if ($mobile['path'] !== null) {
            $data['image_mobile_path'] = $mobile['path'];
            if ($slide['image_mobile_path']) {
                ImageService::delete($slide['image_mobile_path']);
            }
        } elseif ($r->str('remove_mobile') === '1' && $slide['image_mobile_path']) {
            ImageService::delete($slide['image_mobile_path']);
            $data['image_mobile_path'] = null;
        }
        DB::update('home_slides', $data + ['updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $slide['id']]);
        Audit::log('slide.update', 'slide', (int) $slide['id'], ['title' => $data['title'], 'active' => $data['is_active']]);
        Session::flash('success', 'Diapositive enregistrée.');
        return redirect('/admin/carrousel');
    }

    public function toggle(Request $r): Response
    {
        $slide = $this->find((int) $r->params['id']);
        $active = (int) $slide['is_active'] === 1 ? 0 : 1;
        DB::update('home_slides', ['is_active' => $active, 'updated_at' => gmdate('Y-m-d H:i:s')], 'id = ?', [(int) $slide['id']]);
        Audit::log('slide.toggle', 'slide', (int) $slide['id'], ['active' => $active]);
        Session::flash('success', $active ? 'Diapositive activée.' : 'Diapositive désactivée.');
        return redirect('/admin/carrousel');
    }

    /** Déplace une diapositive d'un rang vers le haut ou le bas. */
    public function move(Request $r): Response
    {
        $slide = $this->find((int) $r->params['id']);
        Slides::renumber();
        $ids = array_map('intval', array_column(Slides::all(), 'id'));
        $pos = array_search((int) $slide['id'], $ids, true);
        $target = $r->params['dir'] === 'haut' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$target])) {
            [$ids[$pos], $ids[$target]] = [$ids[$target], $ids[$pos]];
            foreach ($ids as $i => $id) {
                DB::update('home_slides', ['sort_order' => $i], 'id = ?', [$id]);
            }
            Audit::log('slide.move', 'slide', (int) $slide['id'], ['to' => $target]);
        }
        return redirect('/admin/carrousel');
    }

    public function delete(Request $r): Response
    {
        $slide = $this->find((int) $r->params['id']);
        DB::run('DELETE FROM home_slides WHERE id = ?', [(int) $slide['id']]);
        // Les images fournies avec le site (/assets) sont conservées ; seules les images téléversées sont supprimées.
        ImageService::delete($slide['image_path']);
        if ($slide['image_mobile_path']) {
            ImageService::delete($slide['image_mobile_path']);
        }
        Slides::renumber();
        Audit::log('slide.delete', 'slide', (int) $slide['id'], ['title' => $slide['title']]);
        Session::flash('success', 'Diapositive supprimée.');
        return redirect('/admin/carrousel');
    }

    /** Réglages du défilement. */
    public function settings(Request $r): Response
    {
        $interval = $r->str('carousel_interval');
        if (!preg_match('/^\d{1,2}$/', $interval) || (int) $interval < 4 || (int) $interval > 15) {
            return $this->index($r, ['carousel_interval' => 'Durée d\'affichage : entre 4 et 15 secondes.']);
        }
        Settings::set('carousel_autoplay', $r->str('carousel_autoplay') === '1' ? '1' : '0');
        Settings::set('carousel_interval', $interval);
        Audit::log('settings.update', 'settings', 'carrousel', ['keys' => ['carousel_autoplay', 'carousel_interval']]);
        Session::flash('success', 'Réglages du carrousel enregistrés.');
        return redirect('/admin/carrousel');
    }

    /** Aperçu du carrousel tel qu'il apparaîtra (diapositives publiées, ou toutes si ?toutes=1), sans rien publier. */
    public function preview(Request $r): Response
    {
        $all = $r->str('toutes') === '1';
        $slides = array_values(array_map([Slides::class, 'localize'], array_filter(Slides::all(), static fn ($s) => $all || Slides::isLive($s))));
        return view('admin/slides/preview', ['slides' => $slides, 'all' => $all, 'pageTitle' => 'Aperçu du carrousel', 'noindex' => true]);
    }
}
