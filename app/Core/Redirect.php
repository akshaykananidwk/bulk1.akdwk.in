<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fluent redirect builder with session flash support.
 *
 *   Redirect::to('/tenant/contacts')->with('success', 'Saved')->send();
 *   Redirect::back()->withErrors($errors)->withInput()->send();
 */
final class Redirect
{
    private string $url;
    private int $status = 302;

    private function __construct(string $url)
    {
        $this->url = $url;
    }

    public static function to(string $url, int $status = 302): self
    {
        $redirect = new self($url);
        $redirect->status = $status;
        return $redirect;
    }

    public static function back(string $fallback = '/'): self
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = Request::instance()->host();
        // Only redirect back to our own host — never to an external referer.
        if ($referer !== '') {
            $refHost = strtolower((string) (parse_url($referer, PHP_URL_HOST) ?? ''));
            if ($refHost === $host) {
                return new self($referer);
            }
        }
        return new self($fallback);
    }

    public static function route(string $name, array $params = []): self
    {
        return new self(route($name, $params));
    }

    public function with(string $key, mixed $value): self
    {
        $_SESSION['_flash'][$key] = $value;
        return $this;
    }

    public function withErrors(array $errors): self
    {
        $_SESSION['_flash']['errors'] = $errors;
        return $this;
    }

    public function withInput(?array $input = null): self
    {
        $input = $input ?? Request::instance()->all();
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['_method']);
        $_SESSION['_flash']['old'] = $input;
        return $this;
    }

    public function send(): never
    {
        Response::redirect($this->url, $this->status);
    }
}
