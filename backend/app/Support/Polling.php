<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Helper polling ringan: klien mengirim ?since=<versi terakhir>.
 * Bila tidak ada perubahan, server cukup membalas {changed:false}.
 */
class Polling
{
    public static function unchanged(?string $since, ?string $latest): bool
    {
        if (! $since || ! $latest) {
            return false;
        }
        try {
            $sinceAt = Carbon::parse($since);
            $latestAt = Carbon::parse($latest);
        } catch (\Throwable) {
            return false;
        }
        // Selisih 2 detik terakhir selalu dianggap berubah (presisi timestamp per detik).
        if ($latestAt->greaterThanOrEqualTo(now()->subSeconds(2))) {
            return false;
        }

        return $latestAt->lessThanOrEqualTo($sinceAt);
    }

    public static function version(?string ...$timestamps): ?string
    {
        $max = null;
        foreach ($timestamps as $t) {
            if ($t && (! $max || $t > $max)) {
                $max = $t;
            }
        }

        return $max ? Carbon::parse($max)->format('Y-m-d H:i:s') : null;
    }
}
