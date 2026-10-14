<?php
declare(strict_types=1);

namespace App\Services\Payment;

use App\Core\Env;
use App\Core\Log;
use App\Services\Settings;

abstract class AbstractGateway implements Gateway
{
    public function label(): string
    {
        return t('pay.' . $this->code());
    }

    public function description(): string
    {
        return t('pay.desc.' . $this->code());
    }

    public function needsPhone(): bool
    {
        return false;
    }

    public function isEnabled(): bool
    {
        if (!Settings::bool($this->code() . '_enabled')) {
            return false;
        }
        foreach ($this->requiredEnv() as $var) {
            if ((string) Env::get($var, '') === '') {
                return false;
            }
        }
        return true;
    }

    protected function env(string $key, string $default = ''): string
    {
        return (string) Env::get($key, $default);
    }

    /** URL absolue de retour client après paiement chez le prestataire. */
    protected function returnUrl(array $payment, string $orderToken, string $outcome = 'retour'): string
    {
        return base_url() . '/paiement/retour/' . $this->code() . '?' . http_build_query(array_filter(['ref' => $payment['reference'], 't' => $orderToken, 'issue' => $outcome !== 'retour' ? $outcome : null]));
    }

    protected function notifyUrl(): string
    {
        return base_url() . '/webhooks/paiement/' . $this->code();
    }

    protected function fail(string $context, array $response): never
    {
        Log::error('Prestataire ' . $this->code() . ' : ' . $context, ['status' => $response['status'] ?? 0, 'error' => $response['error'] ?? null, 'body' => mb_substr((string) ($response['body'] ?? ''), 0, 500)]);
        throw new PaymentException(t('pay.provider_error', ['provider' => $this->label()]));
    }

    /** Jeton OAuth mis en cache dans storage/cache (jamais dans le code ni dans la base). */
    protected function cachedToken(string $name, callable $fetch): string
    {
        $file = BASE_PATH . '/storage/cache/token-' . $this->code() . '-' . substr(sha1($name), 0, 12) . '.php';
        if (is_file($file)) {
            $data = @include $file;
            if (is_array($data) && ($data['expires'] ?? 0) > time() + 60 && is_string($data['token'] ?? null)) {
                return $data['token'];
            }
        }
        [$token, $ttl] = $fetch();
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }
        @file_put_contents($file, '<?php return ' . var_export(['token' => $token, 'expires' => time() + max(60, (int) $ttl)], true) . ';', LOCK_EX);
        @chmod($file, 0600);
        return $token;
    }
}
