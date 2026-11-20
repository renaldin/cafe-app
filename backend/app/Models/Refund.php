<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = ['bill_id', 'shift_id', 'amount', 'method', 'reason', 'restock', 'created_by'];

    protected $casts = ['restock' => 'boolean', 'amount' => 'integer'];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
