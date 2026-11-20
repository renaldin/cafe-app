<?php

namespace App\Support;

class Money
{
    public static function format(int|float $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    public static function round(int $amount, int $step, string $mode = 'nearest'): int
    {
        if ($step <= 0) {
            return $amount;
        }

        return match ($mode) {
            'up' => (int) (ceil($amount / $step) * $step),
            'down' => (int) (floor($amount / $step) * $step),
            default => (int) (round($amount / $step) * $step),
        };
    }
}
