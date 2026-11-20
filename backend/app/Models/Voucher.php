<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'type', 'value', 'max_discount', 'min_spend', 'starts_at', 'ends_at', 'usage_limit', 'used_count', 'per_customer_limit', 'is_active'];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean',
        'value' => 'integer', 'max_discount' => 'integer', 'min_spend' => 'integer',
        'usage_limit' => 'integer', 'used_count' => 'integer', 'per_customer_limit' => 'integer',
    ];

    public function usages()
    {
        return $this->hasMany(VoucherUsage::class);
    }
}
