<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\TableStatus;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TableRequest;
use App\Http\Resources\TableResource;
use App\Models\Table;
use App\Models\TableCall;
use App\Services\BillService;
use App\Support\Activity;
use App\Support\Polling;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $latest = Polling::version(Table::max('updated_at'), \App\Models\Bill::max('updated_at'));
        if (Polling::unchanged($request->query('since'), $latest)) {
            return $this->ok(['changed' => false], null, ['version' => $latest]);
        }
        $tables = Table::query()->with(['area', 'openBill'])->orderBy('area_id')->orderBy('code')->get();

        return $this->ok(TableResource::collection($tables)->resolve($request), null, ['version' => $latest]);
    }

    public function store(TableRequest $request)
    {
        $data = $request->validated();
        $table = Table::create($data + ['name' => $data['name'] ?? 'Meja '.$data['code'], 'qr_token' => Table::generateToken(), 'status' => TableStatus::Empty]);

        return $this->created(TableResource::make($table->load('area')));
    }

    public function update(TableRequest $request, Table $table)
    {
        $data = $request->validated();
        $table->update($data + ['name' => $data['name'] ?? $table->name]);

        return $this->ok(TableResource::make($table->load('area')), 'Meja diperbarui.');
    }

    public function destroy(Table $table)
    {
        if ($table->openBill) {
            throw new BusinessException('Meja masih memiliki bill terbuka.');
        }
        $table->update(['code' => $table->code.'-del'.$table->id]);
        $table->delete();

        return $this->ok(null, 'Meja dihapus.');
    }

    public function regenerate(Table $table)
    {
        $table->update(['qr_token' => Table::generateToken()]);
        Activity::log('table.regenerate_qr', $table, "Regenerate QR meja {$table->code}");

        return $this->ok(TableResource::make($table), 'QR meja diganti. Cetak ulang QR untuk meja ini.');
    }

    public function status(Request $request, Table $table, BillService $bills)
    {
        $data = $request->validate(['status' => ['required', 'in:empty,occupied,needs_cleaning']]);
        if ($data['status'] === 'empty') {
            $bills->markCleaned($table);
        } else {
            $table->update(['status' => $data['status']]);
        }

        return $this->ok(TableResource::make($table->fresh()), 'Status meja diperbarui.');
    }

    public function calls(Request $request)
    {
        $calls = TableCall::query()->with('table:id,code,name')->where('status', 'open')->latest()->limit(50)->get();

        return $this->ok($calls->map(fn ($c) => [
            'id' => $c->id, 'type' => $c->type, 'table' => $c->table?->only(['id', 'code', 'name']),
            'bill_id' => $c->bill_id, 'created_at' => $c->created_at->toIso8601String(),
        ]));
    }

    public function handleCall(Request $request, TableCall $call)
    {
        $call->update(['status' => 'handled', 'handled_by' => $request->user()->id, 'handled_at' => now()]);

        return $this->ok(null, 'Panggilan ditandai selesai.');
    }
}
