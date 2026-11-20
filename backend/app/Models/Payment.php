<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'bill_id', 'shift_id', 'method', 'amount', 'received', 'change', 'reference', 'proof_image', 'gateway', 'gateway_ref',
        'gateway_payload', 'gateway_qr', 'gateway_url', 'status', 'paid_at', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'method' => PaymentMethod::class, 'gateway_payload' => 'array', 'paid_at' => 'datetime', 'expires_at' => 'datetime',
        'amount' => 'integer', 'received' => 'integer', 'change' => 'integer',
    ];

    protected $hidden = ['gateway_payload'];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
