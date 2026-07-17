<?php

/**
 * Validate an uploaded application icon without moving it.
 *
 * @return array{success: bool, extension: string, message: string}
 */
function validate_app_icon_upload(?array $file, int $maxBytes = 5242880): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'extension' => '', 'message' => 'No file uploaded'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'extension' => '', 'message' => 'Upload error'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    $reportedSize = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_file($tmpName) || $reportedSize <= 0) {
        return ['success' => false, 'extension' => '', 'message' => 'Invalid upload'];
    }

    $actualSize = filesize($tmpName);
    if ($actualSize === false || $actualSize <= 0 || $actualSize > $maxBytes || $reportedSize > $maxBytes) {
        return ['success' => false, 'extension' => '', 'message' => 'Image must not exceed 5 MB'];
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!class_exists('finfo') || !function_exists('getimagesize')) {
        return ['success' => false, 'extension' => '', 'message' => 'Server image validation is unavailable'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    $imageInfo = @getimagesize($tmpName);

    if ($imageInfo === false || !isset($allowedMimeTypes[$mimeType])) {
        return ['success' => false, 'extension' => '', 'message' => 'Only JPEG, PNG, GIF or WebP images are allowed'];
    }

    $detectedImageMime = $imageInfo['mime'] ?? '';
    if ($detectedImageMime !== $mimeType) {
        return ['success' => false, 'extension' => '', 'message' => 'Image content does not match its MIME type'];
    }

    return [
        'success' => true,
        'extension' => $allowedMimeTypes[$mimeType],
        'message' => 'Valid image',
    ];
}

/**
 * Validate and store an uploaded application icon.
 *
 * @return array{success: bool, filename: string, message: string}
 */
function save_app_icon_upload(?array $file, string $uploadDir): array
{
    $validation = validate_app_icon_upload($file);
    if (!$validation['success']) {
        return ['success' => false, 'filename' => '', 'message' => $validation['message']];
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        return ['success' => false, 'filename' => '', 'message' => 'Unable to create upload directory'];
    }

    $safeFileName = 'app_icon_' . bin2hex(random_bytes(16)) . '.' . $validation['extension'];
    $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $safeFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'filename' => '', 'message' => 'Failed to store uploaded image'];
    }

    return ['success' => true, 'filename' => $safeFileName, 'message' => 'Upload Success'];
}

/**
 * Validate and move an icon into a non-public staging directory.
 *
 * @return array{success: bool, filename: string, staged_path: string, message: string}
 */
function stage_app_icon_upload(?array $file, string $stagingDir): array
{
    $validation = validate_app_icon_upload($file);
    if (!$validation['success']) {
        return ['success'=>false,'filename'=>'','staged_path'=>'','message'=>$validation['message']];
    }
    if (!is_dir($stagingDir) && !mkdir($stagingDir, 0700, true)) {
        return ['success'=>false,'filename'=>'','staged_path'=>'','message'=>'Unable to create upload staging directory'];
    }
    $filename = 'app_icon_' . bin2hex(random_bytes(16)) . '.' . $validation['extension'];
    $stagedPath = rtrim($stagingDir, '/\\') . DIRECTORY_SEPARATOR . '.pending_' . bin2hex(random_bytes(16));
    if (!move_uploaded_file((string) $file['tmp_name'], $stagedPath)) {
        return ['success'=>false,'filename'=>'','staged_path'=>'','message'=>'Failed to stage uploaded image'];
    }
    @chmod($stagedPath, 0600);
    return ['success'=>true,'filename'=>$filename,'staged_path'=>$stagedPath,'message'=>'Upload staged'];
}

/** Publish a staged icon using an atomic rename on the same filesystem. */
function publish_staged_app_icon(array $staged, string $uploadDir): string
{
    $filename = sanitize_existing_app_icon((string) ($staged['filename'] ?? ''));
    $stagedPath = (string) ($staged['staged_path'] ?? '');
    if ($filename === '' || $stagedPath === '' || !is_file($stagedPath)) {
        throw new RuntimeException('Invalid staged application icon.');
    }
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        throw new RuntimeException('Unable to create upload directory.');
    }
    $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($target) || !rename($stagedPath, $target)) {
        throw new RuntimeException('Unable to publish staged application icon.');
    }
    @chmod($target, 0644);
    return $target;
}

function discard_staged_app_icon(array $staged): void
{
    $path = (string) ($staged['staged_path'] ?? '');
    if ($path !== '' && is_file($path) && !unlink($path)) {
        error_log('Unable to discard staged application icon: ' . basename($path));
    }
}

/**
 * Accept only a plain image filename already stored in public_img.
 */
function sanitize_existing_app_icon(?string $fileName): string
{
    $fileName = basename(trim((string) $fileName));

    if (!preg_match('/\Aapp_icon_[A-Za-z0-9_-]+\.(?:jpe?g|png|gif|webp)\z/i', $fileName)) {
        return '';
    }

    return $fileName;
}
