<?php

class Validator
{
    public static function required($value)
    {
        return !($value === null || $value === '');
    }

    public static function string($value)
    {
        return is_string($value);
    }

    public static function email($value)
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function integer($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public static function boolean($value)
    {
        return is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1';
    }

    public static function enum($value, array $allowed)
    {
        return in_array($value, $allowed, true);
    }

    public static function arrayValue($value)
    {
        return is_array($value);
    }

    public static function arrayOfEnum($values, array $allowed)
    {
        if (!is_array($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (!in_array($value, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    public static function maxLength($value, $max)
    {
        if ($value === null) {
            return true;
        }

        return is_string($value) && mb_strlen($value) <= $max;
    }

    public static function minLength($value, $min)
    {
        return is_string($value) && mb_strlen($value) >= $min;
    }

    public static function url($value)
    {
        return $value === null || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function nullable($value, callable $rule)
    {
        return $value === null || $rule($value);
    }

    public static function uniqueArray(array $values)
    {
        return count($values) === count(array_unique($values, SORT_REGULAR));
    }

    public static function collectErrors(array $rules)
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $message => $result) {
                if (!$result) {
                    $errors[] = [
                        'field' => $field,
                        'message' => is_string($message) ? $message : 'Invalid field.',
                    ];
                }
            }
        }

        return $errors;
    }
}
