<?php

namespace App\Exports;

use App\Models\Menu;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MenuExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping
{
    public function collection()
    {
        return Menu::query()->with('category')->orderBy('category_id')->orderBy('sort')->get();
    }

    public function headings(): array
    {
        return ['sku', 'nama', 'kategori', 'harga', 'deskripsi', 'tersedia', 'label', 'urutan'];
    }

    public function map($menu): array
    {
        return [
            $menu->sku, $menu->name, $menu->category?->name, $menu->price, $menu->description,
            $menu->is_available ? 'ya' : 'tidak', implode(',', $menu->labels ?? []), $menu->sort,
        ];
    }
}
