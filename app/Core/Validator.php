<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Validation déclarative : ['email' => 'required|email|max:190'].
 * Règles : required, email, phone, int, min:n, max:n (longueur ou valeur pour int), in:a,b, same:champ, accepted, slug, date.
 */
final class Validator
{
    public array $errors = [];
    public array $data = [];

    public function __construct(private array $input, private array $rules, private array $labels = [])
    {
        $this->run();
    }

    public static function make(array $input, array $rules, array $labels = []): self
    {
        return new self($input, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $raw = $this->input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $rules = explode('|', $ruleString);
            $isInt = in_array('int', $rules, true);
            $label = $this->labels[$field] ?? Lang::t('field.default');
            if (is_array($value)) {
                $this->errors[$field] = Lang::t('validation.invalid', ['label' => $label]);
                continue;
            }
            $empty = $value === null || $value === '';
            if ($empty) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = Lang::t('validation.required', ['label' => $label]);
                } elseif (in_array('accepted', $rules, true)) {
                    $this->errors[$field] = Lang::t('validation.accepted');
                }
                $this->data[$field] = $isInt ? null : '';
                continue;
            }
            $value = (string) $value;
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($name) {
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 190 ? null : Lang::t('validation.email'),
                    'phone' => preg_match('/^\+?[0-9 .\-()]{8,20}$/', $value) ? null : Lang::t('validation.phone'),
                    'int' => preg_match('/^-?\d{1,10}$/', $value) ? null : Lang::t('validation.int', ['label' => $label]),
                    'min' => $isInt ? ((int) $value >= (int) $arg ? null : Lang::t('validation.min_int', ['label' => $label, 'n' => $arg])) : (mb_strlen($value) >= (int) $arg ? null : Lang::t('validation.min_len', ['label' => $label, 'n' => $arg])),
                    'max' => $isInt ? ((int) $value <= (int) $arg ? null : Lang::t('validation.max_int', ['label' => $label, 'n' => $arg])) : (mb_strlen($value) <= (int) $arg ? null : Lang::t('validation.max_len', ['label' => $label, 'n' => $arg])),
                    'in' => in_array($value, explode(',', (string) $arg), true) ? null : Lang::t('validation.in', ['label' => $label]),
                    'same' => $value === (string) ($this->input[$arg] ?? '') ? null : Lang::t('validation.same'),
                    'accepted' => in_array($value, ['1', 'on', 'yes', 'oui'], true) ? null : Lang::t('validation.accepted'),
                    'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? null : Lang::t('validation.slug'),
                    'date' => preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value) ? null : Lang::t('validation.date'),
                    default => null,
                };
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
            $this->data[$field] = $isInt && !isset($this->errors[$field]) ? (int) $value : $value;
        }
    }
}
