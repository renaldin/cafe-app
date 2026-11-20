<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Helper fungsi SQL lintas driver (MySQL produksi, SQLite untuk test). */
class SqlFn
{
    public static function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    public static function date(string $col): string
    {
        return self::isSqlite() ? "date($col)" : "DATE($col)";
    }

    public static function hour(string $col): string
    {
        return self::isSqlite() ? "CAST(strftime('%H', $col) AS INTEGER)" : "HOUR($col)";
    }

    public static function month(string $col): string
    {
        return self::isSqlite() ? "strftime('%Y-%m', $col)" : "DATE_FORMAT($col, '%Y-%m')";
    }

    public static function week(string $col): string
    {
        return self::isSqlite() ? "strftime('%Y-W%W', $col)" : "DATE_FORMAT($col, '%x-W%v')";
    }

    public static function minutesDiff(string $from, string $to): string
    {
        return self::isSqlite()
            ? "((julianday($to) - julianday($from)) * 1440.0)"
            : "(TIMESTAMPDIFF(SECOND, $from, $to) / 60.0)";
    }
}
