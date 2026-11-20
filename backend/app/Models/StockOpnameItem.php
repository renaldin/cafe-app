<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_opname_id', 'ingredient_id', 'system_qty', 'counted_qty', 'difference'];

    protected $casts = ['system_qty' => 'float', 'counted_qty' => 'float', 'difference' => 'float'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }
}
