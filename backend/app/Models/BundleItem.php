<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['bundle_menu_id', 'menu_id', 'qty'];

    public function menu()
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }
}
