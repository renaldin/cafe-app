<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\ReasonRequest;
use App\Http\Requests\RefundRequest;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Services\BillService;
use App\Services\ImageService;
use App\Services\PaymentService;
use App\Support\Settings;
use Illuminate\Http\Request;

class BillController extends Controller
{
    private function full(Bill $bill): array
    {
        $bill->load(['table', 'customer', 'voucher', 'items' => fn ($q) => $q->orderBy('id'), 'items.modifiers', 'orders.items', 'payments', 'refunds', 'children', 'opener', 'closer']);

        return BillResource::make($bill)->resolve(request());
    }

    public function index(Request $request)
    {
        $q = Bill::query()->with(['table', 'closer'])
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('opened_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('opened_at', '<=', $d))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('invoice_no', 'like', "%$s%")->orWhere('customer_name', 'like', "%$s%")->orWhere('customer_phone', 'like', "%$s%")))
            ->latest('id');

        return $this->paginated($q->paginate($this->perPage()), BillResource::class);
    }

    public function show(Bill $bill)
    {
        return $this->ok($this->full($bill));
    }

    public function requestBill(Bill $bill, BillService $bills)
    {
        return $this->ok($this->full($bills->requestBill($bill)), 'Bill siap dibayar.');
    }

    public function discount(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:percent,fixed'],
            'value' => ['required_with:type', 'integer', 'min:0'],
            'reason' => ['required_with:type', 'nullable', 'string', 'max:255'],
        ]);

        return $this->ok($this->full($bills->setDiscount($bill, $data['type'] ?? null, (int) ($data['value'] ?? 0), $request->user(), $data['reason'] ?? null)), 'Diskon diterapkan.');
    }

    public function voucher(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate(['code' => ['nullable', 'string', 'max:40']]);

        return $this->ok($this->full($bills->applyVoucher($bill, $data['code'] ?? null)), ! empty($data['code']) ? 'Voucher diterapkan.' : 'Voucher dilepas.');
    }

    public function points(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate(['points' => ['required', 'integer', 'min:0']]);

        return $this->ok($this->full($bills->redeemPoints($bill, $data['points'])), 'Poin diterapkan.');
    }

    public function customer(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        return $this->ok($this->full($bills->attachCustomer($bill, $data['customer_id'] ?? null, $data['name'] ?? null, $data['phone'] ?? null)), 'Pelanggan dihubungkan.');
    }

    public function delivery(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate([
            'delivery_fee' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'delivery_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ]);

        return $this->ok($this->full($bills->updateDelivery($bill, $data, $request->user())), 'Data pengantaran diperbarui.');
    }

    public function split(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);
        $child = $bills->splitByItems($bill, $data['items'], $request->user());

        return $this->ok(['new_bill' => $this->full($child), 'bill' => $this->full($bill->fresh())], 'Bill berhasil dipecah.');
    }

    public function merge(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate(['bill_ids' => ['required', 'array', 'min:1'], 'bill_ids.*' => ['integer', 'exists:bills,id']]);

        return $this->ok($this->full($bills->merge($bill, $data['bill_ids'], $request->user())), 'Bill berhasil digabung.');
    }

    public function moveTable(Request $request, Bill $bill, BillService $bills)
    {
        $data = $request->validate(['table_id' => ['required', 'integer', 'exists:tables,id']]);

        return $this->ok($this->full($bills->moveTable($bill, $data['table_id'], $request->user())), 'Bill dipindahkan ke meja baru.');
    }

    public function pay(PaymentRequest $request, Bill $bill, PaymentService $payments)
    {
        $result = $payments->pay($bill, $request->validated('payments'), $request->user());

        return $this->ok($this->full($result), $result->status->value === 'paid' ? 'Pembayaran berhasil. Bill lunas.' : 'Pembayaran sebagian tercatat.');
    }

    public function void(ReasonRequest $request, Bill $bill, BillService $bills)
    {
        return $this->ok($this->full($bills->void($bill, $request->user(), $request->validated('reason'))), 'Bill di-void.');
    }

    public function refund(RefundRequest $request, Bill $bill, BillService $bills)
    {
        $d = $request->validated();

        return $this->ok($this->full($bills->refund($bill, $request->user(), (int) $d['amount'], $d['reason'], (bool) ($d['restock'] ?? false), $d['method'] ?? 'cash')), 'Refund tercatat.');
    }

    public function receipt(Bill $bill)
    {
        $s = Settings::all();

        return $this->ok([
            'bill' => $this->full($bill),
            'store' => [
                'name' => $s['cafe_name'], 'address' => $s['cafe_address'], 'phone' => $s['cafe_phone'],
                'logo' => ImageService::url($s['cafe_logo']), 'footer' => $s['receipt_footer'], 'paper' => $s['receipt_paper'],
                'instagram' => $s['social_instagram'], 'tax_percent' => $s['tax_percent'], 'service_percent' => $s['service_percent'],
            ],
            'cashier' => $bill->closer?->name ?? request()->user()->name,
        ]);
    }
}
