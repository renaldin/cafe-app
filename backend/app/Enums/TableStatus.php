<?php

namespace App\Enums;

enum TableStatus: string
{
    case Empty = 'empty';
    case Occupied = 'occupied';
    case AwaitingPayment = 'awaiting_payment';
    case NeedsCleaning = 'needs_cleaning';

    public function label(): string
    {
        return match ($this) {
            self::Empty => 'Kosong',
            self::Occupied => 'Terisi',
            self::AwaitingPayment => 'Menunggu Bayar',
            self::NeedsCleaning => 'Perlu Dibersihkan',
        };
    }
}
