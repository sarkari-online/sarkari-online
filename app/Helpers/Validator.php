<?php
/**
 * EduPulse - Form & Input Validator Helper (Phase 1)
 */

namespace App\Helpers;

class Validator {
    private array $errors = [];
    private array $data;

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $label = ''): self {
        $name = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val === '') {
            $this->errors[$field] = "{$name} is required.";
        }
        return $this;
    }

    public function email(string $field, string $label = ''): self {
        $name = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$name} must be a valid email address.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $label = ''): self {
        $name = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = trim((string)($this->data[$field] ?? ''));
        if ($val !== '' && mb_strlen($val) < $min) {
            $this->errors[$field] = "{$name} must be at least {$min} characters.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, string $label = ''): self {
        $name = $label ?: ucfirst(str_replace('_', ' ', $field));
        $val = $this->data[$field] ?? null;
        if ($val !== null && !in_array($val, $allowed, true)) {
            $this->errors[$field] = "{$name} contains an invalid selection.";
        }
        return $this;
    }

    public function passes(): bool {
        return empty($this->errors);
    }

    public function errors(): array {
        return $this->errors;
    }

    public function firstError(): ?string {
        return reset($this->errors) ?: null;
    }
}
