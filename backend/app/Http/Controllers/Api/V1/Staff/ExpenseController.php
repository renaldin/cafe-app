<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    private array $rules = [
        'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
        'date' => ['required', 'date'],
        'amount' => ['required', 'integer', 'min:1'],
        'note' => ['nullable', 'string', 'max:255'],
    ];

    public function index(Request $request)
    {
        $q = Expense::query()->with(['category:id,name', 'user:id,name'])
            ->when($request->query('category_id'), fn ($q, $c) => $q->where('expense_category_id', $c))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->latest('date')->latest('id');
        $sum = (clone $q)->sum('amount');

        return $this->paginated($q->paginate($this->perPage()), null, ['sum' => (int) $sum]);
    }

    public function store(Request $request)
    {
        return $this->created(Expense::create($request->validate($this->rules) + ['user_id' => $request->user()->id])->load('category'));
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($request->validate($this->rules));

        return $this->ok($expense->load('category'), 'Biaya diperbarui.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return $this->ok(null, 'Biaya dihapus.');
    }

    public function categories()
    {
        return $this->ok(ExpenseCategory::orderBy('name')->get(['id', 'name']));
    }

    public function storeCategory(Request $request)
    {
        return $this->created(ExpenseCategory::create($request->validate(['name' => ['required', 'string', 'max:100']])));
    }

    public function destroyCategory(ExpenseCategory $expenseCategory)
    {
        if (Expense::where('expense_category_id', $expenseCategory->id)->exists()) {
            throw new BusinessException('Kategori masih dipakai.');
        }
        $expenseCategory->delete();

        return $this->ok(null, 'Kategori dihapus.');
    }
}
