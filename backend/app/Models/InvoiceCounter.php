<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceCounter extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'date';

    protected $keyType = 'string';

    protected $fillable = ['date', 'last_invoice', 'last_queue', 'last_order'];
}
