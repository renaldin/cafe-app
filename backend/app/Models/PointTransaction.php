<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = ['customer_id', 'bill_id', 'points', 'type', 'note'];

    protected $casts = ['points' => 'integer'];
}
