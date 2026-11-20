<?php

namespace App\Services;

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;

/** Katalog menu publik yang di-cache; versi dinaikkan saat data menu berubah. */
class MenuCatalog
{
    public const VERSION_KEY = 'menu_catalog_version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }
}
