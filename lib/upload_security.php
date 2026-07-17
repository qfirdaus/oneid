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
    $filename = 'app_icon_' . bin2hex(random_bytes(16)) . '.png';
    $stagedPath = rtrim($stagingDir, '/\\') . DIRECTORY_SEPARATOR . '.pending_' . bin2hex(random_bytes(16));
    $normalized = normalize_app_icon_to_png((string) $file['tmp_name'], $stagedPath);
    if (!$normalized['success']) {
        return ['success'=>false,'filename'=>'','staged_path'=>'','message'=>$normalized['message']];
    }
    @chmod($stagedPath, 0600);
    return ['success'=>true,'filename'=>$filename,'staged_path'=>$stagedPath,'message'=>'Upload normalized and staged'];
}

/** @return array{success:bool,message:string} */
function normalize_app_icon_to_png(string $sourcePath,string $targetPath,int $canvasSize=256,int $maxDimension=4096,int $maxPixels=16000000): array
{
    if(!extension_loaded('gd')||!function_exists('imagecreatefromstring')||!function_exists('imagepng')){
        return ['success'=>false,'message'=>'Server image normalization is unavailable'];
    }
    $info=@getimagesize($sourcePath);
    if($info===false||($info[0]??0)<1||($info[1]??0)<1){
        return ['success'=>false,'message'=>'Invalid image dimensions'];
    }
    $width=(int)$info[0];$height=(int)$info[1];
    if($width>$maxDimension||$height>$maxDimension||($width*$height)>$maxPixels){
        return ['success'=>false,'message'=>'Image dimensions exceed the allowed limit'];
    }
    $contents=@file_get_contents($sourcePath);
    if($contents===false||app_icon_is_animated($contents,(string)($info['mime']??''))){
        return ['success'=>false,'message'=>'Animated images are not allowed'];
    }
    $source=@imagecreatefromstring($contents);
    if($source===false)return ['success'=>false,'message'=>'Image could not be decoded'];
    if(($info['mime']??'')==='image/jpeg'&&function_exists('exif_read_data')){
        $exif=@exif_read_data($sourcePath);
        $orientation=(int)($exif['Orientation']??1);
        $rotated=false;
        if($orientation===3)$rotated=imagerotate($source,180,0);
        elseif($orientation===6)$rotated=imagerotate($source,-90,0);
        elseif($orientation===8)$rotated=imagerotate($source,90,0);
        if($rotated!==false){imagedestroy($source);$source=$rotated;}
        $width=imagesx($source);$height=imagesy($source);
    }
    $canvas=imagecreatetruecolor($canvasSize,$canvasSize);
    if($canvas===false){imagedestroy($source);return ['success'=>false,'message'=>'Image canvas could not be created'];}
    imagealphablending($canvas,false);imagesavealpha($canvas,true);
    $transparent=imagecolorallocatealpha($canvas,0,0,0,127);imagefill($canvas,0,0,$transparent);
    $scale=min($canvasSize/$width,$canvasSize/$height);
    $targetWidth=max(1,(int)round($width*$scale));$targetHeight=max(1,(int)round($height*$scale));
    $x=(int)floor(($canvasSize-$targetWidth)/2);$y=(int)floor(($canvasSize-$targetHeight)/2);
    $copied=imagecopyresampled($canvas,$source,$x,$y,0,0,$targetWidth,$targetHeight,$width,$height);
    $written=$copied&&imagepng($canvas,$targetPath,9);
    imagedestroy($source);imagedestroy($canvas);
    if(!$written){if(is_file($targetPath))@unlink($targetPath);return ['success'=>false,'message'=>'Normalized image could not be written'];}
    return ['success'=>true,'message'=>'Image normalized to 256x256 PNG'];
}

function app_icon_is_animated(string $contents,string $mime): bool
{
    if($mime==='image/gif')return substr_count($contents,"\x00\x21\xF9\x04")>1;
    if($mime==='image/webp')return str_contains(substr($contents,0,64),'ANIM');
    if($mime==='image/png')return str_contains(substr($contents,0,256),'acTL');
    return false;
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
