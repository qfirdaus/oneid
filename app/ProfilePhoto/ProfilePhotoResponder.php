<?php

declare(strict_types=1);

namespace OneId\App\ProfilePhoto;

final class ProfilePhotoResponder
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    public static function send(array $user, string $fallbackPath): never
    {
        $staffId = trim((string) ($user['data2'] ?? ''));
        $studentId = trim((string) ($user['data4'] ?? ''));
        $candidates = [];

        if (self::isSafeIdentifier($staffId)) {
            $candidates[] = 'https://esmartcard.upnm.edu.my/img/staf/' . rawurlencode($staffId) . '.jpg';
        }
        if (self::isSafeIdentifier($studentId)) {
            $candidates[] = 'https://kemasukan.upnm.edu.my/tawaran/pelajar/student_image/' . rawurlencode($studentId) . '.jpg';
        }

        if ($candidates !== [] && function_exists('curl_init')) {
            foreach ($candidates as $url) {
                $image = self::retrieve($url);
                if ($image === null) {
                    continue;
                }

                self::sendHeaders($image['mime'], 'upstream', strlen($image['body']));
                echo $image['body'];
                exit;
            }
        }

        self::sendFallback($fallbackPath);
    }

    public static function sendFallback(string $fallbackPath): never
    {
        self::sendHeaders('image/svg+xml; charset=utf-8', 'fallback');
        readfile($fallbackPath);
        exit;
    }

    private static function isSafeIdentifier(string $identifier): bool
    {
        return $identifier !== ''
            && strlen($identifier) <= 40
            && preg_match('/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/', $identifier) === 1
            && stripos($identifier, 'TEST') === false;
    }

    /** @return array{body:string,mime:string}|null */
    private static function retrieve(string $url): ?array
    {
        $body = '';
        $tooLarge = false;
        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'OneID-Profile-Photo/1.0',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $completed = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $declaredType = strtolower(trim((string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE)));
        curl_close($handle);

        if ($completed === false || $tooLarge || $status !== 200 || $body === '') {
            return null;
        }

        $imageInfo = @getimagesizefromstring($body);
        $detectedType = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
        if (!in_array($detectedType, ['image/jpeg', 'image/png'], true)
            || ($declaredType !== '' && !str_starts_with($declaredType, 'image/'))) {
            return null;
        }

        return ['body' => $body, 'mime' => $detectedType];
    }

    private static function sendHeaders(string $contentType, string $source, ?int $length = null): void
    {
        header('Content-Type: ' . $contentType);
        if ($length !== null) {
            header('Content-Length: ' . $length);
        }
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('X-OneID-Profile-Photo: ' . $source);
    }
}
