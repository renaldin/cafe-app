<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    private array $rules = [
        'name' => ['required', 'string', 'max:100'],
        'phone' => ['nullable', 'string', 'max:30'],
        'address' => ['nullable', 'string', 'max:255'],
        'note' => ['nullable', 'string', 'max:255'],
    ];

    public function index(Request $request)
    {
        $q = Supplier::query()->when($request->query('search'), fn ($q, $s) => $q->where('name', 'like', "%$s%"))->orderBy('name');

        return $request->boolean('all') ? $this->ok($q->get()) : $this->paginated($q->paginate($this->perPage()));
    }

    public function store(Request $request)
    {
        return $this->created(Supplier::create($request->validate($this->rules)));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($request->validate($this->rules));

        return $this->ok($supplier, 'Supplier diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return $this->ok(null, 'Supplier dihapus.');
    }
}
