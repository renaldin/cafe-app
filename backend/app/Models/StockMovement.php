<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public $timestamps = false;

    protected $fillable = ['ingredient_id', 'type', 'qty', 'stock_after', 'unit_cost', 'reference_type', 'reference_id', 'reason', 'user_id', 'created_at'];

    protected $casts = ['qty' => 'float', 'stock_after' => 'float', 'unit_cost' => 'float', 'created_at' => 'datetime'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
