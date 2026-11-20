<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'points', 'total_spent', 'visit_count', 'last_visit_at', 'note'];

    protected $casts = ['last_visit_at' => 'datetime', 'points' => 'integer', 'total_spent' => 'integer', 'visit_count' => 'integer'];

    public function bills()
    {
        return $this->hasMany(Bill::class);
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }
}
