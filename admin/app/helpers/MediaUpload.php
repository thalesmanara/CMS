<?php

declare(strict_types=1);

namespace Revita\Crm\Helpers;

use Revita\Crm\Models\Media;

final class MediaUpload
{
    private const MAX_IMAGE_BYTES = 12_000_000;

    private const MAX_VIDEO_BYTES = 80_000_000;

    /** @var list<string> */
    private static array $imageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /** @var list<string> */
    private static array $videoExt = ['mp4', 'webm', 'ogg'];

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $file
     */
    public static function handle(?array $file, string $kind, ?int $userId): ?int
    {
        if ($file === null || !isset($file['error']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return null;
        }
        $original = (string) ($file['name'] ?? 'file');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $relativeSubdir = $kind === 'video' ? 'uploads/videos' : 'uploads/images';
        $base = REVITA_CRM_ROOT . '/' . $relativeSubdir;
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        if ($kind === 'video') {
            if (!in_array($ext, self::$videoExt, true)) {
                return null;
            }
            if ((int) ($file['size'] ?? 0) > self::MAX_VIDEO_BYTES) {
                return null;
            }
            $stored = 'v_' . bin2hex(random_bytes(12)) . '.' . $ext;
            $dest = $base . '/' . $stored;
            if (!move_uploaded_file($tmp, $dest)) {
                return null;
            }
            $model = new Media();
            return $model->insert(
                'video',
                $relativeSubdir . '/' . $stored,
                $original,
                $stored,
                $file['type'] ?? null,
                isset($file['size']) ? (int) $file['size'] : null,
                $userId
            );
        }
        if (!in_array($ext, self::$imageExt, true)) {
            return null;
        }
        if ((int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            return null;
        }
        $stored = 'img_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = $base . '/' . $stored;
        if (!move_uploaded_file($tmp, $dest)) {
            return null;
        }
        $model = new Media();
        return $model->insert(
            'image',
            $relativeSubdir . '/' . $stored,
            $original,
            $stored,
            $file['type'] ?? null,
            isset($file['size']) ? (int) $file['size'] : null,
            $userId
        );
    }
}
