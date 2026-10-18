<?php
declare(strict_types=1);

namespace OneId\App\Monitoring;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class UserWeatherService
{
    private const WEATHER_ENDPOINT = 'https://api.open-meteo.com/v1/forecast';
    private const AIR_ENDPOINT = 'https://air-quality-api.open-meteo.com/v1/air-quality';
    private const MAX_RESPONSE_BYTES = 262144;

    public function __construct(
        private readonly string $cacheFile,
        private readonly float $latitude,
        private readonly float $longitude,
        private readonly int $cacheTtlSeconds = 900,
        private readonly int $staleTtlSeconds = 21600,
        private readonly int $timeoutMs = 3000,
        private readonly int $forceRefreshCooldownSeconds = 60
    ) {
    }

    /** @return array<string,mixed> */
    public function current(bool $forceRefresh = false): array
    {
        $cache = $this->readCache();
        if (!$forceRefresh) {
            $fresh = $this->freshSections($cache);
            if (count($fresh) === 2) {
                return $this->publicResult($fresh, []);
            }
        } elseif ($this->forceRefreshIsCoolingDown($cache)) {
            return $this->publicResult($this->cachedSections($cache, false), []);
        }

        $lock = $this->acquireRefreshLock();
        try {
            // Another request may have refreshed the shared cache while this
            // request was waiting for the lock.
            $cache = $this->readCache();
            if (!$forceRefresh) {
                $fresh = $this->freshSections($cache);
                if (count($fresh) === 2) {
                    return $this->publicResult($fresh, []);
                }
            } elseif ($this->forceRefreshIsCoolingDown($cache)) {
                return $this->publicResult($this->cachedSections($cache, false), []);
            }

            return $this->refresh($cache);
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** @param array<string,mixed> $cache @return array<string,mixed> */
    private function refresh(array $cache): array
    {
        $responses = $this->fetchConcurrent($this->providerUrls());
        $sections = [];
        $failures = [];
        foreach (['weather', 'air_quality'] as $section) {
            try {
                $sections[$section] = $this->normalize($section, $responses[$section] ?? null);
                $cache[$section] = ['cached_at_epoch' => time(), 'data' => $sections[$section]];
            } catch (RuntimeException $exception) {
                $cached = is_array($cache[$section] ?? null) ? $cache[$section] : [];
                $age = time() - (int) ($cached['cached_at_epoch'] ?? 0);
                if ($age <= $this->staleTtlSeconds && is_array($cached['data'] ?? null)) {
                    $sections[$section] = $cached['data'];
                    $sections[$section]['stale'] = $age > $this->cacheTtlSeconds;
                } else {
                    $failures[$section] = $exception->getMessage();
                }
            }
        }
        if ($cache !== []) {
            $this->writeCache($cache);
        }
        if ($sections === []) {
            throw new RuntimeException('WEATHER_AND_AIR_QUALITY_UNAVAILABLE');
        }
        return $this->publicResult($sections, $failures);
    }

    /** @param array<string,mixed> $cache @return array<string,array<string,mixed>> */
    private function freshSections(array $cache): array
    {
        $sections = [];
        foreach (['weather', 'air_quality'] as $section) {
            $cached = is_array($cache[$section] ?? null) ? $cache[$section] : [];
            $age = max(0, time() - (int) ($cached['cached_at_epoch'] ?? 0));
            if ($age <= $this->cacheTtlSeconds && is_array($cached['data'] ?? null)) {
                $sections[$section] = $cached['data'];
                $sections[$section]['stale'] = false;
            }
        }
        return $sections;
    }

    /** @param array<string,mixed> $cache @return array<string,array<string,mixed>> */
    private function cachedSections(array $cache, bool $stale): array
    {
        $sections = [];
        foreach (['weather', 'air_quality'] as $section) {
            $cached = is_array($cache[$section] ?? null) ? $cache[$section] : [];
            if (is_array($cached['data'] ?? null)) {
                $sections[$section] = $cached['data'];
                $sections[$section]['stale'] = $stale;
            }
        }
        return $sections;
    }

    /** @param array<string,mixed> $cache */
    private function forceRefreshIsCoolingDown(array $cache): bool
    {
        if (count($this->cachedSections($cache, false)) !== 2) {
            return false;
        }
        $timestamps = [];
        foreach (['weather', 'air_quality'] as $section) {
            $timestamps[] = (int) ($cache[$section]['cached_at_epoch'] ?? 0);
        }
        return max(0, time() - min($timestamps)) < $this->forceRefreshCooldownSeconds;
    }

    /** @return resource|null */
    private function acquireRefreshLock()
    {
        $directory = dirname($this->cacheFile);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            return null;
        }
        $handle = @fopen($this->cacheFile . '.lock', 'c');
        if ($handle === false) {
            return null;
        }
        if (!@flock($handle, LOCK_EX)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    /** @return array<string,string> */
    private function providerUrls(): array
    {
        $common = [
            'latitude' => number_format($this->latitude, 4, '.', ''),
            'longitude' => number_format($this->longitude, 4, '.', ''),
            'timezone' => 'Asia/Kuala_Lumpur',
        ];
        return [
            'weather' => self::WEATHER_ENDPOINT . '?' . http_build_query($common + [
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,precipitation,weather_code,wind_speed_10m',
            ], '', '&', PHP_QUERY_RFC3986),
            'air_quality' => self::AIR_ENDPOINT . '?' . http_build_query($common + [
                'current' => 'pm10,pm2_5,us_aqi',
            ], '', '&', PHP_QUERY_RFC3986),
        ];
    }

    /** @param array<string,string> $urls @return array<string,array<string,mixed>|null> */
    private function fetchConcurrent(array $urls): array
    {
        $multi = curl_multi_init();
        $handles = [];
        $bodies = [];
        foreach ($urls as $key => $url) {
            $bodies[$key] = '';
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT_MS => min(1500, $this->timeoutMs),
                CURLOPT_TIMEOUT_MS => $this->timeoutMs,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'OneID-Weather/1.0',
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$bodies, $key): int {
                    if (strlen($bodies[$key]) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                        return 0;
                    }
                    $bodies[$key] .= $chunk;
                    return strlen($chunk);
                },
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$key] = $handle;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 0.25);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $responses = [];
        foreach ($handles as $key => $handle) {
            $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $contentType = strtolower((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
            $curlError = curl_errno($handle);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
            if ($curlError !== 0 || $httpCode !== 200 || !str_starts_with($contentType, 'application/json')) {
                $responses[$key] = null;
                continue;
            }
            $payload = json_decode($bodies[$key], true);
            $responses[$key] = is_array($payload) ? $payload : null;
        }
        curl_multi_close($multi);
        return $responses;
    }

    /** @param array<string,mixed>|null $payload @return array<string,mixed> */
    private function normalize(string $section, ?array $payload): array
    {
        $current = is_array($payload['current'] ?? null) ? $payload['current'] : null;
        $required = $section === 'weather'
            ? ['time', 'temperature_2m', 'apparent_temperature', 'relative_humidity_2m', 'precipitation', 'weather_code', 'wind_speed_10m']
            : ['time', 'pm10', 'pm2_5', 'us_aqi'];
        if ($current === null || array_diff($required, array_keys($current)) !== []) {
            throw new RuntimeException(strtoupper($section) . '_PROVIDER_UNAVAILABLE');
        }
        foreach (array_slice($required, 1) as $field) {
            if (!is_numeric($current[$field])) {
                throw new RuntimeException(strtoupper($section) . '_PROVIDER_RESPONSE_INVALID');
            }
        }
        try {
            $malaysiaTimezone = new DateTimeZone('Asia/Kuala_Lumpur');
            $observedAt = (new DateTimeImmutable((string) $current['time'], $malaysiaTimezone))
                ->setTimezone($malaysiaTimezone)
                ->format(DATE_ATOM);
        } catch (Throwable) {
            throw new RuntimeException(strtoupper($section) . '_PROVIDER_TIME_INVALID');
        }
        $base = ['observed_at' => $observedAt, 'checked_at' => gmdate(DATE_ATOM), 'stale' => false];
        if ($section === 'weather') {
            return $base + [
                'temperature_c' => round((float) $current['temperature_2m'], 1),
                'apparent_temperature_c' => round((float) $current['apparent_temperature'], 1),
                'humidity_percent' => max(0, min(100, (int) round((float) $current['relative_humidity_2m']))),
                'precipitation_mm' => max(0, round((float) $current['precipitation'], 1)),
                'weather_code' => (int) $current['weather_code'],
                'wind_speed_kmh' => max(0, round((float) $current['wind_speed_10m'], 1)),
            ];
        }
        return $base + [
            'air_quality_aqi' => max(0, (int) round((float) $current['us_aqi'])),
            'pm2_5' => max(0, round((float) $current['pm2_5'], 1)),
            'pm10' => max(0, round((float) $current['pm10'], 1)),
        ];
    }

    /** @return array<string,mixed> */
    private function readCache(): array
    {
        if (!is_file($this->cacheFile)) {
            return [];
        }
        $value = json_decode((string) @file_get_contents($this->cacheFile), true);
        return is_array($value) ? $value : [];
    }

    /** @param array<string,mixed> $value */
    private function writeCache(array $value): void
    {
        $directory = dirname($this->cacheFile);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            return;
        }
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (Throwable) {
            return;
        }
        $temporary = $this->cacheFile . '.' . $suffix . '.tmp';
        if (@file_put_contents($temporary, json_encode($value, JSON_UNESCAPED_SLASHES), LOCK_EX) !== false) {
            @rename($temporary, $this->cacheFile);
        }
        @unlink($temporary);
    }

    /** @param array<string,array<string,mixed>> $sections @param array<string,string> $failures @return array<string,mixed> */
    private function publicResult(array $sections, array $failures): array
    {
        return [
            'location' => 'UPNM, Kuala Lumpur',
            'weather_available' => isset($sections['weather']),
            'air_quality_available' => isset($sections['air_quality']),
            'weather' => $sections['weather'] ?? null,
            'air_quality' => $sections['air_quality'] ?? null,
            'unavailable_sections' => array_keys($failures),
        ];
    }
}
