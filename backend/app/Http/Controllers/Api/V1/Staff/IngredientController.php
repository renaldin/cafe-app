<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\IngredientRequest;
use App\Http\Resources\IngredientResource;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Unit;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngredientController extends Controller
{
    public function index(Request $request)
    {
        $q = Ingredient::query()->with('unit')
            ->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->when($request->boolean('low'), fn ($q) => $q->whereColumn('stock', '<=', 'min_stock'))
            ->orderBy('name');
        if ($request->boolean('all')) {
            return $this->ok(IngredientResource::collection($q->get())->resolve($request));
        }

        return $this->paginated($q->paginate($this->perPage()), IngredientResource::class);
    }

    public function units()
    {
        return $this->ok(Unit::orderBy('name')->get(['id', 'name', 'symbol']));
    }

    public function store(IngredientRequest $request, StockService $stock)
    {
        $data = $request->validated();
        $ing = DB::transaction(function () use ($data, $stock) {
            $ing = Ingredient::create([
                'name' => $data['name'], 'unit_id' => $data['unit_id'], 'min_stock' => $data['min_stock'] ?? 0,
                'cost_per_unit' => $data['cost_per_unit'] ?? 0, 'is_key' => $data['is_key'] ?? false, 'stock' => 0,
            ]);
            if (! empty($data['initial_stock'])) {
                $stock->move($ing->id, (float) $data['initial_stock'], 'adjustment', null, null, 'Stok awal', (float) ($data['cost_per_unit'] ?? 0));
            }

            return $ing;
        });

        return $this->created(IngredientResource::make($ing->fresh('unit')));
    }

    public function update(IngredientRequest $request, Ingredient $ingredient)
    {
        $data = $request->validated();
        $ingredient->update([
            'name' => $data['name'], 'unit_id' => $data['unit_id'], 'min_stock' => $data['min_stock'] ?? 0,
            'cost_per_unit' => $data['cost_per_unit'] ?? $ingredient->cost_per_unit, 'is_key' => $data['is_key'] ?? false,
        ]);

        return $this->ok(IngredientResource::make($ingredient->fresh('unit')), 'Bahan diperbarui.');
    }

    public function destroy(Ingredient $ingredient)
    {
        if (Recipe::where('ingredient_id', $ingredient->id)->exists()) {
            throw new BusinessException('Bahan masih dipakai di resep menu.');
        }
        $ingredient->delete();

        return $this->ok(null, 'Bahan dihapus.');
    }
}
