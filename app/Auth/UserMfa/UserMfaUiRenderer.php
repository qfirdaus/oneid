<?php

declare(strict_types=1);

namespace OneId\App\Auth\UserMfa;

final class UserMfaUiRenderer
{
    /** @param array<string, mixed> $state */
    public function challenge(string $locale, array $state): string
    {
        $text = UserMfaUiCatalogue::forLocale($locale);
        $locale = $locale === 'en' ? 'en' : 'ms';
        $totp = (bool) ($state['totp_enabled'] ?? false)
            && (bool) ($state['active_totp'] ?? false);
        $masked = $this->escape((string) ($state['masked_email'] ?? ''));
        $csrf = $this->escape((string) ($state['csrf_token'] ?? ''));
        $status = $this->status($text, (string) ($state['status'] ?? 'empty'));
        $totpOption = $totp
            ? '<label><input type="radio" name="factor" value="TOTP"> '
                . $this->escape($text['factor.totp']) . '</label>'
            : '<p id="totp-unavailable">' . $this->escape($text['factor.unavailable']) . '</p>';

        return '<section class="user-mfa" lang="' . $locale . '" aria-labelledby="mfa-title">'
            . '<a class="skip-link" href="#mfa-main">' . $this->escape($text['skip']) . '</a>'
            . '<main id="mfa-main" tabindex="-1">'
            . '<h1 id="mfa-title">' . $this->escape($text['title.challenge']) . '</h1>'
            . $status
            . '<p role="status" aria-live="polite">' . $this->escape($text['email.sent'])
                . ($masked !== '' ? ' <span class="masked-destination">' . $masked . '</span>' : '') . '</p>'
            . '<form method="post" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<fieldset><legend>' . $this->escape($text['factor.legend']) . '</legend>'
            . '<label><input type="radio" name="factor" value="EMAIL_OTP" checked> '
                . $this->escape($text['factor.email']) . '</label>'
            . $totpOption . '</fieldset>'
            . '<label for="mfa-code">' . $this->escape($totp ? $text['totp.label'] : $text['email.label']) . '</label>'
            . '<p id="mfa-code-hint">' . $this->escape($text['email.hint']) . '</p>'
            . '<input id="mfa-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" '
                . 'minlength="6" maxlength="6" autocomplete="one-time-code" aria-describedby="mfa-code-hint mfa-error" required>'
            . '<p id="mfa-error" role="alert" aria-live="assertive"></p>'
            . '<button type="submit">' . $this->escape($text['submit.verify']) . '</button>'
            . '<button type="submit" name="action" value="resend" formnovalidate>'
                . $this->escape($text['email.resend']) . '</button>'
            . '</form></main></section>';
    }

    /** @param array<string, mixed> $state */
    public function accountSecurity(string $locale, array $state): string
    {
        $text = UserMfaUiCatalogue::forLocale($locale);
        $locale = $locale === 'en' ? 'en' : 'ms';
        $active = (bool) ($state['active_totp'] ?? false);
        $enrolling = (bool) ($state['enrollment_pending'] ?? false);
        $csrf = $this->escape((string) ($state['csrf_token'] ?? ''));
        $factorState = $active
            ? '<p role="status">' . $this->escape($text['factor.totp']) . '</p>'
            : '<p class="empty-state">' . $this->escape($text['state.empty']) . '</p>';
        $qr = $enrolling
            ? '<div id="totp-qr" role="img" aria-label="' . $this->escape($text['totp.qr'])
                . '" data-qr-source="same-origin-post" data-cache-control="no-store"></div>'
                . '<p id="totp-help">' . $this->escape($text['totp.qr_help']) . '</p>'
            : '';

        return '<section class="user-mfa" lang="' . $locale . '" aria-labelledby="security-title">'
            . '<h1 id="security-title">' . $this->escape($text['title.security']) . '</h1>'
            . $this->status($text, (string) ($state['status'] ?? 'empty'))
            . $factorState . $qr
            . '<form method="post" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<label for="totp-code">' . $this->escape($text['totp.label']) . '</label>'
            . '<input id="totp-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" '
                . 'minlength="6" maxlength="6" autocomplete="one-time-code" aria-describedby="totp-help security-error">'
            . '<p id="security-error" role="alert" aria-live="assertive"></p>'
            . '<button type="submit" name="action" value="enroll">' . $this->escape($text['totp.enroll']) . '</button>'
            . '<button type="submit" name="action" value="confirm">' . $this->escape($text['submit.confirm']) . '</button>'
            . '<button type="submit" name="action" value="revoke">' . $this->escape($text['totp.revoke']) . '</button>'
            . '</form>'
            . '<aside aria-labelledby="recovery-title"><h2 id="recovery-title">'
                . $this->escape($text['title.recovery']) . '</h2><p>'
                . $this->escape($text['recovery.safe']) . '</p></aside>'
            . '</section>';
    }

    /** @param array<string, mixed> $state */
    public function adminConfiguration(string $locale, array $state): string
    {
        $text = UserMfaUiCatalogue::forLocale($locale);
        $locale = $locale === 'en' ? 'en' : 'ms';
        $mode = in_array(($state['mode'] ?? ''), UserLoginMfaPolicy::MODES, true)
            ? (string) $state['mode'] : 'OFF';
        $csrf = $this->escape((string) ($state['csrf_token'] ?? ''));
        $options = '';
        foreach (UserLoginMfaPolicy::MODES as $canonicalMode) {
            $options .= '<option value="' . $canonicalMode . '"'
                . ($canonicalMode === $mode ? ' selected' : '') . '>' . $canonicalMode . '</option>';
        }

        return '<section class="user-mfa" lang="' . $locale . '" aria-labelledby="admin-mfa-title">'
            . '<h1 id="admin-mfa-title">' . $this->escape($text['title.admin']) . '</h1>'
            . '<p role="alert">' . $this->escape($text['admin.not_authorized']) . '</p>'
            . $this->status($text, (string) ($state['status'] ?? 'empty'))
            . '<form method="post" autocomplete="off">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<label for="mfa-mode">' . $this->escape($text['admin.mode']) . '</label>'
            . '<select id="mfa-mode" name="mode">' . $options . '</select>'
            . '<label><input type="checkbox" checked disabled> ' . $this->escape($text['admin.email']) . '</label>'
            . '<input type="hidden" name="email_enabled" value="1">'
            . '<label><input type="checkbox" name="totp_enabled" value="1"> ' . $this->escape($text['admin.totp']) . '</label>'
            . '<label for="mfa-reason">' . $this->escape($text['admin.reason']) . '</label>'
            . '<textarea id="mfa-reason" name="reason" required></textarea>'
            . '<label for="mfa-reference">' . $this->escape($text['admin.reference']) . '</label>'
            . '<input id="mfa-reference" name="reference" type="text" required>'
            . '<p id="admin-mfa-error" role="alert" aria-live="assertive"></p>'
            . '<button type="submit">' . $this->escape($text['submit.save']) . '</button>'
            . '</form></section>';
    }

    /** @param array<string, string> $text */
    private function status(array $text, string $state): string
    {
        $state = in_array($state, ['loading', 'empty', 'success', 'error'], true) ? $state : 'empty';
        $role = $state === 'error' ? 'alert' : 'status';
        return '<div class="mfa-state mfa-state-' . $state . '" role="' . $role
            . '" aria-live="' . ($state === 'error' ? 'assertive' : 'polite') . '">'
            . $this->escape($text['state.' . $state]) . '</div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
