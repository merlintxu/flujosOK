<?php

namespace FlujosDimension\Support;

class Validator
{
    /**
     * Validate data against a set of rules.
     * Returns an array of error messages indexed by field.
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $ruleList = explode('|', $ruleString);

            foreach ($ruleList as $rule) {
                if ($rule === 'required') {
                    if ($value === null || $value === '') {
                        $errors[$field][] = "Field {$field} is required";
                    }
                    continue;
                }

                if ($value === null || $value === '') {
                    continue; // Skip other rules if value not provided
                }

                if ($rule === 'string' && !is_string($value)) {
                    $errors[$field][] = "Field {$field} must be a string";
                } elseif ($rule === 'integer' && !self::isInteger($value)) {
                    $errors[$field][] = "Field {$field} must be an integer";
                } elseif (str_starts_with($rule, 'in:')) {
                    $allowed = explode(',', substr($rule, 3));
                    if (!in_array($value, $allowed, true)) {
                        $errors[$field][] = "Field {$field} must be one of: " . implode(', ', $allowed);
                    }
                } elseif (str_starts_with($rule, 'format:')) {
                    $format = substr($rule, 7);
                    if (!self::validateFormat($value, $format)) {
                        $errors[$field][] = "Field {$field} has invalid format";
                    }
                }
            }
        }

        return $errors;
    }

    private static function isInteger($value): bool
    {
        return is_int($value) || (is_string($value) && ctype_digit($value));
    }

    private static function validateFormat($value, string $format): bool
    {
        return match ($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'   => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'phone' => preg_match('/^\+[1-9]\d{7,14}$/', $value) === 1,
            default => true,
        };
    }
}
