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
            $label = $this->labels[$field] ?? 'Ce champ';
            if (is_array($value)) {
                $this->errors[$field] = "$label est invalide.";
                continue;
            }
            $empty = $value === null || $value === '';
            if ($empty) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = "$label est obligatoire.";
                } elseif (in_array('accepted', $rules, true)) {
                    $this->errors[$field] = 'Vous devez accepter pour continuer.';
                }
                $this->data[$field] = $isInt ? null : '';
                continue;
            }
            $value = (string) $value;
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $error = match ($name) {
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) && mb_strlen($value) <= 190 ? null : 'Adresse e-mail invalide.',
                    'phone' => preg_match('/^\+?[0-9 .\-()]{8,20}$/', $value) ? null : 'Numéro de téléphone invalide (8 à 20 chiffres).',
                    'int' => preg_match('/^-?\d{1,10}$/', $value) ? null : "$label doit être un nombre entier.",
                    'min' => $isInt ? ((int) $value >= (int) $arg ? null : "$label doit être au moins $arg.") : (mb_strlen($value) >= (int) $arg ? null : "$label doit contenir au moins $arg caractères."),
                    'max' => $isInt ? ((int) $value <= (int) $arg ? null : "$label doit être au plus $arg.") : (mb_strlen($value) <= (int) $arg ? null : "$label ne doit pas dépasser $arg caractères."),
                    'in' => in_array($value, explode(',', (string) $arg), true) ? null : "$label n'est pas une valeur autorisée.",
                    'same' => $value === (string) ($this->input[$arg] ?? '') ? null : 'Les deux valeurs ne correspondent pas.',
                    'accepted' => in_array($value, ['1', 'on', 'yes', 'oui'], true) ? null : 'Vous devez accepter pour continuer.',
                    'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) ? null : 'Uniquement minuscules, chiffres et tirets.',
                    'date' => preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/', $value) ? null : 'Date invalide.',
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
