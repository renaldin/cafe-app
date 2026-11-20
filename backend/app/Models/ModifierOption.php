<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierOption extends Model
{
    use SoftDeletes;

    protected $fillable = ['modifier_group_id', 'name', 'price_delta', 'is_available', 'is_default', 'sort'];

    protected $casts = ['is_available' => 'boolean', 'is_default' => 'boolean', 'price_delta' => 'integer', 'sort' => 'integer'];

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }

    public function recipes()
    {
        return $this->hasMany(ModifierOptionRecipe::class);
    }
}
