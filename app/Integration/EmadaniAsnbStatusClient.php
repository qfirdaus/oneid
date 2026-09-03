<?php
declare(strict_types=1);

namespace OneId\App\Integration;

final class EmadaniAsnbStatusClient
{
    private readonly ?\Closure $transport;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $clientId,
        private readonly string $secret,
        private readonly int $timeoutSeconds = 5,
        ?callable $transport = null
    ) {
        $this->transport = $transport === null ? null : \Closure::fromCallable($transport);
    }

    /** @return null|array{applicable:bool,asnb_complete:bool} */
    public function check(string $matrik): ?array
    {
        $matrik = strtoupper(trim($matrik));
        if (!$this->isConfigured() || preg_match('/^[A-Z0-9_-]{3,30}$/', $matrik) !== 1) return null;
        $body = json_encode(['matrik' => $matrik], JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) return null;

        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(16));
        $path = (string)(parse_url($this->endpoint, PHP_URL_PATH) ?: '');
        $canonical = "POST\n{$path}\n{$timestamp}\n{$nonce}\n" . hash('sha256', $body);
        $headers = [
            'Accept: application/json', 'Content-Type: application/json',
            'X-Client-Id: ' . $this->clientId, 'X-Timestamp: ' . $timestamp,
            'X-Nonce: ' . $nonce,
            'X-Signature: ' . hash_hmac('sha256', $canonical, $this->secret),
        ];
        try {
            $response = $this->transport !== null
                ? ($this->transport)($this->endpoint, $headers, $body, $this->timeoutSeconds)
                : $this->curlRequest($headers, $body);
        } catch (\Throwable) {
            return null;
        }
        if (($response['status'] ?? 0) !== 200) return null;
        $decoded = json_decode((string)($response['body'] ?? ''), true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true
            || !is_bool($decoded['applicable'] ?? null) || !is_bool($decoded['asnb_complete'] ?? null)) return null;
        return ['applicable' => $decoded['applicable'], 'asnb_complete' => $decoded['asnb_complete']];
    }

    private function isConfigured(): bool
    {
        return filter_var($this->endpoint, FILTER_VALIDATE_URL) !== false
            && strtolower((string)parse_url($this->endpoint, PHP_URL_SCHEME)) === 'https'
            && $this->clientId !== '' && strlen($this->secret) >= 32 && $this->timeoutSeconds >= 1;
    }

    private function curlRequest(array $headers, string $body): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('CURL_UNAVAILABLE');
        $handle = curl_init($this->endpoint);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => min(3, $this->timeoutSeconds), CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $responseBody = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if (!is_string($responseBody)) throw new \RuntimeException('EMADANI_REQUEST_FAILED');
        return ['status' => $status, 'body' => $responseBody];
    }
}
