<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillResource;
use App\Models\Customer;
use App\Models\PointTransaction;
use App\Services\OrderService;
use App\Support\Activity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = Customer::query()
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('phone', 'like', "%$s%")))
            ->orderBy($request->query('sort') === 'spent' ? 'total_spent' : 'name', $request->query('sort') === 'spent' ? 'desc' : 'asc');

        return $this->paginated($q->paginate($this->perPage()));
    }

    public function show(Customer $customer)
    {
        $bills = $customer->bills()->with('table')->latest('id')->limit(30)->get();

        return $this->ok([
            'customer' => $customer,
            'bills' => BillResource::collection($bills)->resolve(request()),
            'points' => PointTransaction::where('customer_id', $customer->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return $this->created(Customer::create($data));
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request, $customer->id);
        $customer->update($data);

        return $this->ok($customer, 'Pelanggan diperbarui.');
    }

    public function adjustPoints(Request $request, Customer $customer)
    {
        $data = $request->validate(['points' => ['required', 'integer', 'not_in:0'], 'note' => ['required', 'string', 'max:255']]);
        $before = $customer->points;
        $customer->update(['points' => max(0, $customer->points + $data['points'])]);
        PointTransaction::create(['customer_id' => $customer->id, 'points' => $data['points'], 'type' => 'adjust', 'note' => $data['note']]);
        Activity::log('customer.points', $customer, "Penyesuaian poin {$customer->name}", ['points' => $before], ['points' => $customer->points], $data['note']);

        return $this->ok($customer, 'Poin disesuaikan.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return $this->ok(null, 'Pelanggan dihapus.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $request->merge(['phone' => OrderService::normalizePhone((string) $request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('customers', 'phone')->ignore($id)],
            'email' => ['nullable', 'email', 'max:150'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['phone.required' => 'Nomor WA tidak valid.']);

        return $data;
    }
}
