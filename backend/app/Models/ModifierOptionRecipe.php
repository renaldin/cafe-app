<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierOptionRecipe extends Model
{
    public $timestamps = false;

    protected $fillable = ['modifier_option_id', 'ingredient_id', 'qty'];

    protected $casts = ['qty' => 'float'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }
}
