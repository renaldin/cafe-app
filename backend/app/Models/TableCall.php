<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableCall extends Model
{
    protected $fillable = ['table_id', 'bill_id', 'type', 'status', 'handled_by', 'handled_at'];

    protected $casts = ['handled_at' => 'datetime'];

    public function table()
    {
        return $this->belongsTo(Table::class)->withTrashed();
    }
}
