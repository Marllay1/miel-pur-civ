<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BrandIcons;
use App\Services\ImageService;
use App\Services\Settings;
use App\Services\ThemeService;

/**
 * Paramètres du site : configuration centralisée par sections (app/config/site-sections.php), enregistrée dans la table
 * settings et appliquée automatiquement au site public. Réservé à la permission settings.manage.
 */
final class SiteSettingsController extends Base
{
    /** Contraintes des images de marque : [largeur min, hauteur min, carré exigé, plus grand côté conservé]. */
    public const IMAGE_RULES = [
        'brand_logo' => [200, 40, false, 1200],
        'brand_logo_dark' => [200, 40, false, 1200],
        'brand_logo_footer' => [200, 80, false, 1200],
        'brand_logo_footer_dark' => [200, 80, false, 1200],
        'brand_favicon' => [192, 192, true, 1024],
        'og_image' => [600, 315, false, 1600],
        'home_hero_image' => [1000, 600, false, 2400],
    ];

    public static function sections(): array
    {
        return require APP_PATH . '/config/site-sections.php';
    }

    private static function section(string $slug): array
    {
        $sections = self::sections();
        if (!isset($sections[$slug])) {
            throw new HttpException(404, 'Section inconnue.');
        }
        return $sections[$slug];
    }

    /** Ancienne adresse « Réglages ». */
    public function legacy(Request $r): Response
    {
        return redirect('/admin/parametres');
    }

    public function index(Request $r): Response
    {
        return admin_view('site/index', ['sections' => self::sections(), 'pageTitle' => 'Paramètres du site']);
    }

    public function edit(Request $r, array $errors = [], array $old = []): Response
    {
        $slug = (string) $r->params['section'];
        $section = self::section($slug);
        $defs = Settings::definitions();
        $fields = [];
        foreach ($section['keys'] as $key) {
            $fields[$key] = $defs[$key] + ['value' => array_key_exists($key, $old) ? (string) $old[$key] : Settings::get($key)];
        }
        $images = [];
        foreach ($section['images'] ?? [] as $key) {
            $images[$key] = $defs[$key] + ['value' => Settings::get($key), 'rule' => self::IMAGE_RULES[$key]];
        }
        return admin_view('site/section', [
            'slug' => $slug, 'section' => $section, 'sections' => self::sections(), 'fields' => $fields, 'images' => $images,
            'errors' => $errors, 'fonts' => ThemeService::FONTS, 'pageTitle' => $section['title'],
        ], $errors ? 422 : 200);
    }

    /** Enregistre UNIQUEMENT les clés de la section (une case non cochée d'une autre section n'est jamais modifiée). */
    public function save(Request $r): Response
    {
        $slug = (string) $r->params['section'];
        $section = self::section($slug);
        $defs = Settings::definitions();
        $values = [];
        foreach ($section['keys'] as $key) {
            $values[$key] = ($defs[$key]['type'] ?? '') === 'bool' ? ($r->str($key) === '1' ? '1' : '0') : (is_string($r->post[$key] ?? null) ? (string) $r->post[$key] : Settings::get($key));
        }
        $errors = [];
        // Contrôles croisés
        if ($slug === 'apparence') {
            foreach (ThemeService::contrastProblems(ThemeService::sanitize($values)) as $key => $message) {
                $errors[$key] = $message;
            }
        }
        if ($slug === 'langues' && $values['lang_default'] === 'en' && $values['lang_en_enabled'] !== '1') {
            $errors['lang_default'] = 'L\'anglais ne peut être la langue par défaut que si la version anglaise est proposée.';
        }
        if ($slug === 'commerce' && isset($values['payment_timeout_minutes']) && (int) $values['payment_timeout_minutes'] < 5) {
            $errors['payment_timeout_minutes'] = 'Délai de paiement : 5 minutes minimum.';
        }
        if ($slug === 'reseaux' && $values['social_other'] !== '' && trim($values['social_other_label']) === '') {
            $errors['social_other_label'] = 'Indiquez le nom affiché pour l\'autre lien.';
        }
        // Validation de chaque valeur sans rien enregistrer tant qu'une erreur subsiste.
        $changed = [];
        if (!$errors) {
            $snapshot = [];
            foreach ($values as $key => $value) {
                $snapshot[$key] = Settings::get($key);
            }
            foreach ($values as $key => $value) {
                if ($value === $snapshot[$key]) {
                    continue;
                }
                try {
                    Settings::set($key, $value);
                    $changed[] = $key;
                } catch (\InvalidArgumentException $e) {
                    $errors[$key] = $e->getMessage();
                }
            }
            if ($errors) {
                // Restauration : la section est enregistrée entièrement ou pas du tout.
                foreach ($changed as $key) {
                    Settings::set($key, $snapshot[$key]);
                }
                $changed = [];
            }
        }
        if ($errors) {
            Session::flash('error', 'Rien n\'a été enregistré : corrigez les champs signalés.');
            return $this->edit($r, $errors, $values);
        }
        if ($changed) {
            Audit::log('settings.update', 'settings', $slug, ['keys' => $changed]);
        }
        Session::flash('success', $changed ? 'Paramètres enregistrés (' . count($changed) . ' modification(s)). Le site public est à jour.' : 'Aucune modification.');
        return redirect('/admin/parametres/' . $slug);
    }

