<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = ['user_id', 'opened_at', 'closed_at', 'opening_cash', 'cash_sales', 'non_cash_sales', 'cash_refunds', 'expected_cash', 'counted_cash', 'difference', 'note', 'status'];

    protected $casts = [
        'opened_at' => 'datetime', 'closed_at' => 'datetime',
        'opening_cash' => 'integer', 'cash_sales' => 'integer', 'non_cash_sales' => 'integer', 'cash_refunds' => 'integer',
        'expected_cash' => 'integer', 'counted_cash' => 'integer', 'difference' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}
