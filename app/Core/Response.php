<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response helpers. All methods that emit output terminate the request.
 */
final class Response
{
    /**
     * Send a JSON response and exit.
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send an HTML response and exit.
     */
    public static function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * Send plain text and exit.
     */
    public static function text(string $text, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $text;
        exit;
    }

    /**
     * Redirect and exit.
     */
    public static function redirect(string $url, int $status = 302): never
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /**
     * Stream a file download and exit.
     */
    public static function download(string $path, ?string $name = null, ?string $mime = null): never
    {
        if (!is_file($path)) {
            self::abort(404);
        }
        $name = $name ?? basename($path);
        $mime = $mime ?? (function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream');

        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');

        $fp = fopen($path, 'rb');
        if ($fp !== false) {
            while (!feof($fp)) {
                echo fread($fp, 1048576);
                flush();
            }
            fclose($fp);
        }
        exit;
    }

    /**
     * Serve a file inline (images, media previews) and exit.
     */
    public static function file(string $path, ?string $mime = null): never
    {
        if (!is_file($path)) {
            self::abort(404);
        }
        $mime = $mime ?? (function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    /**
     * Abort with an error status. Renders the themed error page for web requests.
     */
    public static function abort(int $status, string $message = ''): never
    {
        http_response_code($status);

        if (Request::instance()->wantsJson()) {
            self::json(['success' => false, 'message' => $message !== '' ? $message : self::statusText($status)], $status);
        }

        $view = RESOURCE_PATH . '/views/errors/' . $status . '.php';
        if (is_file($view)) {
            include $view;
        } else {
            echo '<h1>' . $status . ' — ' . e(self::statusText($status)) . '</h1>';
            if ($message !== '') {
                echo '<p>' . e($message) . '</p>';
            }
        }
        exit;
    }

    public static function statusText(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Session Expired',
            422 => 'Validation Failed',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }
}
