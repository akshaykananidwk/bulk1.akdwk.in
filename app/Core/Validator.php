<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Input validator.
 *
 * Rules: required, required_if:field,value, nullable, string, integer, numeric,
 * boolean, array, email, url, phone, min:n, max:n, between:a,b, in:a,b,c,
 * not_in:a,b, regex:pattern, alpha, alpha_num, alpha_dash, slug, date,
 * date_format:F, after:date, before:date, confirmed, same:field,
 * different:field, unique:table,column[,ignoreId], exists:table,column,
 * json, ip, timezone, digits:n, digits_between:a,b, starts_with:x,
 * uploaded_file, mimes:a,b, max_file:kb, hex_color, password_strength.
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    private array $errors = [];
    private array $validated = [];
    private bool $ran = false;

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public function fails(): bool
    {
        $this->run();
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        $this->run();
        return $this->errors;
    }

    public function firstError(): ?string
    {
        $this->run();
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                return $error;
            }
        }
        return null;
    }

    public function validated(): array
    {
        $this->run();
        return $this->validated;
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }
        $this->ran = true;

        foreach ($this->rules as $field => $ruleset) {
            $rules = is_array($ruleset) ? $ruleset : explode('|', $ruleset);
            $value = $this->data[$field] ?? null;
            $isNullable = in_array('nullable', $rules, true);
            $isEmpty = $value === null || $value === '' || $value === [];

            if ($isNullable && $isEmpty) {
                $this->validated[$field] = $value;
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule === 'nullable' || $rule === '') {
                    continue;
                }
                [$name, $params] = $this->parseRule($rule);

                if ($isEmpty && !in_array($name, ['required', 'required_if'], true)) {
                    continue;
                }

                if (!$this->apply($name, $params, $field, $value)) {
                    $this->addError($field, $name, $params);
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function parseRule(string $rule): array
    {
        if (str_starts_with($rule, 'regex:')) {
            return ['regex', [substr($rule, 6)]];
        }
        $parts = explode(':', $rule, 2);
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];
        return [$parts[0], $params];
    }

    private function apply(string $name, array $params, string $field, mixed $value): bool
    {
        switch ($name) {
            case 'required':
                return !($value === null || $value === '' || $value === []);
            case 'required_if':
                $other = $this->data[$params[0] ?? ''] ?? null;
                if ((string) $other === (string) ($params[1] ?? '')) {
                    return !($value === null || $value === '' || $value === []);
                }
                return true;
            case 'string':
                return is_string($value) || is_numeric($value);
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            case 'numeric':
                return is_numeric($value);
            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false', 'on', 'off'], true);
            case 'array':
                return is_array($value);
            case 'email':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'phone':
                return is_scalar($value) && preg_match('/^\+?[1-9]\d{6,14}$/', preg_replace('/[\s\-()]/', '', (string) $value) ?? '') === 1;
            case 'min':
                $min = (float) ($params[0] ?? 0);
                if (is_numeric($value) && !is_string($value)) {
                    return (float) $value >= $min;
                }
                if (is_array($value)) {
                    return count($value) >= (int) $min;
                }
                return mb_strlen((string) $value) >= (int) $min;
            case 'max':
                $max = (float) ($params[0] ?? PHP_FLOAT_MAX);
                if (is_numeric($value) && !is_string($value)) {
                    return (float) $value <= $max;
                }
                if (is_array($value)) {
                    return count($value) <= (int) $max;
                }
                return mb_strlen((string) $value) <= (int) $max;
            case 'between':
                // HTTP input is always a string — numeric strings must be
                // compared by VALUE, not by string length ("10" is 10, not 2)
                $len = is_numeric($value) ? (float) $value : (is_array($value) ? count($value) : mb_strlen((string) $value));
                return $len >= (float) ($params[0] ?? 0) && $len <= (float) ($params[1] ?? PHP_FLOAT_MAX);
            case 'in':
                return in_array((string) $value, array_map('strval', $params), true);
            case 'not_in':
                return !in_array((string) $value, array_map('strval', $params), true);
            case 'regex':
                return is_scalar($value) && @preg_match($params[0], (string) $value) === 1;
            case 'alpha':
                return is_string($value) && preg_match('/^[\pL]+$/u', $value) === 1;
            case 'alpha_num':
                return is_scalar($value) && preg_match('/^[\pL\pN]+$/u', (string) $value) === 1;
            case 'alpha_dash':
                return is_scalar($value) && preg_match('/^[\pL\pN_-]+$/u', (string) $value) === 1;
            case 'slug':
                return is_scalar($value) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) === 1;
            case 'date':
                return is_string($value) && strtotime($value) !== false;
            case 'date_format':
                $dt = \DateTime::createFromFormat($params[0] ?? 'Y-m-d', (string) $value);
                return $dt !== false && $dt->format($params[0] ?? 'Y-m-d') === (string) $value;
            case 'after':
                return is_string($value) && strtotime($value) !== false && strtotime($value) > strtotime($params[0] ?? 'now');
            case 'before':
                return is_string($value) && strtotime($value) !== false && strtotime($value) < strtotime($params[0] ?? 'now');
            case 'confirmed':
                return $value === ($this->data[$field . '_confirmation'] ?? null);
            case 'same':
                return $value === ($this->data[$params[0] ?? ''] ?? null);
            case 'different':
                return $value !== ($this->data[$params[0] ?? ''] ?? null);
            case 'unique':
                return $this->checkUnique($params, $value);
            case 'exists':
                $table = preg_replace('/[^a-z0-9_]/i', '', $params[0] ?? '');
                $column = preg_replace('/[^a-z0-9_]/i', '', $params[1] ?? 'id');
                if ($table === '') {
                    return false;
                }
                return DB::table($table)->where($column, $value)->exists();
            case 'json':
                return is_string($value) && json_decode($value) !== null;
            case 'ip':
                return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false;
            case 'timezone':
                return is_string($value) && in_array($value, \DateTimeZone::listIdentifiers(), true);
            case 'digits':
                return is_scalar($value) && preg_match('/^\d{' . (int) ($params[0] ?? 1) . '}$/', (string) $value) === 1;
            case 'digits_between':
                return is_scalar($value) && preg_match('/^\d{' . (int) ($params[0] ?? 1) . ',' . (int) ($params[1] ?? 20) . '}$/', (string) $value) === 1;
            case 'starts_with':
                return is_string($value) && str_starts_with($value, $params[0] ?? '');
            case 'hex_color':
                return is_string($value) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value) === 1;
            case 'password_strength':
                return is_string($value) && mb_strlen($value) >= 8
                    && preg_match('/[a-z]/', $value) === 1
                    && preg_match('/[A-Z0-9]/', $value) === 1;
            case 'uploaded_file':
                return is_array($value) && ($value['error'] ?? -1) === UPLOAD_ERR_OK;
            case 'mimes':
                if (!is_array($value) || empty($value['name'])) {
                    return false;
                }
                $ext = strtolower(pathinfo((string) $value['name'], PATHINFO_EXTENSION));
                return in_array($ext, array_map('strtolower', $params), true);
            case 'max_file':
                return is_array($value) && ((int) ($value['size'] ?? 0)) <= ((int) ($params[0] ?? 0)) * 1024;
            default:
                // Unknown rule: fail closed so typos are caught in development.
                return false;
        }
    }

    private function checkUnique(array $params, mixed $value): bool
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $params[0] ?? '');
        $column = preg_replace('/[^a-z0-9_]/i', '', $params[1] ?? 'id');
        if ($table === '') {
            return false;
        }
        $query = DB::table($table)->where($column, $value);
        if (isset($params[2]) && is_numeric($params[2])) {
            $query->where('id', '!=', (int) $params[2]);
        }
        if (isset($params[3]) && $params[3] === 'tenant' && Tenant::id() !== null) {
            $query->where('tenant_id', Tenant::id());
        }
        return !$query->exists();
    }

    private function addError(string $field, string $rule, array $params): void
    {
        $custom = $this->messages[$field . '.' . $rule] ?? $this->messages[$field] ?? null;
        if ($custom !== null) {
            $this->errors[$field][] = $custom;
            return;
        }

        $label = __('fields.' . $field, ucwords(str_replace('_', ' ', $field)));
        $key = 'validation.' . $rule;
        $message = __($key);
        if ($message === $key) {
            $message = ':field is invalid.';
        }
        $message = str_replace([':field', ':min', ':max', ':values'], [
            $label,
            $params[0] ?? '',
            $params[1] ?? ($params[0] ?? ''),
            implode(', ', $params),
        ], $message);

        $this->errors[$field][] = $message;
    }
}
