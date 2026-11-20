<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exports\MenuExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\MenuRequest;
use App\Http\Resources\MenuResource;
use App\Imports\MenuImport;
use App\Models\Menu;
use App\Models\Recipe;
use App\Services\ImageService;
use App\Services\MenuCatalog;
use App\Services\RecipeCostService;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $q = Menu::query()->with(['category:id,name'])
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('sku', 'like', "%$s%")))
            ->when($request->query('category_id'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->has('available'), fn ($q) => $q->where('is_available', $request->boolean('available')))
            ->orderBy('category_id')->orderBy('sort')->orderBy('name');

        if ($request->boolean('all')) {
            return $this->ok(MenuResource::collection($q->with('modifierGroups.options')->get())->resolve($request));
        }

        return $this->paginated($q->paginate($this->perPage()), MenuResource::class);
    }

    public function show(Menu $menu)
    {
        $menu->load(['category', 'modifierGroups.options', 'recipes.ingredient.unit', 'bundleItems.menu']);

        return $this->ok(MenuResource::make($menu));
    }

    public function store(MenuRequest $request, RecipeCostService $cost)
    {
        $menu = DB::transaction(function () use ($request, $cost) {
            $data = $request->validated();
            $menu = Menu::create($this->payload($data) + ['slug' => $this->slug($data['name'])]);
            $this->syncRelations($menu, $data);
            $cost->refreshMenuCost($menu);

            return $menu;
        });
        MenuCatalog::bump();
        Activity::log('menu.create', $menu, "Tambah menu {$menu->name}", null, ['price' => $menu->price]);

        return $this->created(MenuResource::make($menu->load(['category', 'modifierGroups.options'])));
    }

    public function update(MenuRequest $request, Menu $menu, RecipeCostService $cost)
    {
        $before = $menu->only(['name', 'price', 'is_available', 'category_id']);
        DB::transaction(function () use ($request, $menu, $cost) {
            $data = $request->validated();
            $payload = $this->payload($data);
            if ($data['name'] !== $menu->name) {
                $payload['slug'] = $this->slug($data['name'], $menu->id);
            }
            $menu->update($payload);
            $this->syncRelations($menu, $data);
            $cost->refreshMenuCost($menu);
        });
        MenuCatalog::bump();
        $after = $menu->only(['name', 'price', 'is_available', 'category_id']);
        if ($before['price'] !== $after['price']) {
            Activity::log('menu.price_change', $menu, "Ubah harga {$menu->name}", ['price' => $before['price']], ['price' => $after['price']]);
        }
        Activity::log('menu.update', $menu, "Ubah menu {$menu->name}", $before, $after);

        return $this->ok(MenuResource::make($menu->load(['category', 'modifierGroups.options'])), 'Menu diperbarui.');
    }

    public function destroy(Menu $menu, ImageService $images)
    {
        $menu->update(['slug' => $menu->slug.'-del-'.$menu->id, 'sku' => null]);
        $menu->delete();
        MenuCatalog::bump();
        Activity::log('menu.delete', $menu, "Hapus menu {$menu->name}");

        return $this->ok(null, 'Menu dihapus.');
    }

    public function availability(Request $request, Menu $menu)
    {
        $data = $request->validate(['is_available' => ['required', 'boolean']]);
        $menu->update(['is_available' => $data['is_available']]);
        MenuCatalog::bump();
        Activity::log('menu.availability', $menu, ($data['is_available'] ? 'Tersedia: ' : 'Habis: ').$menu->name);

        return $this->ok(MenuResource::make($menu), $data['is_available'] ? 'Menu tersedia kembali.' : 'Menu ditandai habis.');
    }

    public function image(Request $request, Menu $menu, ImageService $images)
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']]);
        $old = $menu->image;
        $path = $images->store($request->file('image'), 'menus');
        $menu->update(['image' => $path]);
        $images->delete($old);
        MenuCatalog::bump();

        return $this->ok(['image_url' => ImageService::url($path)], 'Foto menu diperbarui.');
    }

    public function recipe(Request $request, Menu $menu, RecipeCostService $cost)
    {
        $data = $request->validate([
            'items' => ['present', 'array'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ]);
        DB::transaction(function () use ($menu, $data) {
            Recipe::where('menu_id', $menu->id)->delete();
            foreach ($data['items'] as $row) {
                Recipe::create(['menu_id' => $menu->id, 'ingredient_id' => $row['ingredient_id'], 'qty' => $row['qty']]);
            }
        });
        $hpp = $cost->refreshMenuCost($menu->fresh());
        Activity::log('recipe.update', $menu, "Ubah resep {$menu->name}", null, ['items' => $data['items'], 'cost' => $hpp]);

        return $this->ok(['cost' => $hpp, 'margin' => $menu->price - $hpp], 'Resep disimpan.');
    }

    public function export()
    {
        return Excel::download(new MenuExport, 'menu-'.now()->format('Ymd').'.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);
        $import = new MenuImport;
        Excel::import($import, $request->file('file'));
        MenuCatalog::bump();
        Activity::log('menu.import', null, "Import menu: {$import->created} baru, {$import->updated} diperbarui");

        return $this->ok(['created' => $import->created, 'updated' => $import->updated, 'errors' => $import->errors], "Import selesai: {$import->created} menu baru, {$import->updated} diperbarui.");
    }

    private function payload(array $data): array
    {
        return [
            'category_id' => $data['category_id'],
            'station_id' => $data['station_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'labels' => $data['labels'] ?? [],
            'is_available' => $data['is_available'] ?? true,
            'is_active' => $data['is_active'] ?? true,
            'is_bundle' => $data['is_bundle'] ?? false,
            'available_from' => $data['available_from'] ?? null,
            'available_until' => $data['available_until'] ?? null,
            'sort' => $data['sort'] ?? 0,
        ];
    }

    private function syncRelations(Menu $menu, array $data): void
    {
        if (array_key_exists('modifier_group_ids', $data)) {
            $sync = [];
            foreach (array_values($data['modifier_group_ids'] ?? []) as $i => $gid) {
                $sync[$gid] = ['sort' => $i];
            }
            $menu->modifierGroups()->sync($sync);
        }
        if (array_key_exists('bundle_items', $data)) {
            $menu->bundleItems()->delete();
            if ($menu->is_bundle) {
                foreach ($data['bundle_items'] ?? [] as $b) {
                    if ((int) $b['menu_id'] !== $menu->id) {
                        $menu->bundleItems()->create(['menu_id' => $b['menu_id'], 'qty' => $b['qty']]);
                    }
                }
            }
        }
    }

    private function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'menu';
        $slug = $base;
        $i = 2;
        while (Menu::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
