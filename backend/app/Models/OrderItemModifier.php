<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemModifier extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_item_id', 'modifier_option_id', 'group_name', 'option_name', 'price_delta'];

    protected $casts = ['price_delta' => 'integer'];
}
