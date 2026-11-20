<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseRequest;
use App\Models\Purchase;
use App\Services\NumberService;
use App\Services\RecipeCostService;
use App\Services\StockService;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $q = Purchase::query()->with(['supplier:id,name', 'user:id,name'])->withCount('items')
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->latest('date')->latest('id');

        return $this->paginated($q->paginate($this->perPage()));
    }

    public function show(Purchase $purchase)
    {
        return $this->ok($purchase->load(['supplier', 'user:id,name', 'items.ingredient.unit']));
    }

    public function store(PurchaseRequest $request, StockService $stock, RecipeCostService $cost)
    {
        $data = $request->validated();
        $purchase = DB::transaction(function () use ($data, $stock, $request) {
            $purchase = Purchase::create([
                'purchase_no' => NumberService::docNo('PB', 'purchases', 'purchase_no'),
                'supplier_id' => $data['supplier_id'] ?? null,
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
            ]);
            $total = 0;
            foreach ($data['items'] as $row) {
                $sub = (int) round($row['qty'] * $row['unit_cost']);
                $purchase->items()->create(['ingredient_id' => $row['ingredient_id'], 'qty' => $row['qty'], 'unit_cost' => $row['unit_cost'], 'subtotal' => $sub]);
                $stock->move((int) $row['ingredient_id'], (float) $row['qty'], 'purchase', 'purchase', $purchase->id, 'Pembelian '.$purchase->purchase_no, (float) $row['unit_cost']);
                $total += $sub;
            }
            $purchase->update(['total' => $total]);

            return $purchase;
        });
        // HPP menu diperbarui karena harga rata-rata bahan berubah
        \App\Models\Menu::query()->whereHas('recipes', fn ($q) => $q->whereIn('ingredient_id', array_column($data['items'], 'ingredient_id')))
            ->get()->each(fn ($m) => $cost->refreshMenuCost($m));
        Activity::log('stock.purchase', $purchase, "Pembelian {$purchase->purchase_no}", null, ['total' => $purchase->total]);

        return $this->created($purchase->load('items.ingredient'), 'Pembelian tersimpan, stok bertambah.');
    }
}
