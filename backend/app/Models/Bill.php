<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    protected $fillable = [
        'invoice_no', 'tracking_key', 'parent_bill_id', 'merged_into_id', 'table_id', 'customer_id', 'shift_id', 'voucher_id',
        'type', 'queue_no', 'customer_name', 'customer_phone', 'delivery_address', 'delivery_fee', 'status',
        'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'voucher_amount', 'promo_amount',
        'points_redeemed', 'points_amount', 'service_charge', 'tax', 'rounding', 'total', 'paid_amount', 'change_amount',
        'cost_total', 'guest_count', 'note', 'bill_requested', 'stock_deducted', 'opened_by', 'closed_by', 'opened_at', 'scheduled_at',
        'paid_at', 'cleaned_at', 'void_reason',
    ];

    protected $casts = [
        'status' => BillStatus::class,
        'type' => OrderType::class,
        'opened_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
        'cleaned_at' => 'datetime',
        'bill_requested' => 'boolean',
        'stock_deducted' => 'boolean',
        'subtotal' => 'integer', 'discount_value' => 'integer', 'discount_amount' => 'integer', 'voucher_amount' => 'integer',
        'promo_amount' => 'integer', 'points_amount' => 'integer', 'service_charge' => 'integer', 'tax' => 'integer',
        'rounding' => 'integer', 'total' => 'integer', 'paid_amount' => 'integer', 'change_amount' => 'integer',
        'delivery_fee' => 'integer', 'cost_total' => 'integer', 'points_redeemed' => 'integer', 'queue_no' => 'integer',
    ];

    public function table()
    {
        return $this->belongsTo(Table::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class)->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function parent()
    {
        return $this->belongsTo(Bill::class, 'parent_bill_id');
    }

    public function children()
    {
        return $this->hasMany(Bill::class, 'parent_bill_id');
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by')->withTrashed();
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function balanceDue(): int
    {
        return max(0, $this->total - $this->paid_amount);
    }

    public function label(): string
    {
        // Catatan: jangan pakai $this->table di dalam model (bentrok dengan properti nama tabel Eloquent)
        $table = $this->table_id ? $this->getRelationValue('table') : null;
        if ($table) {
            return 'Meja '.$table->code;
        }

        return 'Antrean #'.str_pad((string) $this->queue_no, 3, '0', STR_PAD_LEFT);
    }
}
