<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Ingredient;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Table;
use App\Support\SqlFn;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $finance = $user->can('dashboard.finance');
        $today = [now()->startOfDay(), now()->endOfDay()];
        $paid = Bill::query()->whereIn('status', ['paid', 'refunded'])->whereBetween('paid_at', $today);
        $omzet = (int) (clone $paid)->sum('total');
        $count = (clone $paid)->count();
        $yesterday = (int) Bill::query()->whereIn('status', ['paid', 'refunded'])->whereBetween('paid_at', [now()->subDay()->startOfDay(), now()->subDay()])->sum('total');

        $tables = Table::query()->where('is_active', true)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $activeBills = Bill::query()->whereIn('status', ['open', 'awaiting_payment'])->count();
        $activeItems = OrderItem::query()->whereIn('status', ['new', 'processing'])->whereHas('order', fn ($q) => $q->where('status', 'confirmed'))->count();
        $pendingConfirm = \App\Models\Order::where('status', 'pending_confirmation')->count();

        $data = [
            'orders_today' => $count,
            'active_bills' => $activeBills,
            'active_items' => $activeItems,
            'pending_confirmation' => $pendingConfirm,
            'tables' => [
                'total' => (int) $tables->sum(),
                'empty' => (int) ($tables['empty'] ?? 0),
                'occupied' => (int) (($tables['occupied'] ?? 0) + ($tables['awaiting_payment'] ?? 0)),
                'needs_cleaning' => (int) ($tables['needs_cleaning'] ?? 0),
            ],
            'low_stock' => $user->can('stock.view')
                ? Ingredient::query()->with('unit')->whereColumn('stock', '<=', 'min_stock')->orderBy('stock')->limit(10)->get()
                    ->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'stock' => $i->stock, 'min_stock' => $i->min_stock, 'unit' => $i->unit?->symbol])
                : [],
        ];
        if ($finance) {
            $data += [
                'revenue_today' => $omzet,
                'revenue_yesterday' => $yesterday,
                'avg_order' => $count ? (int) round($omzet / $count) : 0,
            ];
        }

        return $this->ok($data, null, ['finance' => $finance]);
    }

    public function charts(Request $request)
    {
        abort_unless($request->user()->can('dashboard.finance'), 403);
        $days = $request->query('range') === '30d' ? 30 : 7;
        $from = now()->subDays($days - 1)->startOfDay();
        $paid = fn () => Bill::query()->whereIn('status', ['paid', 'refunded'])->where('paid_at', '>=', $from);

        $d = SqlFn::date('paid_at');
        $daily = $paid()->selectRaw("$d as day, SUM(total) as total, COUNT(*) as bills")->groupBy('day')->pluck('total', 'day');
        $dailySeries = collect(range(0, $days - 1))->map(function ($i) use ($from, $daily) {
            $day = $from->copy()->addDays($i)->toDateString();

            return ['date' => $day, 'total' => (int) ($daily[$day] ?? 0)];
        });

        $h = SqlFn::hour('opened_at');
        $hourly = $paid()->selectRaw("$h as hour, COUNT(*) as bills, SUM(total) as total")->groupBy('hour')->get()->keyBy('hour');
        $hourlySeries = collect(range(6, 23))->map(fn ($i) => ['hour' => sprintf('%02d', $i), 'bills' => (int) ($hourly[$i]->bills ?? 0), 'total' => (int) ($hourly[$i]->total ?? 0)]);

        $top = OrderItem::query()->join('bills', 'bills.id', '=', 'order_items.bill_id')
            ->whereIn('bills.status', ['paid', 'refunded'])->where('bills.paid_at', '>=', $from)->where('order_items.status', '!=', 'cancelled')
            ->selectRaw('order_items.menu_name as name, SUM(order_items.qty) as qty, SUM(order_items.line_total) as total')
            ->groupBy('order_items.menu_name')->orderByDesc('qty')->limit(8)->get();

        $cats = OrderItem::query()->join('bills', 'bills.id', '=', 'order_items.bill_id')
            ->leftJoin('menus', 'menus.id', '=', 'order_items.menu_id')->leftJoin('categories', 'categories.id', '=', 'menus.category_id')
            ->whereIn('bills.status', ['paid', 'refunded'])->where('bills.paid_at', '>=', $from)->where('order_items.status', '!=', 'cancelled')
            ->selectRaw("COALESCE(categories.name, 'Lainnya') as name, SUM(order_items.line_total) as total")
            ->groupBy('categories.name')->orderByDesc('total')->get();

        $labels = ['cash' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'card' => 'Kartu', 'gateway' => 'Online'];
        $methods = Payment::query()->where('status', 'paid')->where('paid_at', '>=', $from)
            ->selectRaw('method, SUM(amount) as total')->groupBy('method')->get()
            ->map(fn ($r) => ['name' => $labels[$r->method->value] ?? $r->method->value, 'total' => (int) $r->total]);

        return $this->ok([
            'daily' => $dailySeries,
            'hourly' => $hourlySeries,
            'top_menus' => $top->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'total' => (int) $r->total]),
            'categories' => $cats->map(fn ($r) => ['name' => $r->name, 'total' => (int) $r->total]),
            'payment_methods' => $methods,
        ]);
    }
}
