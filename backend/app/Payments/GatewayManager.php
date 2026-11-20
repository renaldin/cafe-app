<?php

namespace App\Payments;

class GatewayManager
{
    /** Gateway aktif bila PAYMENT_GATEWAY diisi & kuncinya lengkap. */
    public static function active(): ?PaymentGateway
    {
        $name = strtolower((string) config('services.payment_gateway'));

        return match ($name) {
            'midtrans' => config('services.midtrans.server_key') ? new MidtransGateway : null,
            'xendit' => config('services.xendit.secret_key') ? new XenditGateway : null,
            default => null,
        };
    }

    public static function enabled(): bool
    {
        return self::active() !== null;
    }

    public static function byName(string $name): ?PaymentGateway
    {
        $active = self::active();

        return $active && $active->name() === $name ? $active : null;
    }
}
