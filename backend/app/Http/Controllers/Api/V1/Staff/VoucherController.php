<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoucherRequest;
use App\Models\HappyHour;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $q = Voucher::query()->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('code', 'like', "%$s%")->orWhere('name', 'like', "%$s%")))->latest('id');

        return $this->paginated($q->paginate($this->perPage()));
    }

    public function store(VoucherRequest $request)
    {
        return $this->created(Voucher::create($request->validated()));
    }

    public function update(VoucherRequest $request, Voucher $voucher)
    {
        $voucher->update($request->validated());

        return $this->ok($voucher, 'Voucher diperbarui.');
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->update(['code' => $voucher->code.'-DEL'.$voucher->id]);
        $voucher->delete();

        return $this->ok(null, 'Voucher dihapus.');
    }

    public function check(Request $request, VoucherService $vouchers)
    {
        $data = $request->validate(['code' => ['required', 'string'], 'subtotal' => ['required', 'integer', 'min:0']]);
        $v = $vouchers->validate($data['code'], $data['subtotal']);

        return $this->ok($v, 'Voucher valid.');
    }

    // ---- Happy hour ----
    private array $hhRules = [
        'name' => ['required', 'string', 'max:100'],
        'days' => ['required', 'array', 'min:1'],
        'days.*' => ['integer', 'between:1,7'],
        'start_time' => ['required', 'date_format:H:i'],
        'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        'type' => ['required', 'in:percent,fixed'],
        'value' => ['required', 'integer', 'min:1'],
        'category_ids' => ['nullable', 'array'],
        'category_ids.*' => ['integer', 'exists:categories,id'],
        'is_active' => ['boolean'],
    ];

    public function happyHours()
    {
        return $this->ok(HappyHour::orderBy('start_time')->get());
    }

    public function storeHappyHour(Request $request)
    {
        return $this->created(HappyHour::create($request->validate($this->hhRules)));
    }

    public function updateHappyHour(Request $request, HappyHour $happyHour)
    {
        $happyHour->update($request->validate($this->hhRules));

        return $this->ok($happyHour, 'Happy hour diperbarui.');
    }

    public function destroyHappyHour(HappyHour $happyHour)
    {
        $happyHour->delete();

        return $this->ok(null, 'Happy hour dihapus.');
    }
}
