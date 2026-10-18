<?php

declare(strict_types=1);

use OneId\App\Monitoring\UserWeatherService;

$root = dirname(__DIR__);
require_once $root . '/app/Monitoring/UserWeatherService.php';

$serviceSource = (string) file_get_contents($root . '/app/Monitoring/UserWeatherService.php');
$endpointSource = (string) file_get_contents($root . '/lib/q_func.php');
$securitySource = (string) file_get_contents($root . '/lib/request_security.php');
$page = (string) file_get_contents($root . '/page/dashboard.php');
$top = (string) file_get_contents($root . '/page/const/top.php');
$javascript = (string) file_get_contents($root . '/public/dist/js/oneid-user-weather.js');
$stylesheet = (string) file_get_contents($root . '/public/dist/css/oneid-user-weather.css');
$english = require $root . '/config/locales/en.php';
$malay = require $root . '/config/locales/ms.php';

$reflection = new ReflectionClass(UserWeatherService::class);
$constructor = $reflection->getConstructor();
$defaults = [];
foreach ($constructor?->getParameters() ?? [] as $parameter) {
    if ($parameter->isDefaultValueAvailable()) {
        $defaults[$parameter->getName()] = $parameter->getDefaultValue();
    }
}

$cacheFile = sys_get_temp_dir() . '/oneid-weather-contract-' . getmypid() . '.json';
$seed = [
    'weather' => [
        'cached_at_epoch' => time(),
        'data' => [
            'observed_at' => '2026-10-18T10:45:00+08:00',
            'checked_at' => '2026-10-18T02:45:00+00:00',
            'stale' => false,
            'temperature_c' => 25.4,
            'apparent_temperature_c' => 31.8,
            'humidity_percent' => 96,
            'precipitation_mm' => 0.4,
            'weather_code' => 80,
            'wind_speed_kmh' => 1.5,
        ],
    ],
    'air_quality' => [
        'cached_at_epoch' => time(),
        'data' => [
            'observed_at' => '2026-10-18T10:00:00+08:00',
            'checked_at' => '2026-10-18T02:45:00+00:00',
            'stale' => false,
            'air_quality_aqi' => 153,
            'pm2_5' => 80.8,
            'pm10' => 82.0,
        ],
    ],
];
file_put_contents($cacheFile, json_encode($seed, JSON_UNESCAPED_SLASHES));
$cachedResult = (new UserWeatherService($cacheFile, 3.0506, 101.7243))->current(true);
@unlink($cacheFile);
@unlink($cacheFile . '.lock');

$normalize = $reflection->getMethod('normalize');
$normalized = $normalize->invoke(
    new UserWeatherService(sys_get_temp_dir() . '/unused-weather-cache.json', 3.0506, 101.7243),
    'weather',
    ['current' => [
        'time' => '2026-10-18T10:45',
        'temperature_2m' => 25.4,
        'apparent_temperature' => 31.8,
        'relative_humidity_2m' => 96,
        'precipitation' => 0.4,
        'weather_code' => 80,
        'wind_speed_10m' => 1.5,
    ]]
);

$checks = [
    'weather endpoint requires an authenticated user action' => str_contains($securitySource, "'user_weather_status'") && str_contains($endpointSource, "if(\$oneidGuardedAction==='user_weather_status')"),
    'fixed UPNM coordinates are resolved on the server' => str_contains($endpointSource, "oneid_config('ONEID_WEATHER_LATITUDE'") && !str_contains($javascript, 'navigator.geolocation'),
    'provider requests verify TLS and have bounded time and response size' => str_contains($serviceSource, 'CURLOPT_SSL_VERIFYPEER => true') && str_contains($serviceSource, 'CURLOPT_TIMEOUT_MS') && str_contains($serviceSource, 'MAX_RESPONSE_BYTES'),
    'shared cache refresh is protected by an exclusive file lock' => str_contains($serviceSource, "flock(\$handle, LOCK_EX)") && str_contains($serviceSource, 'Another request may have refreshed'),
    'manual refresh is sent by the UI and accepted by the backend' => str_contains($javascript, "body.set('force_refresh', '1')") && str_contains($endpointSource, "\$_POST['force_refresh']"),
    'manual refresh has a server-side cooldown' => ($defaults['forceRefreshCooldownSeconds'] ?? null) === 60 && ($cachedResult['weather_available'] ?? false) === true && ($cachedResult['air_quality_available'] ?? false) === true,
    'stale fallback remains available for six hours' => ($defaults['staleTtlSeconds'] ?? null) === 21600,
    'provider time is returned with the Malaysia UTC offset' => str_ends_with((string) ($normalized['observed_at'] ?? ''), '+08:00'),
    'AQI uses all six standard US AQI display bands' => str_contains($javascript, 'if (aqi <= 50)') && str_contains($javascript, 'if (aqi <= 300)') && str_contains($stylesheet, '.oneid-weather-air.is-hazardous'),
    'AQI explanation distinguishes the model from official DOE readings' => str_contains((string) ($english['dashboard.weather.air_quality_note'] ?? ''), 'US AQI') && str_contains((string) ($malay['dashboard.weather.air_quality_note'] ?? ''), 'US AQI'),
    'partial provider failures have separate weather and air messages' => isset($english['dashboard.weather.weather_unavailable'], $english['dashboard.weather.air_unavailable'], $malay['dashboard.weather.weather_unavailable'], $malay['dashboard.weather.air_unavailable']),
    'weather trigger and versioned assets are loaded' => str_contains($top, 'oneid_user_weather_trigger') && str_contains($page, 'oneid-user-weather.css?v=20261018-2') && str_contains($page, 'oneid-user-weather.js?v=20261018-3'),
    'refresh icon is the only loading indicator and existing data remains visible' => !str_contains($javascript, 'data-weather-loading') && str_contains($javascript, "classList.toggle('fa-spin', busy)") && str_contains($javascript, "setAttribute('aria-busy'") && !str_contains($javascript, 'if (!lastLoadedAt) content.hidden = true'),
    'observed and checked times always use two aligned rows' => str_contains($stylesheet, 'grid-template-columns:auto 1fr') && str_contains($stylesheet, '.oneid-weather-times span{grid-column:2}') && str_contains($stylesheet, '.oneid-weather-times span+span::before{content:none'),
    'mobile layout and reduced-motion mode are present' => str_contains($stylesheet, '@media(max-width:767px)') && str_contains($stylesheet, '@media(prefers-reduced-motion:reduce)'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf("%s %s\n", $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) {
        $failed++;
    }
}
printf("RESULT checks=%d failed=%d\n", count($checks), $failed);
exit($failed === 0 ? 0 : 1);
