<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['station_id', 'name', 'slug', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer'];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function menus()
    {
        return $this->hasMany(Menu::class);
    }
}
