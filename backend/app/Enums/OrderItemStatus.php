<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Processing => 'Diproses',
            self::Ready => 'Siap',
            self::Served => 'Disajikan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /** Transisi yang diizinkan (maju) */
    public function allowedNext(): array
    {
        return match ($this) {
            self::New => [self::Processing, self::Ready, self::Cancelled],
            self::Processing => [self::Ready, self::Cancelled, self::New],
            self::Ready => [self::Served, self::Processing],
            self::Served => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Served, self::Cancelled], true);
    }
}
