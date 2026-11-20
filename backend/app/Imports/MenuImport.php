<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Menu;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Kolom: sku, nama, kategori, harga, deskripsi, tersedia (ya/tidak), label (koma), urutan
 * Baris dengan sku/nama yang sudah ada akan diperbarui.
 */
class MenuImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public array $errors = [];

    public function collection(Collection $rows)
    {
        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $name = trim((string) ($row['nama'] ?? ''));
            $catName = trim((string) ($row['kategori'] ?? ''));
            $price = (int) preg_replace('/\D/', '', (string) ($row['harga'] ?? ''));
            if ($name === '' || $catName === '') {
                $this->errors[] = "Baris $line: nama dan kategori wajib diisi.";

                continue;
            }
            $cat = Category::firstOrCreate(['name' => $catName], ['slug' => Str::slug($catName).'-'.Str::lower(Str::random(4)), 'is_active' => true]);
            $sku = trim((string) ($row['sku'] ?? '')) ?: null;
            $menu = $sku ? Menu::where('sku', $sku)->first() : Menu::where('name', $name)->first();
            $labels = array_values(array_filter(array_map('trim', explode(',', (string) ($row['label'] ?? '')))));
            $attrs = [
                'name' => $name, 'category_id' => $cat->id, 'price' => $price, 'sku' => $sku,
                'description' => $row['deskripsi'] ?? null,
                'is_available' => ! in_array(strtolower((string) ($row['tersedia'] ?? 'ya')), ['tidak', '0', 'no', 'habis'], true),
                'labels' => array_values(array_intersect($labels, ['best_seller', 'new', 'spicy', 'vegan', 'recommended'])),
                'sort' => (int) ($row['urutan'] ?? 0),
            ];
            if ($menu) {
                $menu->update($attrs);
                $this->updated++;
            } else {
                Menu::create($attrs + ['slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'is_active' => true]);
                $this->created++;
            }
        }
    }
}
