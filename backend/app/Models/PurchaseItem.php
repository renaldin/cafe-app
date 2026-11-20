<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_id', 'ingredient_id', 'qty', 'unit_cost', 'subtotal'];

    protected $casts = ['qty' => 'float', 'unit_cost' => 'float', 'subtotal' => 'integer'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }
}
