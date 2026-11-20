<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'bill_id', 'menu_id', 'station_id', 'menu_name', 'unit_price', 'modifiers_total', 'qty', 'line_total',
        'cost_snapshot', 'note', 'status', 'stock_deducted', 'started_at', 'ready_at', 'served_at', 'cancel_reason', 'cancelled_by',
    ];

    protected $casts = [
        'status' => OrderItemStatus::class,
        'stock_deducted' => 'boolean',
        'started_at' => 'datetime', 'ready_at' => 'datetime', 'served_at' => 'datetime',
        'unit_price' => 'integer', 'modifiers_total' => 'integer', 'qty' => 'integer', 'line_total' => 'integer', 'cost_snapshot' => 'integer',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function modifiers()
    {
        return $this->hasMany(OrderItemModifier::class);
    }
}
