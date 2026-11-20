<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HappyHour extends Model
{
    protected $fillable = ['name', 'days', 'start_time', 'end_time', 'type', 'value', 'category_ids', 'is_active'];

    protected $casts = ['days' => 'array', 'category_ids' => 'array', 'is_active' => 'boolean', 'value' => 'integer'];
}
