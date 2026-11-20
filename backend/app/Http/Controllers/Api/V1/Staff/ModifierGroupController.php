<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModifierGroupRequest;
use App\Http\Resources\ModifierGroupResource;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOptionRecipe;
use App\Services\MenuCatalog;
use App\Services\RecipeCostService;
use Illuminate\Support\Facades\DB;

class ModifierGroupController extends Controller
{
    public function index()
    {
        $groups = ModifierGroup::query()->with(['options.recipes.ingredient'])->withCount('menus')->orderBy('sort')->orderBy('name')->get();

        return $this->ok(ModifierGroupResource::collection($groups)->resolve(request()));
    }

    public function store(ModifierGroupRequest $request)
    {
        $group = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $group = ModifierGroup::create($this->payload($data));
            $this->syncOptions($group, $data['options']);

            return $group;
        });
        MenuCatalog::bump();

        return $this->created(ModifierGroupResource::make($group->load('options.recipes.ingredient')));
    }

    public function update(ModifierGroupRequest $request, ModifierGroup $modifierGroup, RecipeCostService $cost)
    {
        DB::transaction(function () use ($request, $modifierGroup) {
            $data = $request->validated();
            $modifierGroup->update($this->payload($data));
            $this->syncOptions($modifierGroup, $data['options']);
        });
        MenuCatalog::bump();

        return $this->ok(ModifierGroupResource::make($modifierGroup->load('options.recipes.ingredient')), 'Grup opsi diperbarui.');
    }

    public function destroy(ModifierGroup $modifierGroup)
    {
        $modifierGroup->menus()->detach();
        $modifierGroup->options()->delete();
        $modifierGroup->delete();
        MenuCatalog::bump();

        return $this->ok(null, 'Grup opsi dihapus.');
    }

    private function payload(array $d): array
    {
        $single = $d['selection'] === 'single';

        return [
            'name' => $d['name'],
            'selection' => $d['selection'],
            'is_required' => $d['is_required'] ?? false,
            'min_select' => ($d['is_required'] ?? false) ? max(1, (int) ($d['min_select'] ?? 1)) : (int) ($d['min_select'] ?? 0),
            'max_select' => $single ? 1 : (int) ($d['max_select'] ?? count($d['options'])),
            'sort' => $d['sort'] ?? 0,
        ];
    }

    private function syncOptions(ModifierGroup $group, array $options): void
    {
        $keep = [];
        foreach (array_values($options) as $i => $o) {
            $attrs = [
                'name' => $o['name'],
                'price_delta' => (int) $o['price_delta'],
                'is_available' => $o['is_available'] ?? true,
                'is_default' => $o['is_default'] ?? false,
                'sort' => $i,
            ];
            $opt = ! empty($o['id']) ? $group->options()->whereKey($o['id'])->first() : null;
            $opt ? $opt->update($attrs) : $opt = $group->options()->create($attrs);
            $keep[] = $opt->id;
            if (array_key_exists('recipes', $o)) {
                ModifierOptionRecipe::where('modifier_option_id', $opt->id)->delete();
                foreach ($o['recipes'] ?? [] as $r) {
                    ModifierOptionRecipe::create(['modifier_option_id' => $opt->id, 'ingredient_id' => $r['ingredient_id'], 'qty' => $r['qty']]);
                }
            }
        }
        $group->options()->whereNotIn('id', $keep)->delete();
    }
}
