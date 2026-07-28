<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with view/JSON/validation conveniences.
 */
abstract class Controller
{
    protected ?string $layout = null;

    protected function view(string $view, array $data = []): never
    {
        View::render($view, $data, $this->layout);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function ok(mixed $data = null, string $message = 'OK'): never
    {
        Response::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $message, int $status = 422, mixed $errors = null): never
    {
        Response::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    protected function redirect(string $url): Redirect
    {
        return Redirect::to($url);
    }

    protected function back(): Redirect
    {
        return Redirect::back();
    }

    /**
     * Validate request input. On failure: JSON 422 for API/AJAX,
     * redirect-back-with-errors for web.
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = new Validator($request->all(), $rules, $messages);
        if ($validator->fails()) {
            if ($request->wantsJson()) {
                $this->fail(__('validation.failed'), 422, $validator->errors());
            }
            Redirect::back()->withErrors($validator->errors())->withInput()->send();
        }
        return $validator->validated();
    }
}
