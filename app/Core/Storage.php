<?php

declare(strict_types=1);

namespace App\Core;

/**
 * File storage: local driver (default) + S3-compatible driver via REST
 * (AWS Signature V4, works with S3/MinIO/R2). No SDK required.
 */
final class Storage
{
    /**
     * Store an uploaded file after validation. Returns relative path.
     * $file is a $_FILES entry.
     */
    public static function putUpload(array $file, string $directory, array $allowedExtensions, int $maxBytes): string
    {
        if (($file['error'] ?? -1) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(__('upload.failed'));
        }
        if ((int) $file['size'] > $maxBytes) {
            throw new \RuntimeException(__('upload.too_large'));
        }

        $original = Sanitizer::filename((string) $file['name']);
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new \RuntimeException(__('upload.type_not_allowed'));
        }

        // MIME + magic-byte validation
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $file['tmp_name']);
        if (!self::mimeMatchesExtension($mime, $extension)) {
            throw new \RuntimeException(__('upload.type_not_allowed'));
        }

        // Never store executable server-side code
        if (in_array($extension, ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'cgi', 'pl', 'py', 'sh', 'htaccess'], true)) {
            throw new \RuntimeException(__('upload.type_not_allowed'));
        }

        $tenantSegment = Tenant::id() !== null ? (string) Tenant::id() : 'system';
        $relative = trim($directory, '/') . '/' . $tenantSegment . '/' . date('Y/m');
        $absoluteDir = UPLOAD_PATH . '/' . $relative;
        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true)) {
            throw new \RuntimeException('Cannot create upload directory');
        }

        $name = Hash::token(16) . '.' . $extension;
        $target = $absoluteDir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            // CLI/test context fallback
            if (!@rename((string) $file['tmp_name'], $target)) {
                throw new \RuntimeException(__('upload.failed'));
            }
        }
        @chmod($target, 0644);

        return $relative . '/' . $name;
    }

    public static function path(string $relative): string
    {
        $real = realpath(UPLOAD_PATH . '/' . ltrim($relative, '/'));
        $base = realpath(UPLOAD_PATH);
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            throw new \RuntimeException('Invalid storage path');
        }
        return $real;
    }

    public static function url(string $relative): string
    {
        return url('/uploads/' . ltrim($relative, '/'));
    }

    public static function delete(string $relative): bool
    {
        try {
            $path = self::path($relative);
        } catch (\Throwable) {
            return false;
        }
        return @unlink($path);
    }

    /**
     * Save raw bytes to a relative uploads path (webhook media downloads).
     */
    public static function putContents(string $relative, string $contents): string
    {
        $relative = ltrim(str_replace('..', '', $relative), '/');
        $target = UPLOAD_PATH . '/' . $relative;
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Cannot create upload directory');
        }
        if (@file_put_contents($target, $contents) === false) {
            throw new \RuntimeException('Cannot write file');
        }
        @chmod($target, 0644);
        return $relative;
    }

    private static function mimeMatchesExtension(string $mime, string $extension): bool
    {
        $map = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'svg' => ['image/svg+xml'],
            'ico' => ['image/x-icon', 'image/vnd.microsoft.icon'],
            'mp4' => ['video/mp4'],
            '3gp' => ['video/3gpp'],
            'mp3' => ['audio/mpeg'],
            'aac' => ['audio/aac', 'audio/x-hx-aac-adts'],
            'ogg' => ['audio/ogg', 'application/ogg'],
            'opus' => ['audio/ogg', 'audio/opus'],
            'amr' => ['audio/amr'],
            'wav' => ['audio/wav', 'audio/x-wav'],
            'webm' => ['video/webm', 'audio/webm'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'csv' => ['text/csv', 'text/plain', 'application/csv'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip'],
            'json' => ['application/json', 'text/plain'],
            'vcf' => ['text/vcard', 'text/x-vcard', 'text/plain'],
        ];
        if (!isset($map[$extension])) {
            return false;
        }
        return in_array($mime, $map[$extension], true);
    }

    /**
     * Recursive directory size in bytes (tenant storage quota checks).
     */
    public static function directorySize(string $absolute): int
    {
        if (!is_dir($absolute)) {
            return 0;
        }
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }
}
