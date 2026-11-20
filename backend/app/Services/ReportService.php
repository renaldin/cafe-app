<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Ingredient;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Support\SqlFn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Semua laporan mengembalikan struktur seragam:
 * ['title','columns'=>[[key,label,type]], 'rows'=>[], 'summary'=>[[label,value,type]], 'chart'=>?]
 */
class ReportService
{
    public const TYPES = [
        'sales' => ['Penjualan', 'report.view'],
        'menus' => ['Penjualan per Menu', 'report.view'],
        'categories' => ['Penjualan per Kategori', 'report.view'],
        'cashiers' => ['Penjualan per Kasir', 'report.view'],
        'payment-methods' => ['Metode Pembayaran', 'report.view'],
        'order-types' => ['Tipe Pesanan', 'report.view'],
        'peak-hours' => ['Jam Sibuk', 'report.view'],
        'tables' => ['Kinerja Meja', 'report.view'],
        'serving-time' => ['Waktu Penyajian', 'report.view'],
        'profit-loss' => ['Laba Rugi', 'report.finance'],
        'stock' => ['Stok & Nilai Persediaan', 'report.view'],
        'ingredient-usage' => ['Pemakaian Bahan', 'report.view'],
        'waste' => ['Waste / Bahan Rusak', 'report.view'],
        'discounts' => ['Diskon & Voucher', 'report.view'],
        'voids' => ['Void & Refund', 'report.view'],
        'shifts' => ['Shift Kasir', 'report.view'],
    ];

    private Carbon $from;

    private Carbon $to;

