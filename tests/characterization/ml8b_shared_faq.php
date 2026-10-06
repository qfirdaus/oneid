<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Documentation/SharedFaqContent.php';

use OneId\App\Documentation\SharedFaqContent;

$checks = 0;
$failed = 0;
$report = static function (bool $ok, string $label) use (&$checks, &$failed): void {
    $checks++;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
};

$catalogue = new SharedFaqContent();
$ms = $catalogue->resolve('ms');
$en = $catalogue->resolve('en');
$invalid = $catalogue->resolve('xx');
$root = dirname(__DIR__, 2);
$login = (string) file_get_contents($root . '/index.php');
$dashboard = (string) file_get_contents($root . '/page/dashboard.php');
$faqCss = (string) file_get_contents($root . '/public/dist/css/oneid-user-faq.css');

$report(count($ms['entries']) === 18 && count($en['entries']) === 18, 'both approved locales contain eighteen FAQ entries');
$report(
    array_column($ms['entries'], 'id') === array_column($en['entries'], 'id')
    && count(array_unique(array_column($ms['entries'], 'id'))) === 18,
    'BM and English use the same stable FAQ identities'
);
$paragraphStructureValid = true;
foreach ([$ms, $en] as $localeContent) {
    foreach ($localeContent['entries'] as $entry) {
        $paragraphs = preg_split('/\R{2,}/', trim($entry['answer'])) ?: [];
        if (count($paragraphs) < 2 || in_array('', array_map('trim', $paragraphs), true)) {
            $paragraphStructureValid = false;
            break 2;
        }
    }
}
$report(
    $paragraphStructureValid,
    'all thirty-six localized FAQ answers contain explicit non-empty paragraph breaks'
);
$report(
    str_contains($faqCss, '.oneid-faq-answer-copy p+p{margin-top:1.15em}')
    && str_contains($login, 'oneid-user-faq.css?v=20261006-1')
    && str_contains($dashboard, 'oneid-user-faq.css?v=20261006-1'),
    'Login and User Dashboard load the FAQ stylesheet with a full-line paragraph gap'
);
$requiredUserTopics = [
    'inactive-account',
    'application-missing-or-denied',
    'downstream-access-problem',
    'browser-after-update',
    'contact-support',
];
$report(
    array_diff($requiredUserTopics, array_column($ms['entries'], 'id')) === []
    && array_diff($requiredUserTopics, array_column($en['entries'], 'id')) === [],
    'current user support topics exist in both approved locales'
);
$msById = array_column($ms['entries'], null, 'id');
$enById = array_column($en['entries'], null, 'id');
$report(
    str_contains($msById['inactive-account']['answer'], 'Hubungi PTMK')
    && str_contains($enById['inactive-account']['answer'], 'Contact PTMK')
    && str_contains($msById['contact-support']['answer'], 'Jangan sertakan kata laluan')
    && str_contains($enById['contact-support']['answer'], 'Never include your password'),
    'inactive-account and support guidance direct users safely to PTMK'
);
$report(
    $ms['fallback_used'] === false
    && $en['fallback_used'] === false
    && $invalid['effective_locale'] === 'ms',
    'approved locales resolve directly and invalid locale fails safely to BM'
);

$fallbackCatalogue = new SharedFaqContent([
    'ms' => array_combine(
        array_column($ms['entries'], 'id'),
        array_map(
            static fn (array $entry): array => ['question' => $entry['question'], 'answer' => $entry['answer']],
            $ms['entries']
        )
    ),
]);
$fallback = $fallbackCatalogue->resolve('en');
$report(
    $fallback['fallback_used'] === true
    && $fallback['effective_locale'] === 'ms'
    && str_contains((string) $fallback['fallback_notice'], 'English FAQ content is not yet available'),
    'missing English FAQ uses explicit—not silent—BM fallback'
);
$report(
    str_contains($login, "require_once __DIR__ . '/lib/shared_faq.php'")
    && str_contains($login, 'oneid_render_login_faq()')
    && !str_contains($login, '<!-- FAQ 1 -->'),
    'Login renders FAQ from the shared source'
);
$report(
    str_contains($dashboard, "require_once __DIR__ . '/../lib/shared_faq.php'")
    && str_contains($dashboard, 'oneid_render_dashboard_faq()')
    && !str_contains($dashboard, '<!-- FAQ 1 -->'),
    'User Dashboard renders FAQ from the same shared source'
);
$report(
    str_contains((string) file_get_contents($root . '/lib/shared_faq.php'), 'aria-expanded=')
    && str_contains((string) file_get_contents($root . '/lib/shared_faq.php'), 'aria-controls=')
    && str_contains((string) file_get_contents($root . '/lib/shared_faq.php'), 'role="status"'),
    'accordion and fallback notice retain accessibility semantics'
);
$report(
    str_contains($login, "oneid_translate('login.menu.manual')")
    && str_contains($login, "oneid_translate('login.menu.directory')")
    && str_contains($login, "oneid_translate('login.menu.email')")
    && str_contains($login, 'https://outlook.cloud.microsoft/mail/')
    && str_contains($login, "oneid_translate('login.contact.title')")
    && str_contains($login, "oneid_translate('login.contact.service')")
    && str_contains($login, "oneid_translate('login.contact.department')")
    && str_contains($login, "oneid_translate('login.contact.address')"),
    'Login navigation and contact information are locale-aware'
);
$report(
    str_contains($login, "oneid_current_locale() === 'en'")
    && str_contains($login, "'./public_docs/ONEID_USER_MANUAL_EN_V2.pdf'")
    && str_contains($login, "'./public_docs/ONEID_USER_MANUAL_MS_V2.pdf'")
    && !str_contains($login, "oneid_translate('login.manual_fallback_notice')")
    && !str_contains($login, '>Manual Pengguna</a>')
    && !str_contains($login, '>Hubungi Kami</h5>'),
    'Login selects the matching BM or English manual and has no stale fallback notice'
);
$report(
    str_contains($login, "oneid_translate('login.locked')")
    && str_contains($login, "oneid_translate('login.remaining_attempts'")
    && str_contains($login, "oneid_translate('common.reference')")
    && !str_contains($login, 'Terlalu banyak cubaan. Akaun anda telah dikunci.')
    && !str_contains($login, "\\nReference: "),
    'dynamic Login limiter and reference feedback use the active locale'
);
$source = (string) file_get_contents($root . '/app/Documentation/SharedFaqContent.php');
$report(
    !preg_match('/\b(?:INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|ALTER)\s+/i', $source),
    'shared FAQ source contains no persistence or mutation statement'
);

echo "RESULT checks={$checks} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
