<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function open(User $user, int $openingCash, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($user, $openingCash, $note) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            if ($user->openShift()) {
                throw new BusinessException('Anda masih memiliki shift yang aktif.');
            }
            $shift = Shift::create([
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_cash' => $openingCash,
                'note' => $note,
                'status' => 'open',
            ]);
            Activity::log('shift.open', $shift, 'Buka shift', null, ['opening_cash' => $openingCash], null, $user->id);

            return $shift;
        });
    }

    public function summary(Shift $shift): array
    {
        $byMethod = Payment::query()->where('shift_id', $shift->id)->where('status', 'paid')
            ->selectRaw('method, SUM(amount) as total, COUNT(*) as count')->groupBy('method')->get()
            ->mapWithKeys(fn ($r) => [$r->method->value => ['total' => (int) $r->total, 'count' => (int) $r->count]]);
        $cashSales = (int) ($byMethod['cash']['total'] ?? 0);
        $nonCash = (int) $byMethod->except('cash')->sum('total');
        $cashRefunds = (int) Refund::where('shift_id', $shift->id)->where('method', 'cash')->sum('amount');

        return [
            'by_method' => $byMethod,
            'cash_sales' => $cashSales,
            'non_cash_sales' => $nonCash,
            'cash_refunds' => $cashRefunds,
            'expected_cash' => $shift->opening_cash + $cashSales - $cashRefunds,
            'bill_count' => \App\Models\Bill::where('shift_id', $shift->id)->where('status', 'paid')->count(),
        ];
    }

    public function close(Shift $shift, User $user, int $counted, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($shift, $user, $counted, $note) {
            $shift = Shift::query()->whereKey($shift->id)->lockForUpdate()->firstOrFail();
            if ($shift->status !== 'open') {
                throw new BusinessException('Shift sudah ditutup.');
            }
            $s = $this->summary($shift);
            $shift->update([
                'closed_at' => now(),
                'cash_sales' => $s['cash_sales'],
                'non_cash_sales' => $s['non_cash_sales'],
                'cash_refunds' => $s['cash_refunds'],
                'expected_cash' => $s['expected_cash'],
                'counted_cash' => $counted,
                'difference' => $counted - $s['expected_cash'],
                'note' => trim(($shift->note ? $shift->note."\n" : '').($note ?? '')) ?: null,
                'status' => 'closed',
            ]);
            Activity::log('shift.close', $shift, 'Tutup shift', null, ['expected' => $s['expected_cash'], 'counted' => $counted, 'difference' => $counted - $s['expected_cash']], null, $user->id);

            return $shift;
        });
    }
}