    public function run(string $type, string $from, string $to, array $opts = []): array
    {
        $this->from = Carbon::parse($from)->startOfDay();
        $this->to = Carbon::parse($to)->endOfDay();
        $method = 'r'.str_replace(' ', '', ucwords(str_replace('-', ' ', $type)));
        $result = $this->{$method}($opts);

        return $result + [
            'type' => $type,
            'title' => self::TYPES[$type][0],
            'period' => ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()],
            'summary' => [],
        ];
    }

    private function paidBills()
    {
        return Bill::query()->whereIn('bills.status', ['paid', 'refunded'])->whereBetween('bills.paid_at', [$this->from, $this->to]);
    }

    private function soldItems()
    {
        return OrderItem::query()->join('bills', 'bills.id', '=', 'order_items.bill_id')
            ->whereIn('bills.status', ['paid', 'refunded'])->whereBetween('bills.paid_at', [$this->from, $this->to])
            ->where('order_items.status', '!=', 'cancelled');
    }

    private function rSales(array $o): array
    {
        $group = $o['group'] ?? 'day';
        $expr = match ($group) {
            'week' => SqlFn::week('bills.paid_at'),
            'month' => SqlFn::month('bills.paid_at'),
            default => SqlFn::date('bills.paid_at'),
        };
        $rows = $this->paidBills()->selectRaw("$expr as period, COUNT(*) as bills, SUM(subtotal) as subtotal,
                SUM(discount_amount + voucher_amount + promo_amount + points_amount) as discounts,
                SUM(service_charge) as service, SUM(tax) as tax, SUM(total) as total, SUM(cost_total) as cost")
            ->groupBy('period')->orderBy('period')->get()
            ->map(fn ($r) => [
                'period' => $r->period, 'bills' => (int) $r->bills, 'subtotal' => (int) $r->subtotal, 'discounts' => (int) $r->discounts,
                'service' => (int) $r->service, 'tax' => (int) $r->tax, 'total' => (int) $r->total,
                'avg' => $r->bills ? (int) round($r->total / $r->bills) : 0,
            ]);
        $refunds = (int) Refund::whereBetween('created_at', [$this->from, $this->to])->sum('amount');
        $total = (int) $rows->sum('total');

        return [
            'columns' => [
                ['period', 'Periode', 'text'], ['bills', 'Transaksi', 'number'], ['subtotal', 'Subtotal', 'money'],
                ['discounts', 'Diskon', 'money'], ['service', 'Service', 'money'], ['tax', 'Pajak', 'money'],
                ['total', 'Total', 'money'], ['avg', 'Rata-rata', 'money'],
            ],
            'rows' => $rows->values(),
            'summary' => [
                ['Total penjualan', $total, 'money'],
                ['Jumlah transaksi', (int) $rows->sum('bills'), 'number'],
                ['Rata-rata per transaksi', $rows->sum('bills') ? (int) round($total / $rows->sum('bills')) : 0, 'money'],
                ['Refund', $refunds, 'money'],
                ['Penjualan bersih', $total - $refunds, 'money'],
            ],
            'chart' => ['x' => 'period', 'y' => 'total', 'kind' => 'bar'],
        ];
    }

    private function rMenus(array $o): array
    {
        $rows = $this->soldItems()->selectRaw('order_items.menu_id, order_items.menu_name as name, SUM(order_items.qty) as qty,
                SUM(order_items.line_total) as revenue, SUM(order_items.cost_snapshot * order_items.qty) as cost')
            ->groupBy('order_items.menu_id', 'order_items.menu_name')->orderByDesc('qty')->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (int) $r->revenue, 'cost' => (int) $r->cost, 'profit' => (int) ($r->revenue - $r->cost)]);

        return [
            'columns' => [['name', 'Menu', 'text'], ['qty', 'Terjual', 'number'], ['revenue', 'Penjualan', 'money'], ['cost', 'HPP', 'money'], ['profit', 'Laba Kotor', 'money']],
            'rows' => $rows->values(),
            'summary' => [['Total item terjual', (int) $rows->sum('qty'), 'number'], ['Total penjualan', (int) $rows->sum('revenue'), 'money']],
            'chart' => ['x' => 'name', 'y' => 'qty', 'kind' => 'bar', 'limit' => 10],
        ];
    }

    private function rCategories(array $o): array
    {
        $rows = $this->soldItems()->leftJoin('menus', 'menus.id', '=', 'order_items.menu_id')->leftJoin('categories', 'categories.id', '=', 'menus.category_id')
            ->selectRaw("COALESCE(categories.name, 'Lainnya') as name, SUM(order_items.qty) as qty, SUM(order_items.line_total) as revenue")
            ->groupBy('categories.name')->orderByDesc('revenue')->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (int) $r->revenue]);

        return [
            'columns' => [['name', 'Kategori', 'text'], ['qty', 'Terjual', 'number'], ['revenue', 'Penjualan', 'money']],
            'rows' => $rows->values(),
            'chart' => ['x' => 'name', 'y' => 'revenue', 'kind' => 'pie'],
        ];
    }

    private function rCashiers(array $o): array
    {
        $rows = $this->paidBills()->leftJoin('users', 'users.id', '=', 'bills.closed_by')
            ->selectRaw("COALESCE(users.name, 'Online/Sistem') as name, COUNT(*) as bills, SUM(bills.total) as total")
            ->groupBy('users.name')->orderByDesc('total')->get()
            ->map(fn ($r) => ['name' => $r->name, 'bills' => (int) $r->bills, 'total' => (int) $r->total]);

        return [
            'columns' => [['name', 'Kasir', 'text'], ['bills', 'Transaksi', 'number'], ['total', 'Total', 'money']],
            'rows' => $rows->values(),
        ];
    }

    private function rPaymentMethods(array $o): array
    {
        $labels = ['cash' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer', 'card' => 'Kartu', 'gateway' => 'Online'];
        $rows = Payment::query()->where('status', 'paid')->whereBetween('paid_at', [$this->from, $this->to])
            ->selectRaw('method, COUNT(*) as count, SUM(amount) as total')->groupBy('method')->orderByDesc('total')->get()
            ->map(fn ($r) => ['name' => $labels[$r->method->value] ?? $r->method->value, 'count' => (int) $r->count, 'total' => (int) $r->total]);

        return [
            'columns' => [['name', 'Metode', 'text'], ['count', 'Transaksi', 'number'], ['total', 'Total', 'money']],
            'rows' => $rows->values(),
            'chart' => ['x' => 'name', 'y' => 'total', 'kind' => 'pie'],
        ];
    }

    private function rOrderTypes(array $o): array
    {
        $labels = ['dine_in' => 'Dine-in', 'takeaway' => 'Takeaway', 'delivery' => 'Delivery'];
        $rows = $this->paidBills()->selectRaw('type, COUNT(*) as bills, SUM(total) as total')->groupBy('type')->get()
            ->map(fn ($r) => ['name' => $labels[$r->type->value] ?? $r->type->value, 'bills' => (int) $r->bills, 'total' => (int) $r->total, 'avg' => $r->bills ? (int) round($r->total / $r->bills) : 0]);

        return [
            'columns' => [['name', 'Tipe', 'text'], ['bills', 'Transaksi', 'number'], ['total', 'Total', 'money'], ['avg', 'Rata-rata', 'money']],
            'rows' => $rows->values(),
            'chart' => ['x' => 'name', 'y' => 'total', 'kind' => 'pie'],
        ];
    }

    private function rPeakHours(array $o): array
    {
        $h = SqlFn::hour('bills.opened_at');
        $data = $this->paidBills()->selectRaw("$h as hour, COUNT(*) as bills, SUM(total) as total")->groupBy('hour')->get()->keyBy('hour');
        $days = max(1, $this->from->diffInDays($this->to) + 1);
        $rows = collect(range(0, 23))->map(fn ($i) => [
            'hour' => sprintf('%02d:00', $i),
            'bills' => (int) ($data[$i]->bills ?? 0),
            'total' => (int) ($data[$i]->total ?? 0),
            'avg_per_day' => round(($data[$i]->bills ?? 0) / $days, 1),
        ])->filter(fn ($r) => $r['bills'] > 0 || ($r['hour'] >= '07:00' && $r['hour'] <= '22:00'))->values();
        $peak = $rows->sortByDesc('bills')->first();

        return [
            'columns' => [['hour', 'Jam', 'text'], ['bills', 'Transaksi', 'number'], ['avg_per_day', 'Rata-rata/hari', 'number'], ['total', 'Total', 'money']],
            'rows' => $rows,
            'summary' => [['Jam tersibuk', $peak && $peak['bills'] ? $peak['hour'] : '-', 'text']],
            'chart' => ['x' => 'hour', 'y' => 'bills', 'kind' => 'bar'],
        ];
    }

    private function rTables(array $o): array
    {
        $dur = SqlFn::minutesDiff('bills.opened_at', 'bills.paid_at');
        $days = max(1, $this->from->diffInDays($this->to) + 1);
        $rows = $this->paidBills()->whereNotNull('bills.table_id')->join('tables', 'tables.id', '=', 'bills.table_id')
            ->selectRaw("tables.code as code, COUNT(*) as bills, SUM(bills.total) as total, AVG($dur) as avg_minutes, AVG(bills.guest_count) as guests")
            ->groupBy('tables.code')->orderBy('tables.code')->get()
            ->map(fn ($r) => [
                'code' => $r->code, 'bills' => (int) $r->bills, 'total' => (int) $r->total,
                'avg_minutes' => (int) round((float) $r->avg_minutes), 'turnover' => round($r->bills / $days, 1),
                'avg_bill' => $r->bills ? (int) round($r->total / $r->bills) : 0,
            ]);

        return [
            'columns' => [['code', 'Meja', 'text'], ['bills', 'Jumlah Bill', 'number'], ['turnover', 'Perputaran/hari', 'number'], ['avg_minutes', 'Rata-rata durasi (menit)', 'number'], ['avg_bill', 'Rata-rata bill', 'money'], ['total', 'Total', 'money']],
            'rows' => $rows->values(),
            'summary' => [['Rata-rata durasi semua meja', (int) round($rows->avg('avg_minutes') ?? 0).' menit', 'text']],
            'chart' => ['x' => 'code', 'y' => 'total', 'kind' => 'bar'],
        ];
    }

    private function rServingTime(array $o): array
    {
        $dur = SqlFn::minutesDiff('order_items.created_at', 'order_items.ready_at');
        $base = OrderItem::query()->whereNotNull('order_items.ready_at')->whereBetween('order_items.created_at', [$this->from, $this->to]);
        $byStation = (clone $base)->leftJoin('stations', 'stations.id', '=', 'order_items.station_id')
            ->selectRaw("COALESCE(stations.name, '-') as station, COUNT(*) as items, AVG($dur) as avg_minutes, MAX($dur) as max_minutes")
            ->groupBy('stations.name')->get();
        $rows = (clone $base)->selectRaw("order_items.menu_name as name, COUNT(*) as items, AVG($dur) as avg_minutes, MAX($dur) as max_minutes")
            ->groupBy('order_items.menu_name')->orderByDesc('avg_minutes')->get()
            ->map(fn ($r) => ['name' => $r->name, 'items' => (int) $r->items, 'avg_minutes' => round((float) $r->avg_minutes, 1), 'max_minutes' => round((float) $r->max_minutes, 1)]);

        return [
            'columns' => [['name', 'Menu', 'text'], ['items', 'Jumlah', 'number'], ['avg_minutes', 'Rata-rata (menit)', 'number'], ['max_minutes', 'Terlama (menit)', 'number']],
            'rows' => $rows->values(),
            'summary' => $byStation->map(fn ($s) => ['Rata-rata '.$s->station, round((float) $s->avg_minutes, 1).' menit', 'text'])->values()->all(),
            'chart' => ['x' => 'name', 'y' => 'avg_minutes', 'kind' => 'bar', 'limit' => 10],
        ];
    }

    private function rProfitLoss(array $o): array
    {
        $b = $this->paidBills()->selectRaw('SUM(total) as total, SUM(tax) as tax, SUM(service_charge) as service, SUM(cost_total) as cost, SUM(delivery_fee) as delivery')->first();
        $refunds = (int) Refund::whereBetween('created_at', [$this->from, $this->to])->sum('amount');
        $gross = (int) $b->total - (int) $b->tax;
        $net = $gross - $refunds;
        $cost = (int) $b->cost;
        $waste = (int) round(abs((float) StockMovement::where('type', 'waste')->whereBetween('created_at', [$this->from, $this->to])->selectRaw('SUM(qty * unit_cost) as v')->value('v')));
        $expenses = Expense::query()->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')->groupBy('expense_categories.name')->get();
        $opex = (int) $expenses->sum('total');
        $grossProfit = $net - $cost - $waste;

        $rows = collect([
            ['item' => 'Penjualan (tanpa pajak)', 'amount' => $gross, 'kind' => 'income'],
            ['item' => 'Refund', 'amount' => -$refunds, 'kind' => 'income'],
            ['item' => 'Penjualan Bersih', 'amount' => $net, 'kind' => 'subtotal'],
            ['item' => 'HPP (Harga Pokok Penjualan)', 'amount' => -$cost, 'kind' => 'cost'],
            ['item' => 'Waste / Bahan Rusak', 'amount' => -$waste, 'kind' => 'cost'],
            ['item' => 'Laba Kotor', 'amount' => $grossProfit, 'kind' => 'subtotal'],
        ]);
        foreach ($expenses as $e) {
            $rows->push(['item' => 'Biaya: '.$e->name, 'amount' => -(int) $e->total, 'kind' => 'expense']);
        }
        $rows->push(['item' => 'Total Biaya Operasional', 'amount' => -$opex, 'kind' => 'subtotal']);
        $rows->push(['item' => 'LABA BERSIH', 'amount' => $grossProfit - $opex, 'kind' => 'total']);

        return [
            'columns' => [['item', 'Keterangan', 'text'], ['amount', 'Nominal', 'money']],
            'rows' => $rows->values(),
            'summary' => [
                ['Penjualan bersih', $net, 'money'], ['Laba kotor', $grossProfit, 'money'],
                ['Margin kotor', $net > 0 ? round($grossProfit / $net * 100, 1).'%' : '-', 'text'],
                ['Laba bersih', $grossProfit - $opex, 'money'], ['Pajak terkumpul (PPN)', (int) $b->tax, 'money'],
            ],
        ];
    }

    private function rStock(array $o): array
    {
        $rows = Ingredient::query()->with('unit')->orderBy('name')->get()->map(fn ($i) => [
            'name' => $i->name, 'unit' => $i->unit?->symbol, 'stock' => round($i->stock, 3), 'min_stock' => round($i->min_stock, 3),
            'cost_per_unit' => round($i->cost_per_unit, 2), 'value' => (int) round(max(0, $i->stock) * $i->cost_per_unit),
            'status' => $i->stock <= 0 ? 'Habis' : ($i->isLow() ? 'Menipis' : 'Aman'),
        ]);

        return [
            'columns' => [['name', 'Bahan', 'text'], ['unit', 'Satuan', 'text'], ['stock', 'Stok', 'number'], ['min_stock', 'Minimum', 'number'], ['cost_per_unit', 'HPP/Satuan', 'money'], ['value', 'Nilai', 'money'], ['status', 'Status', 'text']],
            'rows' => $rows->values(),
            'summary' => [['Total nilai persediaan', (int) $rows->sum('value'), 'money'], ['Bahan menipis/habis', $rows->where('status', '!=', 'Aman')->count(), 'number']],
        ];
    }

    private function rIngredientUsage(array $o): array
    {
        $rows = StockMovement::query()->where('stock_movements.type', 'sale')->whereBetween('stock_movements.created_at', [$this->from, $this->to])
            ->join('ingredients', 'ingredients.id', '=', 'stock_movements.ingredient_id')->leftJoin('units', 'units.id', '=', 'ingredients.unit_id')
            ->selectRaw('ingredients.name as name, units.symbol as unit, SUM(-stock_movements.qty) as qty, SUM(-stock_movements.qty * stock_movements.unit_cost) as value')
            ->groupBy('ingredients.name', 'units.symbol')->orderByDesc('value')->get()
            ->map(fn ($r) => ['name' => $r->name, 'unit' => $r->unit, 'qty' => round((float) $r->qty, 3), 'value' => (int) round((float) $r->value)]);

        return [
            'columns' => [['name', 'Bahan', 'text'], ['unit', 'Satuan', 'text'], ['qty', 'Terpakai', 'number'], ['value', 'Nilai', 'money']],
            'rows' => $rows->values(),
            'summary' => [['Total nilai pemakaian', (int) $rows->sum('value'), 'money']],
        ];
    }

    private function rWaste(array $o): array
    {
        $rows = StockMovement::query()->with(['ingredient.unit', 'user:id,name'])->where('type', 'waste')
            ->whereBetween('created_at', [$this->from, $this->to])->latest('id')->get()
            ->map(fn ($m) => [
                'date' => $m->created_at->format('d/m/Y H:i'), 'name' => $m->ingredient?->name, 'qty' => abs($m->qty),
                'unit' => $m->ingredient?->unit?->symbol, 'value' => (int) round(abs($m->qty) * $m->unit_cost), 'reason' => $m->reason, 'user' => $m->user?->name,
            ]);

        return [
            'columns' => [['date', 'Waktu', 'text'], ['name', 'Bahan', 'text'], ['qty', 'Jumlah', 'number'], ['unit', 'Satuan', 'text'], ['value', 'Nilai', 'money'], ['reason', 'Alasan', 'text'], ['user', 'Oleh', 'text']],
            'rows' => $rows->values(),
            'summary' => [['Total nilai waste', (int) $rows->sum('value'), 'money']],
        ];
    }

    private function rDiscounts(array $o): array
    {
        $rows = $this->paidBills()->leftJoin('vouchers', 'vouchers.id', '=', 'bills.voucher_id')
            ->where(fn ($q) => $q->where('discount_amount', '>', 0)->orWhere('voucher_amount', '>', 0)->orWhere('promo_amount', '>', 0)->orWhere('points_amount', '>', 0))
            ->orderBy('bills.paid_at')
            ->get(['bills.invoice_no', 'bills.paid_at', 'bills.subtotal', 'bills.discount_amount', 'bills.voucher_amount', 'bills.promo_amount', 'bills.points_amount', 'bills.total', 'vouchers.code as voucher_code'])
            ->map(fn ($b) => [
                'invoice_no' => $b->invoice_no, 'date' => $b->paid_at?->format('d/m/Y H:i'), 'subtotal' => $b->subtotal,
                'manual' => $b->discount_amount, 'voucher' => $b->voucher_amount, 'voucher_code' => $b->voucher_code ?? '-',
                'promo' => $b->promo_amount, 'points' => $b->points_amount, 'total' => $b->total,
            ]);

        return [
            'columns' => [['invoice_no', 'Invoice', 'text'], ['date', 'Waktu', 'text'], ['subtotal', 'Subtotal', 'money'], ['manual', 'Diskon Manual', 'money'], ['voucher_code', 'Kode Voucher', 'text'], ['voucher', 'Voucher', 'money'], ['promo', 'Happy Hour', 'money'], ['points', 'Poin', 'money'], ['total', 'Total', 'money']],
            'rows' => $rows->values(),
            'summary' => [
                ['Diskon manual', (int) $rows->sum('manual'), 'money'], ['Voucher', (int) $rows->sum('voucher'), 'money'],
                ['Happy hour', (int) $rows->sum('promo'), 'money'], ['Tukar poin', (int) $rows->sum('points'), 'money'],
            ],
        ];
    }

    private function rVoids(array $o): array
    {
        $voids = Bill::query()->with('closer:id,name')->where('status', 'void')->whereBetween('updated_at', [$this->from, $this->to])->get()
            ->map(fn ($b) => ['date' => $b->updated_at->format('d/m/Y H:i'), 'kind' => 'Void bill', 'ref' => $b->invoice_no, 'amount' => (int) $b->items()->sum('line_total'), 'reason' => $b->void_reason, 'user' => $b->closer?->name]);
        $refunds = Refund::query()->with(['bill:id,invoice_no', 'creator:id,name'])->whereBetween('created_at', [$this->from, $this->to])->get()
            ->map(fn ($r) => ['date' => $r->created_at->format('d/m/Y H:i'), 'kind' => 'Refund'.($r->restock ? ' (stok kembali)' : ''), 'ref' => $r->bill?->invoice_no, 'amount' => $r->amount, 'reason' => $r->reason, 'user' => $r->creator?->name]);
        $items = OrderItem::query()->with(['bill:id,invoice_no,status', 'order:id,status'])->where('status', 'cancelled')->whereNotNull('cancelled_by')
            ->whereBetween('updated_at', [$this->from, $this->to])->get()
            ->filter(fn ($i) => $i->bill && $i->bill->status->value !== 'void')
            ->map(fn ($i) => ['date' => $i->updated_at->format('d/m/Y H:i'), 'kind' => 'Hapus item: '.$i->menu_name.' x'.$i->qty, 'ref' => $i->bill?->invoice_no, 'amount' => $i->line_total, 'reason' => $i->cancel_reason, 'user' => optional(\App\Models\User::withTrashed()->find($i->cancelled_by))->name]);
        $rows = $voids->concat($refunds)->concat($items)->sortBy('date')->values();

        return [
            'columns' => [['date', 'Waktu', 'text'], ['kind', 'Jenis', 'text'], ['ref', 'Invoice', 'text'], ['amount', 'Nominal', 'money'], ['reason', 'Alasan', 'text'], ['user', 'Oleh', 'text']],
            'rows' => $rows,
            'summary' => [['Total void', (int) $voids->sum('amount'), 'money'], ['Total refund', (int) $refunds->sum('amount'), 'money'], ['Item dihapus', $items->count(), 'number']],
        ];
    }

    private function rShifts(array $o): array
    {
        $rows = Shift::query()->with('user:id,name')->whereBetween('opened_at', [$this->from, $this->to])->orderBy('opened_at')->get()
            ->map(fn ($s) => [
                'user' => $s->user?->name, 'opened_at' => $s->opened_at->format('d/m/Y H:i'), 'closed_at' => $s->closed_at?->format('d/m/Y H:i') ?? 'Aktif',
                'opening_cash' => $s->opening_cash, 'cash_sales' => $s->cash_sales, 'non_cash_sales' => $s->non_cash_sales,
                'expected_cash' => $s->expected_cash, 'counted_cash' => $s->counted_cash, 'difference' => $s->difference,
            ]);

        return [
            'columns' => [['user', 'Kasir', 'text'], ['opened_at', 'Buka', 'text'], ['closed_at', 'Tutup', 'text'], ['opening_cash', 'Modal', 'money'], ['cash_sales', 'Tunai', 'money'], ['non_cash_sales', 'Non-tunai', 'money'], ['expected_cash', 'Kas Sistem', 'money'], ['counted_cash', 'Kas Fisik', 'money'], ['difference', 'Selisih', 'money']],
            'rows' => $rows->values(),
            'summary' => [['Total selisih kas', (int) $rows->sum('difference'), 'money']],
        ];
    }
}
