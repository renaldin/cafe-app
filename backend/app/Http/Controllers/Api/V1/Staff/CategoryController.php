<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Station;
use App\Services\MenuCatalog;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $cats = Category::query()->with('station')->withCount('menus')->orderBy('sort')->orderBy('name')->get();

        return $this->ok(CategoryResource::collection($cats)->resolve(request()));
    }

    public function stations()
    {
        return $this->ok(Station::orderBy('id')->get(['id', 'name', 'code']));
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = $this->slug($data['name']);
        $cat = Category::create($data);
        MenuCatalog::bump();

        return $this->created(CategoryResource::make($cat->load('station')));
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        if ($data['name'] !== $category->name) {
            $data['slug'] = $this->slug($data['name'], $category->id);
        }
        $category->update($data);
        MenuCatalog::bump();

        return $this->ok(CategoryResource::make($category->load('station')), 'Kategori diperbarui.');
    }

    public function destroy(Category $category)
    {
        if ($category->menus()->exists()) {
            throw new BusinessException('Kategori masih memiliki menu. Pindahkan atau hapus menunya terlebih dahulu.');
        }
        $category->update(['slug' => $category->slug.'-del-'.$category->id]);
        $category->delete();
        MenuCatalog::bump();

        return $this->ok(null, 'Kategori dihapus.');
    }

    private function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 2;
        while (Category::withTrashed()->where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
