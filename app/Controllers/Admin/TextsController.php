<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TextOverrides;

/** Textes de l'interface publique (titres de sections, libellés, messages au client) modifiables par rubrique, en français et en anglais. */
final class TextsController extends Base
{
    public function index(Request $r, array $errors = [], array $old = [], int $status = 200): Response
    {
        $group = array_key_exists($r->str('rubrique'), TextOverrides::GROUPS) ? $r->str('rubrique') : (string) array_key_first(TextOverrides::GROUPS);
        if (isset($r->params['group'])) {
            $group = (string) $r->params['group'];
        }
        $q = mb_strtolower(trim(mb_substr($r->str('q'), 0, 80)));
        $keys = TextOverrides::keys($group);
        if ($q !== '') {
            $keys = array_filter($keys, static fn (array $v, string $k) => str_contains(mb_strtolower($v['fr'] . ' ' . $v['en'] . ' ' . $k), $q), ARRAY_FILTER_USE_BOTH);
        }
        $current = [];
        foreach (DB::all('SELECT lang_key, locale, value FROM text_overrides') as $row) {
            $current[$row['lang_key']][$row['locale']] = $row['value'];
        }
        $counts = [];
        foreach (array_keys($current) as $k) {
            $g = TextOverrides::groupOf($k);
            if ($g !== null) {
                $counts[$g] = ($counts[$g] ?? 0) + 1;
            }
        }
        return admin_view('content/texts', ['groups' => TextOverrides::GROUPS, 'group' => $group, 'keys' => $keys, 'current' => $current, 'counts' => $counts,
            'q' => $q, 'errors' => $errors, 'old' => $old, 'pageTitle' => 'Textes du site'], $status);
    }

    public function save(Request $r): Response
    {
        $group = (string) $r->params['group'];
        if (!array_key_exists($group, TextOverrides::GROUPS)) {
            throw new HttpException(404);
        }
        $keys = TextOverrides::keys($group);
        $posted = is_array($r->post['t'] ?? null) ? $r->post['t'] : [];
        $errors = [];
        $changes = [];
        foreach ($posted as $key => $values) {
            if (!is_string($key) || !isset($keys[$key]) || !is_array($values)) {
                continue; // clé hors rubrique : ignorée
            }
            foreach (['fr', 'en'] as $locale) {
                if (!array_key_exists($locale, $values) || !is_string($values[$locale])) {
                    continue;
                }
                $value = trim(str_replace("\r\n", "\n", $values[$locale]));
                $default = $keys[$key][$locale] !== '' ? $keys[$key][$locale] : $keys[$key]['fr'];
                if ($value !== '' && ($err = TextOverrides::check($default, $value)) !== null) {
                    $errors[$key . '.' . $locale] = '« ' . mb_substr($keys[$key]['fr'], 0, 50) . ' » (' . strtoupper($locale) . ') : ' . $err;
                    continue;
                }
                $changes[] = [$key, $locale, $value, $default];
            }
        }
        if ($errors) {
            return $this->index($r, $errors, $posted, 422);
        }
        $changed = [];
        DB::transaction(static function () use ($changes, &$changed): void {
            foreach ($changes as [$key, $locale, $value, $default]) {
                $before = DB::value('SELECT value FROM text_overrides WHERE lang_key = ? AND locale = ?', [$key, $locale]);
                TextOverrides::save($key, $locale, $value, $default);
                $after = DB::value('SELECT value FROM text_overrides WHERE lang_key = ? AND locale = ?', [$key, $locale]);
                if ($before !== $after) {
                    $changed[] = $key . '.' . $locale;
                }
            }
        });
        if ($changed) {
            Audit::log('texts.update', 'texts', $group, ['keys' => $changed]);
        }
        Session::flash('success', $changed ? count($changed) . ' texte(s) mis à jour.' : 'Aucun changement.');
        return redirect(url('/admin/textes', ['rubrique' => $group]));
    }
}
