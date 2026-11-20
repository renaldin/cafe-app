<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherUsage extends Model
{
    protected $fillable = ['voucher_id', 'bill_id', 'customer_id', 'amount'];

    protected $casts = ['amount' => 'integer'];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class)->withTrashed();
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
