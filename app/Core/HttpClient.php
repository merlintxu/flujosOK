<?php
declare(strict_types=1);

namespace FlujosDimension\Core;

/**
 * Cliente HTTP (curl) con reintentos y back-off exponencial.
 *
 * Ejemplo:
 *   $client = new HttpClient();
 *   [$code,$body] = $client->request('GET',$url,['Accept: application/json']);
 */
class HttpClient
{
    public function __construct(
        private int $maxRetries = 5,
        private int $baseDelayMs = 500,    // incremento exponencial
        private int $timeout     = 15
    ) {}

    /**
     * @param string          $method  GET|POST|PUT|PATCH|DELETE
     * @param string          $url
     * @param array<string>   $headers
     * @param null|array|json $data
     * @return array{0:int,1:string}    [HTTP-code, body]
     * @throws \RuntimeException
     */
    public function request(
        string $method,
        string $url,
        array  $headers = [],
        mixed  $data = null
    ): array {
        $attempt = 0;

        do {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT      => 'FlujosDimension/4.2',
            ]);

            if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
                $headers[] = 'Content-Type: application/json';
            }

            $body      = curl_exec($ch);
            $status    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException("cURL error: $curlError");
            }

            /** éxito 2xx / 3xx   ·   429 ó 5xx ⇒ posible reintento */
            $shouldRetry = in_array($status, [429, 500, 502, 503, 504], true)
                           && $attempt < $this->maxRetries;

            if (!$shouldRetry) {
                return [$status, (string)$body];
            }

            $attempt++;
            /** Retry-After viene en segundos (si existe) */
            preg_match('/Retry-After:\s*(\d+)/i', $body ?? '', $m);
            $retryAfter = isset($m[1]) ? (int)$m[1] * 1000 : 0;
            $delay      = max($retryAfter, $this->baseDelayMs * (2 ** ($attempt - 1)));
            usleep($delay * 1000);
        } while (true);
    }
}
