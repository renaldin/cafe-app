<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    public $timestamps = false;

    protected $fillable = ['menu_id', 'ingredient_id', 'qty'];

    protected $casts = ['qty' => 'float'];

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class)->withTrashed();
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }
}
