<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\I18n;
use App\Services\PaymentService;
use App\Services\Settings;

final class SettingsController extends Base
{
    public function index(Request $r, array $errors = [], array $old = []): Response
    {
        $groups = [];
        foreach (Settings::definitions() as $key => $def) {
            if (in_array($key, ContentController::HOME_KEYS, true)) {
                continue;
            }
            $groups[$def['group']][$key] = $def + ['value' => $old[$key] ?? Settings::get($key)];
        }
        return admin_view('settings/index', ['groups' => $groups, 'errors' => $errors, 'pageTitle' => 'Réglages'], $errors ? 422 : 200);
    }

    public function save(Request $r): Response
    {
        $errors = [];
        $changed = [];
        foreach (Settings::definitions() as $key => $def) {
            if (in_array($key, ContentController::HOME_KEYS, true)) {
                continue;
            }
            $isBool = ($def['type'] ?? '') === 'bool';
            if (!$isBool && !array_key_exists($key, $r->post)) {
                continue;
            }
            $value = $isBool ? ($r->str($key) === '1' ? '1' : '0') : (string) $r->post[$key];
            if ($value === Settings::get($key)) {
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
            return $this->index($r, $errors, $r->post);
        }
        if ($changed) {
            Audit::log('settings.update', 'settings', null, ['keys' => $changed]);
        }
        Session::flash('success', $changed ? 'Réglages enregistrés (' . count($changed) . ' modification(s)).' : 'Aucune modification.');
        return redirect('/admin/reglages');
    }

    public function zones(Request $r, array $errors = [], ?array $old = null, int $status = 200): Response
    {
        $edit = $r->int('id') ? DB::one('SELECT * FROM shipping_zones WHERE id = ?', [(int) $r->int('id')]) : null;
        return admin_view('settings/zones', [
            'zones' => DB::all('SELECT z.*, (SELECT COUNT(*) FROM orders o WHERE o.shipping_zone_id = z.id) AS order_count FROM shipping_zones z ORDER BY z.sort_order, z.name'),
            'edit' => $edit, 'old' => $old ?? ($edit ?? ['is_active' => 1, 'allows_cod' => 1]), 'errors' => $errors, 'pageTitle' => 'Zones de livraison',
            'translation' => is_array($old['en'] ?? null) ? $old['en'] : ($edit ? I18n::get('zone', (int) $edit['id']) : []),
        ], $status);
    }

    public function zoneSave(Request $r): Response
    {
        $id = (int) $r->int('id', 0);
        $v = Validator::make($r->post, [
            'name' => 'required|max:120', 'description' => 'max:255', 'fee' => 'required|int|min:0|max:10000000',
            'free_threshold' => 'int|min:0|max:100000000', 'delay_text' => 'max:120', 'sort_order' => 'int|min:0|max:1000',
        ], ['name' => 'Le nom', 'fee' => 'Les frais', 'free_threshold' => 'Le seuil de gratuité', 'delay_text' => 'Le délai', 'sort_order' => 'L\'ordre']);
        if ($v->fails()) {
            return $this->zones($r, $v->errors, $r->post, 422);
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'name' => $v->data['name'], 'description' => $v->data['description'] ?: null, 'fee' => $v->data['fee'],
            'free_threshold' => $v->data['free_threshold'] ?: null, 'delay_text' => $v->data['delay_text'] ?: null, 'sort_order' => $v->data['sort_order'] ?? 0,
            'allows_cod' => $r->str('allows_cod') === '1' ? 1 : 0, 'is_pickup' => $r->str('is_pickup') === '1' ? 1 : 0, 'is_active' => $r->str('is_active') === '1' ? 1 : 0,
            'updated_at' => $now,
        ];
        if ($id > 0 && DB::value('SELECT id FROM shipping_zones WHERE id = ?', [$id]) !== null) {
            DB::update('shipping_zones', $data, 'id = ?', [$id]);
            Audit::log('zone.update', 'zone', $id, $data);
        } else {
            $id = DB::insert('shipping_zones', $data + ['created_at' => $now]);
            Audit::log('zone.create', 'zone', $id, $data);
        }
        $this->saveTranslation($r, 'zone', $id);
        Session::flash('success', 'Zone enregistrée.');
        return redirect('/admin/livraison');
    }

    /** Réglages à renseigner avant l'ouverture (mentions légales, contact, notification des commandes). */
    private const REQUIRED_SETTINGS = ['store_phone', 'legal_company', 'legal_rccm', 'legal_director', 'legal_host', 'order_notification_email'];

    /** État réel des intégrations et liste de contrôle avant mise en production (aucune valeur secrète affichée). */
    public function integrations(Request $r): Response
    {
        $gateways = [];
        foreach (PaymentService::gateways() as $code => $g) {
            $env = [];
            foreach ($g->requiredEnv() as $var) {
                $env[$var] = (string) Env::get($var, '') !== '';
            }
            $gateways[$code] = [
                'label' => $g->label(), 'enabled' => $g->isEnabled(), 'configured' => !in_array(false, $env, true),
                'setting' => Settings::bool($code . '_enabled'), 'env' => $env, 'demo' => $code === 'demo',
                'webhook' => base_url() . '/webhooks/paiement/' . $code,
            ];
        }
        $placeholders = [];
        foreach (self::REQUIRED_SETTINGS as $key) {
            if (Settings::isPlaceholder($key)) {
                $placeholders[$key] = Settings::definitions()[$key]['label'] ?? $key;
            }
        }
        $appUrl = (string) Env::get('APP_URL', '');
        $mailDriver = (string) Env::get('MAIL_DRIVER', 'log');
        $online = array_filter($gateways, static fn (array $g) => !$g['demo'] && $g['enabled']);
        $checks = [
            ['APP_ENV = production', Env::get('APP_ENV') === 'production'],
            ['APP_DEBUG désactivé', !Env::bool('APP_DEBUG')],
            ['APP_URL en https://', str_starts_with($appUrl, 'https://')],
            ['APP_KEY renseignée (32 caractères minimum)', strlen((string) Env::get('APP_KEY', '')) >= 32],
            ['Paiement SIMULÉ désactivé', !PaymentService::gateway('demo')->isEnabled()],
            ['Envoi d\'e-mails réel (MAIL_DRIVER = smtp ou mail)', $mailDriver !== 'log'],
            ['SMTP : hôte et identifiants renseignés', $mailDriver !== 'smtp' || ((string) Env::get('SMTP_HOST', '') !== '' && (string) Env::get('SMTP_USERNAME', '') !== '')],
            ['SMTP : certificat du serveur vérifié (SMTP_ALLOW_SELF_SIGNED=0)', !Env::bool('SMTP_ALLOW_SELF_SIGNED')],
            ['E-mail de notification des commandes valide', filter_var(Settings::get('order_notification_email'), FILTER_VALIDATE_EMAIL) !== false],
            ['Au moins un moyen de paiement proposé', Settings::bool('cod_enabled') || $online !== []],
            ['Aucun produit de démonstration actif', (int) DB::value('SELECT COUNT(*) FROM products WHERE is_demo = 1 AND is_active = 1 AND deleted_at IS NULL') === 0],
            ['Pages légales validées', (int) DB::value('SELECT COUNT(*) FROM pages WHERE is_legal = 1 AND is_validated = 0') === 0],
            ['Coordonnées et mentions légales renseignées', $placeholders === []],
            ['Au moins une zone de livraison active', (int) DB::value('SELECT COUNT(*) FROM shipping_zones WHERE is_active = 1') > 0],
        ];
        return admin_view('settings/integrations', [
            'gateways' => $gateways, 'checks' => $checks, 'placeholders' => $placeholders,
            'mailDriver' => $mailDriver, 'smtpHost' => (string) Env::get('SMTP_HOST', ''), 'analytics' => Settings::get('analytics_provider'),
            'whatsapp' => Settings::get('whatsapp_number') !== '', 'pageTitle' => 'Intégrations',
        ]);
    }
}
