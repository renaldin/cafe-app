<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'station_id', 'sku', 'name', 'slug', 'description', 'image', 'price', 'cost_cache',
        'labels', 'is_available', 'is_active', 'is_bundle', 'available_from', 'available_until', 'sort',
    ];

    protected $casts = [
        'labels' => 'array',
        'is_available' => 'boolean',
        'is_active' => 'boolean',
        'is_bundle' => 'boolean',
        'price' => 'integer',
        'cost_cache' => 'integer',
        'sort' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(ModifierGroup::class, 'menu_modifier_group')->withPivot('sort')->orderByPivot('sort');
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function bundleItems()
    {
        return $this->hasMany(BundleItem::class, 'bundle_menu_id');
    }

    /** Stasiun efektif: override di menu, atau ikut kategori */
    public function effectiveStationId(): ?int
    {
        return $this->station_id ?? $this->category?->station_id;
    }

    /** Apakah menu bisa dipesan pada waktu tertentu */
    public function isOrderableAt(?Carbon $at = null): bool
    {
        if (! $this->is_active || ! $this->is_available) {
            return false;
        }
        if ($this->available_from && $this->available_until) {
            $now = ($at ?? now())->format('H:i:s');
            $from = strlen($this->available_from) === 5 ? $this->available_from.':00' : $this->available_from;
            $until = strlen($this->available_until) === 5 ? $this->available_until.':59' : $this->available_until;
            if ($from <= $until) {
                return $now >= $from && $now <= $until;
            }

            return $now >= $from || $now <= $until; // melewati tengah malam
        }

        return true;
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset($this->image) : null;
    }
}
