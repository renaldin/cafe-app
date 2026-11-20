<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function current(Request $request, ShiftService $shifts)
    {
        $shift = $request->user()->openShift();

        return $this->ok($shift ? $this->present($shift) + ['summary' => $shifts->summary($shift)] : null);
    }

    public function open(Request $request, ShiftService $shifts)
    {
        $data = $request->validate(['opening_cash' => ['required', 'integer', 'min:0', 'max:1000000000'], 'note' => ['nullable', 'string', 'max:255']]);
        $shift = $shifts->open($request->user(), $data['opening_cash'], $data['note'] ?? null);

        return $this->created($this->present($shift) + ['summary' => $shifts->summary($shift)], 'Shift dibuka. Selamat bekerja!');
    }

    public function close(Request $request, Shift $shift, ShiftService $shifts)
    {
        $user = $request->user();
        abort_unless($shift->user_id === $user->id || $user->can('shift.view_all'), 403);
        $data = $request->validate(['counted_cash' => ['required', 'integer', 'min:0', 'max:1000000000'], 'note' => ['nullable', 'string', 'max:255']]);
        $shift = $shifts->close($shift, $user, $data['counted_cash'], $data['note'] ?? null);

        return $this->ok($this->present($shift), 'Shift ditutup.');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $q = Shift::query()->with('user:id,name')
            ->when(! $user->can('shift.view_all'), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('opened_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('opened_at', '<=', $d))
            ->latest('id');
        $p = $q->paginate($this->perPage());
        $p->setCollection($p->getCollection()->map(fn ($s) => $this->present($s)));

        return $this->paginated($p);
    }

    public function show(Request $request, Shift $shift, ShiftService $shifts)
    {
        $user = $request->user();
        abort_unless($shift->user_id === $user->id || $user->can('shift.view_all'), 403);

        return $this->ok($this->present($shift) + ['summary' => $shifts->summary($shift)]);
    }

    private function present(Shift $s): array
    {
        return [
            'id' => $s->id,
            'user' => $s->user?->only(['id', 'name']),
            'status' => $s->status,
            'opened_at' => $s->opened_at?->toIso8601String(),
            'closed_at' => $s->closed_at?->toIso8601String(),
            'opening_cash' => $s->opening_cash,
            'cash_sales' => $s->cash_sales,
            'non_cash_sales' => $s->non_cash_sales,
            'cash_refunds' => $s->cash_refunds,
            'expected_cash' => $s->expected_cash,
            'counted_cash' => $s->counted_cash,
            'difference' => $s->difference,
            'note' => $s->note,
        ];
    }
}
