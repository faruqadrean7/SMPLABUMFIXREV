<?php

namespace App\Support;

class PaginationHelper
{
    /**
     * Clamp pagination page size safely between $min and $max.
     */
    public static function clamp(mixed $value, int $default = 15, int $min = 1, int $max = 100): int
    {
        $clampedDefault = max($min, min($max, $default));

        if ($value === null || $value === '' || is_bool($value) || is_array($value) || is_object($value)) {
            return $clampedDefault;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return $clampedDefault;
            }
        }

        $intVal = filter_var($value, FILTER_VALIDATE_INT);
        if ($intVal !== false) {
            return max($min, min($max, $intVal));
        }

        if (is_numeric($value)) {
            $floatVal = (float) $value;
            if ($floatVal >= $max) {
                return $max;
            }
            if ($floatVal <= $min) {
                return $min;
            }

            return max($min, min($max, (int) $floatVal));
        }

        return $clampedDefault;
    }
}
