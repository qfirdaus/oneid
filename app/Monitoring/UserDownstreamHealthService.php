<?php
declare(strict_types=1);

namespace OneId\App\Monitoring;

final class UserDownstreamHealthService
{
    public function __construct(
        private readonly string $cacheDirectory,
        private readonly int $cacheTtlSeconds = 120,
        private readonly int $connectTimeoutMs = 1200,
        private readonly int $requestTimeoutMs = 3000
    ) {
    }

    /** @param array<int,array{sp_id:string,sp_domain:string}> $applications */
    public function check(array $applications): array
    {
        $applications = array_slice($applications, 0, 12);
        $results = [];
        $pending = [];
        foreach ($applications as $application) {
            $id = (string) ($application['sp_id'] ?? '');
            $url = trim((string) ($application['sp_domain'] ?? ''));
            if ($id === '' || !$this->isSupportedUrl($url)) {
                $results[$id] = $this->result('unavailable', null, null);
                continue;
            }
            $cached = $this->readCache($url);
            if ($cached !== null) {
                $results[$id] = $cached;
            } else {
                $pending[$id] = $url;
            }
        }
        if ($pending !== []) {
            $results += $this->probe($pending);
        }
        return $results;
    }

    private function isSupportedUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && trim((string) ($parts['host'] ?? '')) !== '';
    }

    /** @param array<string,string> $pending */
    private function probe(array $pending): array
    {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($pending as $id => $url) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_NOBODY => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT_MS => $this->connectTimeoutMs,
                CURLOPT_TIMEOUT_MS => $this->requestTimeoutMs,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'OneID-Downstream-Status/1.0',
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$id] = [$handle, $url];
        }
        do {
            $code = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 0.25);
            }
        } while ($running > 0 && $code === CURLM_OK);

        $results = [];
        foreach ($handles as $id => [$handle, $url]) {
            $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $elapsedMs = (int) round(((float) curl_getinfo($handle, CURLINFO_TOTAL_TIME)) * 1000);
            $curlError = curl_errno($handle);
            $state = $this->classify($httpCode, $elapsedMs, $curlError);
            $results[$id] = $this->result($state, $httpCode ?: null, $elapsedMs ?: null);
            $this->writeCache($url, $results[$id]);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
        return $results;
    }

    private function classify(int $httpCode, int $elapsedMs, int $curlError): string
    {
        if ($curlError !== 0 || $httpCode === 0 || $httpCode >= 500) {
            return $httpCode === 503 ? 'maintenance' : 'unavailable';
        }
        return $elapsedMs > 2500 ? 'slow' : 'available';
    }

    private function result(string $state, ?int $httpCode, ?int $elapsedMs): array
    {
        return ['state' => $state, 'http_code' => $httpCode, 'response_ms' => $elapsedMs];
    }

    private function cachePath(string $url): string
    {
        return rtrim($this->cacheDirectory, '/') . '/' . hash('sha256', $url) . '.json';
    }

    private function readCache(string $url): ?array
    {
        $path = $this->cachePath($url);
        if (!is_file($path) || time() - (int) filemtime($path) > $this->cacheTtlSeconds) {
            return null;
        }
        $value = json_decode((string) @file_get_contents($path), true);
        return is_array($value) && isset($value['state']) ? $value : null;
    }

    private function writeCache(string $url, array $value): void
    {
        if (!is_dir($this->cacheDirectory) && !@mkdir($this->cacheDirectory, 0770, true) && !is_dir($this->cacheDirectory)) {
            return;
        }
        $temporary = $this->cachePath($url) . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, json_encode($value), LOCK_EX) !== false) {
            @rename($temporary, $this->cachePath($url));
        }
        @unlink($temporary);
    }
}
