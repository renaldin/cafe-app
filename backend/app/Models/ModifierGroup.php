<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'selection', 'is_required', 'min_select', 'max_select', 'sort'];

    protected $casts = ['is_required' => 'boolean', 'min_select' => 'integer', 'max_select' => 'integer', 'sort' => 'integer'];

    public function options()
    {
        return $this->hasMany(ModifierOption::class)->orderBy('sort');
    }

    public function menus()
    {
        return $this->belongsToMany(Menu::class, 'menu_modifier_group');
    }
}
