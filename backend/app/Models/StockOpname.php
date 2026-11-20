<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $fillable = ['opname_no', 'date', 'status', 'note', 'user_id', 'finalized_at'];

    protected $casts = ['date' => 'date', 'finalized_at' => 'datetime'];

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
