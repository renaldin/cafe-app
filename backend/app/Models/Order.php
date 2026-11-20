<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['bill_id', 'order_no', 'source', 'status', 'requires_payment', 'created_by', 'confirmed_by', 'client_session', 'ip', 'note', 'confirmed_at'];

    protected $casts = ['status' => OrderStatus::class, 'confirmed_at' => 'datetime', 'order_no' => 'integer', 'requires_payment' => 'boolean'];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
