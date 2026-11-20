<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Services\NumberService;
use App\Services\StockService;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function movements(Request $request)
    {
        $q = StockMovement::query()->with(['ingredient.unit', 'user:id,name'])
            ->when($request->query('ingredient_id'), fn ($q, $i) => $q->where('ingredient_id', $i))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id');
        $p = $q->paginate($this->perPage());
        $p->setCollection($p->getCollection()->map(fn ($m) => [
            'id' => $m->id, 'type' => $m->type, 'qty' => $m->qty, 'stock_after' => $m->stock_after, 'unit_cost' => $m->unit_cost,
            'ingredient' => $m->ingredient?->name, 'unit' => $m->ingredient?->unit?->symbol, 'reason' => $m->reason,
            'user' => $m->user?->name, 'created_at' => $m->created_at?->toIso8601String(),
        ]));

        return $this->paginated($p);
    }

    public function low()
    {
        $items = Ingredient::query()->with('unit')->whereColumn('stock', '<=', 'min_stock')->orderBy('name')->get();

        return $this->ok($items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'stock' => $i->stock, 'min_stock' => $i->min_stock, 'unit' => $i->unit?->symbol]));
    }

    /** Penyesuaian stok (+/-) */
    public function adjust(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'qty' => ['required', 'numeric', 'not_in:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        $ing = Ingredient::findOrFail($data['ingredient_id']);
        $m = $stock->move($ing->id, (float) $data['qty'], 'adjustment', null, null, $data['reason']);
        Activity::log('stock.adjust', $ing, "Penyesuaian stok {$ing->name}", ['stock' => $m->stock_after - $m->qty], ['stock' => $m->stock_after], $data['reason']);

        return $this->ok(['stock' => $m->stock_after], 'Stok disesuaikan.');
    }

    public function waste(Request $request, StockService $stock)
    {
        $data = $request->validate([
            'ingredient_id' => ['required', 'integer', 'exists:ingredients,id'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        $ing = Ingredient::findOrFail($data['ingredient_id']);
        $m = $stock->move($ing->id, -(float) $data['qty'], 'waste', null, null, $data['reason']);
        $stock->checkAutoSoldOut([$ing->id]);
        Activity::log('stock.waste', $ing, "Waste {$ing->name} {$data['qty']}", null, ['stock' => $m->stock_after], $data['reason']);

        return $this->ok(['stock' => $m->stock_after], 'Waste tercatat.');
    }

    // ---- Stock opname ----
    public function opnames(Request $request)
    {
        return $this->paginated(StockOpname::query()->with('user:id,name')->withCount('items')->latest('id')->paginate($this->perPage()));
    }

    public function opname(StockOpname $opname)
    {
        return $this->ok($opname->load(['user:id,name', 'items.ingredient.unit']));
    }

    public function storeOpname(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ingredient_id' => ['required', 'integer', 'exists:ingredients,id', 'distinct'],
            'items.*.counted_qty' => ['required', 'numeric', 'min:0'],
        ]);
        $op = DB::transaction(function () use ($data, $request) {
            $op = StockOpname::create([
                'opname_no' => NumberService::docNo('SO', 'stock_opnames', 'opname_no'),
                'date' => $data['date'], 'note' => $data['note'] ?? null, 'user_id' => $request->user()->id, 'status' => 'draft',
            ]);
            $stocks = Ingredient::whereIn('id', array_column($data['items'], 'ingredient_id'))->pluck('stock', 'id');
            foreach ($data['items'] as $row) {
                $sys = (float) ($stocks[$row['ingredient_id']] ?? 0);
                $op->items()->create(['ingredient_id' => $row['ingredient_id'], 'system_qty' => $sys, 'counted_qty' => $row['counted_qty'], 'difference' => round($row['counted_qty'] - $sys, 3)]);
            }

            return $op;
        });

        return $this->created($op->load('items.ingredient.unit'), 'Draft stock opname disimpan.');
    }

    public function finalizeOpname(StockOpname $opname, StockService $stock)
    {
        if ($opname->status === 'final') {
            throw new BusinessException('Stock opname sudah difinalisasi.');
        }
        DB::transaction(function () use ($opname, $stock) {
            foreach ($opname->items as $item) {
                $stock->setTo($item->ingredient_id, (float) $item->counted_qty, 'opname', 'stock_opname', $opname->id, 'Stock opname '.$opname->opname_no);
            }
            $opname->update(['status' => 'final', 'finalized_at' => now()]);
        });
        $stock->checkAutoSoldOut($opname->items->pluck('ingredient_id')->all());
        Activity::log('stock.opname', $opname, "Finalisasi {$opname->opname_no}");

        return $this->ok($opname->fresh('items.ingredient.unit'), 'Stok diperbarui sesuai hasil opname.');
    }
}
