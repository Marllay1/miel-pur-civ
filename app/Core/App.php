<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Noyau HTTP : routage, middlewares (auth, staff, perm:x, guest, nocsrf), erreurs, en-têtes de sécurité.
 */
final class App
{
    private static ?Router $router = null;
    public static ?Request $request = null;

    public static function router(): Router
    {
        if (self::$router === null) {
            $router = new Router();
            require APP_PATH . '/routes.php';
            self::$router = $router;
        }
        return self::$router;
    }

    public static function run(): void
    {
        $request = Request::fromGlobals();
        Session::start();
        self::handle($request)->send();
    }

    public static function handle(Request $r): Response
    {
        self::$request = $r;
        Auth::reset();
        Audit::$ip = $r->ip();
        $locale = Lang::DEFAULT;
        if ($r->path === '/en' || str_starts_with($r->path, '/en/')) {
            $locale = 'en';
            $r->path = substr($r->path, 3) ?: '/';
        }
        if ($r->path === '/admin' || str_starts_with($r->path, '/admin/')) {
            $locale = Lang::DEFAULT;
        }
        Lang::set($locale);
        // Langues : Paramètres du site → Langues. Le français est la langue de référence (adresses sans préfixe).
        try {
            $englishOn = \App\Services\Settings::bool('lang_en_enabled');
            $defaultLang = \App\Services\Settings::get('lang_default');
            $detect = \App\Services\Settings::bool('lang_detect_browser');
        } catch (\Throwable) {
            [$englishOn, $defaultLang, $detect] = [true, 'fr', false];
        }
        if ($locale === 'en' && !$englishOn) {
            // Version anglaise désactivée : la page française correspondante est servie.
            return self::secure($r, Response::redirect($r->path . ($r->query ? '?' . http_build_query($r->query) : '')));
        }
        // Accueil sans préfixe : choix mémorisé (sélecteur), sinon langue par défaut, sinon langue du navigateur (si activé).
        if ($englishOn && $r->method === 'GET' && $locale === Lang::DEFAULT && ($r->server['REQUEST_URI'] ?? '/') === '/' && $r->path === '/') {
            $chosen = $r->cookie('mpci_lang');
            if ($chosen === null) {
                $chosen = $defaultLang === 'en' ? 'en' : 'fr';
                if ($detect) {
                    $accept = strtolower((string) ($r->server['HTTP_ACCEPT_LANGUAGE'] ?? ''));
                    if (preg_match('/^\s*([a-z]{2})/', $accept, $m) && in_array($m[1], ['fr', 'en'], true)) {
                        $chosen = $m[1];
                    }
                }
            }
            if ($chosen === 'en') {
                return self::secure($r, Response::redirect('/en'));
            }
        }
        $theme = $r->cookie('mpci_theme');
        View::$shared = ['request' => $r, 'theme' => in_array($theme, ['light', 'dark'], true) ? $theme : null];
        try {
            if (preg_match('/[\x00-\x1F\x7F]/', $r->path . ($r->server['REQUEST_URI'] ?? '')) === 1 || str_contains((string) ($r->server['REQUEST_URI'] ?? ''), '%00')) {
                throw new HttpException(400, 'Requête invalide.');
            }
            [$route, $params] = self::router()->match($r->method, $r->path);
            $r->params = $params;
            $response = self::guard($r, $route['middleware']) ?? self::call($route['handler'], $r);
        } catch (HttpException $e) {
            $response = self::error($r, $e->status, $e->getMessage(), $e->kind);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'path' => $r->path]);
            $response = self::error($r, 500, Env::bool('APP_DEBUG') ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() : '');
        }
        if (!Lang::isDefault()) {
            if (isset($response->headers['Location'])) {
                $response->headers['Location'] = Lang::path($response->headers['Location']);
            }
            if (str_starts_with($response->headers['Content-Type'] ?? '', 'text/html')) {
                $response->body = Lang::localizeHtml($response->body);
            }
        }
        $response->headers['Content-Language'] = Lang::get();
        return self::secure($r, $response);
    }

    private static function call(array $handler, Request $r): Response
    {
        [$class, $method] = $handler;
        $result = (new $class())->$method($r);
        return $result instanceof Response ? $result : Response::html((string) $result);
    }

    private static function guard(Request $r, array $middleware): ?Response
    {
        if (in_array($r->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !in_array('nocsrf', $middleware, true) && !Csrf::check($r)) {
            throw new HttpException(403, Lang::t('error.csrf'), 'csrf');
        }
        foreach ($middleware as $mw) {
            if ($mw === 'auth' && !Auth::check()) {
                return Response::redirect('/compte/connexion?retour=' . rawurlencode($r->path));
            }
            if ($mw === 'guest' && Auth::check()) {
                return Response::redirect(Auth::isStaff() ? '/admin' : '/compte');
            }
            if (($mw === 'staff' || str_starts_with($mw, 'perm:')) && !Auth::isStaff()) {
                return self::denyAdmin($r);
            }
            if (str_starts_with($mw, 'perm:')) {
                if (!Auth::can(substr($mw, 5))) {
                    throw new HttpException(403, 'Votre rôle ne donne pas accès à cette section.');
                }
            }
        }
        return null;
    }

    /**
     * Accès à l'administration sans compte d'équipe.
     *  - client connecté : refus explicite (403), jamais de formulaire de connexion de l'équipe ni de redirection en boucle ;
     *  - visiteur anonyme : page de connexion de l'équipe (GET), puis retour à la page demandée ; 403 pour toute autre méthode.
     */
    public static function denyAdmin(Request $r): Response
    {
        if (Auth::check()) {
            throw new HttpException(403, '', 'admin_denied');
        }
        if (Auth::$expired) {
            Session::flash('error', 'Session expirée après 2 heures d\'inactivité. Reconnectez-vous.');
        }
        if ($r->method !== 'GET' || $r->wantsJson()) {
            throw new HttpException(403);
        }
        return Response::redirect('/admin/connexion' . ($r->path !== '/admin' ? '?retour=' . rawurlencode($r->path) : ''));
    }

    public static function error(Request $r, int $status, string $message = '', string $kind = ''): Response
    {
        $kind = $kind !== '' ? $kind : (string) $status;
        $title = Lang::has('error.title.' . $kind) ? Lang::t('error.title.' . $kind) : Lang::t('error.title.default');
        if ($r->wantsJson() || str_starts_with($r->path, '/webhooks/')) {
            return Response::json(['error' => $message !== '' ? $message : $title], $status);
        }
        try {
            $layout = str_starts_with($r->path, '/admin') && Auth::isStaff() ? 'layouts/admin' : 'layouts/main';
            $html = View::render('errors/error', ['status' => $status, 'kind' => $kind, 'title' => $title, 'message' => $message, 'pageTitle' => $title, 'noindex' => true], $layout);
        } catch (\Throwable $e) {
            Log::error('Rendu de la page d\'erreur impossible : ' . $e->getMessage());
            $html = '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title><h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
        }
        return Response::html($html, $status);
    }

    private static function secure(Request $r, Response $resp): Response
    {
        $extraScript = '';
        $extraConnect = '';
        // Les formulaires de paiement redirigent vers les pages hébergées des prestataires (Wave, Orange, CinetPay).
        $formTargets = '';
        if ($r->path === '/commande' || str_starts_with($r->path, '/commande/')) {
            foreach (['wave', 'orange_money', 'card'] as $code) {
                try {
                    if (\App\Services\PaymentService::gateway($code)?->isEnabled()) {
                        $formTargets = ' https:';
                        break;
                    }
                } catch (\Throwable) {
                }
            }
        }
        try {
            $provider = \App\Services\Settings::get('analytics_provider');
            if ($provider === 'plausible') {
                $extraScript = ' https://plausible.io';
                $extraConnect = ' https://plausible.io';
            } elseif ($provider === 'ga4') {
                $extraScript = ' https://www.googletagmanager.com';
                $extraConnect = ' https://www.google-analytics.com https://region1.google-analytics.com';
            }
        } catch (\Throwable) {
        }
        $csp = "default-src 'self'; script-src 'self'$extraScript; style-src 'self'; img-src 'self' data:; font-src 'self'; "
            . "connect-src 'self'$extraConnect; form-action 'self'$formTargets; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";
        $resp->headers += [
            'Content-Security-Policy' => $csp,
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($r->isSecure() || Cookie::secure()) {
            $resp->headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach (['/admin', '/compte', '/panier', '/commande', '/paiement', '/suivi-commande'] as $private) {
            if ($r->path === $private || str_starts_with($r->path, $private . '/')) {
                $resp->headers['Cache-Control'] = 'no-store, private';
                $resp->headers['X-Robots-Tag'] = 'noindex, nofollow';
                break;
            }
        }
        // Page personnalisée (lien « Administration », compte) : jamais mise en cache par un intermédiaire.
        if (!isset($resp->headers['Cache-Control']) && Auth::check()) {
            $resp->headers['Cache-Control'] = 'private, no-cache';
        }
        return $resp;
    }
}
