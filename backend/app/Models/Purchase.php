<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['purchase_no', 'supplier_id', 'date', 'total', 'note', 'user_id'];

    protected $casts = ['date' => 'date', 'total' => 'integer'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
