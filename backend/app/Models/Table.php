<?php

namespace App\Models;

use App\Enums\TableStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Table extends Model
{
    use SoftDeletes;

    protected $fillable = ['area_id', 'code', 'name', 'capacity', 'qr_token', 'status', 'pos_x', 'pos_y', 'is_active'];

    protected $casts = ['status' => TableStatus::class, 'is_active' => 'boolean', 'capacity' => 'integer'];

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function area()
    {
        return $this->belongsTo(Area::class)->withTrashed();
    }

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function openBill()
    {
        return $this->hasOne(Bill::class)
            ->whereIn('status', ['open', 'awaiting_payment'])
            ->whereNull('parent_bill_id')
            ->latestOfMany();
    }
}
