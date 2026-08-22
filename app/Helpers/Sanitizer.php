<?php
/**
 * EduPulse - Input Sanitization & Validation Helpers
 * Validates and sanitizes incoming user/admin requests.
 */

namespace App\Helpers;

class Sanitizer {
    public static function string(?string $value): string {
        if ($value === null) return '';
        return trim(strip_tags($value));
    }

    public static function slug(?string $value): string {
        if ($value === null) return '';
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        return trim($slug, '-');
    }

    public static function email(?string $value): string {
        if ($value === null) return '';
        return trim(filter_var($value, FILTER_SANITIZE_EMAIL) ?: '');
    }

    public static function int(?string $value, int $default = 0): int {
        if ($value === null) return $default;
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function html(?string $value): string {
        if ($value === null) return '';
        // Allowed safe HTML tags for rich editorial content
        $allowed = '<h1><h2><h3><h4><h5><h6><p><br><strong><em><b><i><u><ul><ol><li><a><blockquote><table><thead><tbody><tr><th><td><code><pre><div><span><svg><path><rect><circle><polyline><polygon><line><figure><figcaption><hr>';
        return trim(strip_tags($value, $allowed));
    }
}

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
