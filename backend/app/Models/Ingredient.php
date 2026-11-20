<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use SoftDeletes;

    protected $fillable = ['unit_id', 'name', 'stock', 'min_stock', 'cost_per_unit', 'is_key'];

    protected $casts = ['stock' => 'float', 'min_stock' => 'float', 'cost_per_unit' => 'float', 'is_key' => 'boolean'];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLow(): bool
    {
        return $this->stock <= $this->min_stock;
    }
}
