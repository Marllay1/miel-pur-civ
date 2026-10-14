<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Client HTTP minimal (cURL) pour les appels serveur à serveur vers les prestataires de paiement.
 * TLS vérifié, délais courts, aucune redirection suivie.
 */
final class HttpClient
{
    /** Réponses simulées pour les tests unitaires : [ 'METHOD url-prefix' => callable(array $req): array ] */
    public static array $fakes = [];

    /**
     * @return array{status:int, body:string, json:?array, error:?string}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 20): array
    {
        foreach (self::$fakes as $pattern => $handler) {
            [$m, $prefix] = explode(' ', $pattern, 2);
            if ($m === $method && str_starts_with($url, $prefix)) {
                $res = $handler(['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body]);
                $res += ['status' => 200, 'body' => '', 'error' => null];
                $res['json'] = is_string($res['body']) ? json_decode($res['body'], true) : null;
                return $res;
            }
        }
        if (!preg_match('#^https?://#', $url)) {
            return ['status' => 0, 'body' => '', 'json' => null, 'error' => 'URL invalide'];
        }
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = is_int($k) ? $v : $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_USERAGENT => 'MielPurCI/1.0',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $raw === false ? curl_error($ch) : null;
        curl_close($ch);
        $raw = is_string($raw) ? $raw : '';
        if ($error !== null) {
            Log::error('Appel prestataire impossible', ['url' => preg_replace('#\?.*$#', '', $url), 'error' => $error]);
        }
        return ['status' => $status, 'body' => $raw, 'json' => json_decode($raw, true), 'error' => $error];
    }

    public static function json(string $method, string $url, array $data, array $headers = []): array
    {
        return self::request($method, $url, $headers + ['Content-Type' => 'application/json', 'Accept' => 'application/json'], json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
