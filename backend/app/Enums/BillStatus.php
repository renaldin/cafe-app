<?php

namespace App\Enums;

enum BillStatus: string
{
    case Open = 'open';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Void = 'void';
    case Refunded = 'refunded';
    case Merged = 'merged';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::AwaitingPayment => 'Menunggu Bayar',
            self::Paid => 'Lunas',
            self::Void => 'Void',
            self::Refunded => 'Refund',
            self::Merged => 'Digabung',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::AwaitingPayment], true);
    }
}
