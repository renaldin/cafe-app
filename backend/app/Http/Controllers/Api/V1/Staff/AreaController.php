<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function index()
    {
        return $this->ok(Area::withCount('tables')->orderBy('sort')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50'], 'sort' => ['nullable', 'integer', 'min:0']]);

        return $this->created(Area::create($data));
    }

    public function update(Request $request, Area $area)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50'], 'sort' => ['nullable', 'integer', 'min:0']]);
        $area->update($data);

        return $this->ok($area, 'Area diperbarui.');
    }

    public function destroy(Area $area)
    {
        if ($area->tables()->exists()) {
            throw new BusinessException('Area masih memiliki meja.');
        }
        $area->delete();

        return $this->ok(null, 'Area dihapus.');
    }
}
