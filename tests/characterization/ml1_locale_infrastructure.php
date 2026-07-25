<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Locale/LocaleResolver.php';
require_once dirname(__DIR__, 2) . '/app/Locale/Translator.php';

use OneId\App\Locale\LocaleResolver;
use OneId\App\Locale\Translator;

$checks = 0;
$failed = 0;
$report = static function (bool $condition, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) {
        $failed++;
    }
};

$resolver = new LocaleResolver();
$report($resolver->resolve('en', 'ms', 'ms', 'ms') === 'en', 'authenticated preference has first precedence');
$report($resolver->resolve(null, 'en', 'ms', 'ms') === 'en', 'valid session locale has second precedence');
$report($resolver->resolve(null, null, 'en', 'ms') === 'en', 'valid guest cookie has third precedence');
$report($resolver->resolve(null, null, null, 'en') === 'en', 'valid system default has fourth precedence');
$report($resolver->resolve('fr', 'xx', '../en', 'invalid') === 'ms', 'invalid values fail safely to hard fallback BM');
$report(LocaleResolver::valid('../en') === null, 'locale path traversal value rejected');
$report(LocaleResolver::valid('EN') === 'en', 'allowed locale normalization is deterministic');

$catalogues = [
    'ms' => require dirname(__DIR__, 2) . '/config/locales/ms.php',
    'en' => require dirname(__DIR__, 2) . '/config/locales/en.php',
];
$translator = new Translator($catalogues);
$report($translator->missingKeys('en') === [], 'English catalogue has full key parity with BM');
$report($translator->translate('common.close', 'en') === 'Close', 'English catalogue resolves selected locale');
$report($translator->translate('common.close', 'invalid') === 'Tutup', 'invalid locale falls back to BM');
$report($translator->translate('missing.key', 'en') === 'missing.key', 'missing key returns safe identifier without path disclosure');

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