    /** Téléversement d'une image de marque (contrôlée et ré-encodée côté serveur). */
    public function uploadImage(Request $r): Response
    {
        $key = (string) $r->params['key'];
        if (!isset(self::IMAGE_RULES[$key])) {
            throw new HttpException(404);
        }
        [$minW, $minH, $square, $maxSide] = self::IMAGE_RULES[$key];
        $file = $r->files['image'] ?? null;
        if (!is_array($file)) {
            Session::flash('error', 'Choisissez une image.');
            return redirect('/admin/parametres/logos#' . $key);
        }
        if ($square && is_string($file['tmp_name'] ?? null) && ($info = @getimagesize($file['tmp_name'])) !== false) {
            $ratio = $info[0] / max(1, $info[1]);
            if ($ratio < 0.85 || $ratio > 1.18) {
                Session::flash('error', 'Le favicon doit être une image carrée (emblème seul, sans texte de préférence).');
                return redirect('/admin/parametres/logos#' . $key);
            }
        }
        $result = ImageService::store($file, 'brand', $maxSide, ['minWidth' => $minW, 'minHeight' => $minH]);
        if (!$result['ok']) {
            Session::flash('error', (string) $result['error']);
            return redirect('/admin/parametres/logos#' . $key);
        }
        $previous = Settings::get($key);
        Settings::set($key, $result['path']);
        if ($previous !== '' && $previous !== $result['path']) {
            ImageService::delete($previous);
        }
        if ($key === 'brand_favicon') {
            $src = BrandIcons::load(BASE_PATH . '/public' . $result['path']);
            if ($src !== null) {
                BrandIcons::generate($src, BASE_PATH . '/public/uploads/brand/favicon', '/uploads/brand/favicon', [255, 255, 255], Settings::get('color_primary'), Settings::get('store_name'));
            }
        }
        Audit::log('settings.image', 'settings', $key, ['path' => $result['path']]);
        Session::flash('success', 'Image enregistrée (' . $result['width'] . ' × ' . $result['height'] . ' px). Elle est en ligne.');
        return redirect('/admin/parametres/logos#' . $key);
    }

    public function deleteImage(Request $r): Response
    {
        $key = (string) $r->params['key'];
        if (!isset(self::IMAGE_RULES[$key])) {
            throw new HttpException(404);
        }
        $previous = Settings::get($key);
        if ($previous !== '') {
            Settings::set($key, '');
            ImageService::delete($previous);
            if ($key === 'brand_favicon') {
                foreach (glob(BASE_PATH . '/public/uploads/brand/favicon/*') ?: [] as $f) {
                    @unlink($f);
                }
                // Retour aux icônes installées : copie de leur favicon.ico à la racine.
                if (is_file(BASE_PATH . '/public/assets/img/brand/favicon.ico')) {
                    @copy(BASE_PATH . '/public/assets/img/brand/favicon.ico', BASE_PATH . '/public/favicon.ico');
                }
            }
            Audit::log('settings.image_delete', 'settings', $key, ['path' => $previous]);
        }
        Session::flash('success', 'Image retirée : la valeur par défaut est de nouveau utilisée.');
        return redirect('/admin/parametres/logos#' . $key);
    }

    /** Aperçu de l'apparence avant publication (rien n'est enregistré). */
    public function preview(Request $r): Response
    {
        $values = ThemeService::sanitize($r->post);
        return view('admin/site/preview', [
            'values' => $values, 'problems' => ThemeService::contrastProblems($values),
            'cssUrl' => '/admin/parametres/apparence/apercu.css?' . http_build_query($values),
        ], 200, null);
    }

    public function previewCss(Request $r): Response
    {
        return new Response(ThemeService::css(ThemeService::sanitize($r->query)), 200, ['Content-Type' => 'text/css; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
