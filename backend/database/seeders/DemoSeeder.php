<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\HappyHour;
use App\Models\Ingredient;
use App\Models\Menu;
use App\Models\ModifierGroup;
use App\Models\ModifierOptionRecipe;
use App\Models\Recipe;
use App\Models\Station;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\Unit;
use App\Models\User;
use App\Models\Voucher;
use App\Services\RecipeCostService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Menu::query()->exists()) {
            return; // jangan menimpa data yang sudah ada
        }
        $kitchen = Station::where('code', 'kitchen')->first();
        $bar = Station::where('code', 'bar')->first();

        // Staf demo (password: password123) — hapus di produksi!
        foreach ([['manajer', 'Maya Manajer', 'Manajer'], ['kasir', 'Kiki Kasir', 'Kasir'], ['pelayan', 'Putra Pelayan', 'Pelayan'], ['dapur', 'Dedi Dapur', 'Dapur'], ['barista', 'Bima Barista', 'Bar']] as [$u, $n, $r]) {
            $user = User::firstOrCreate(['username' => $u], ['name' => $n, 'password' => 'password123', 'is_active' => true]);
            $user->syncRoles([$r]);
        }

        // Satuan & bahan
        $units = collect([['Gram', 'g'], ['Mililiter', 'ml'], ['Pcs', 'pcs'], ['Kilogram', 'kg'], ['Liter', 'l']])
            ->mapWithKeys(fn ($u) => [$u[1] => Unit::firstOrCreate(['symbol' => $u[1]], ['name' => $u[0]])->id]);
        $ing = [];
        foreach ([
            ['Biji Kopi Arabika', 'g', 3000, 500, 250, true], ['Biji Kopi Robusta', 'g', 3000, 500, 150, true],
            ['Susu Segar', 'ml', 20000, 3000, 22, true], ['Gula Aren Cair', 'ml', 5000, 1000, 40, false],
            ['Gula Pasir', 'g', 5000, 1000, 15, false], ['Sirup Vanilla', 'ml', 2000, 300, 120, false],
            ['Sirup Hazelnut', 'ml', 2000, 300, 120, false], ['Bubuk Coklat', 'g', 2000, 300, 110, true],
            ['Bubuk Matcha', 'g', 1000, 200, 450, true], ['Teh Hitam', 'g', 1000, 200, 80, true],
            ['Es Batu', 'g', 50000, 5000, 1, false], ['Cup Plastik 16oz', 'pcs', 500, 100, 900, false],
            ['Krimer / Whipped Cream', 'ml', 2000, 300, 90, false], ['Boba', 'g', 3000, 500, 45, false],
            ['Beras', 'g', 20000, 3000, 14, true], ['Telur', 'pcs', 120, 30, 2200, false],
            ['Ayam Fillet', 'g', 8000, 1500, 65, true], ['Mie Telur', 'g', 5000, 1000, 30, true],
            ['Roti Tawar', 'pcs', 100, 20, 1500, true], ['Kentang Beku', 'g', 8000, 1500, 40, true],
            ['Minyak Goreng', 'ml', 10000, 2000, 18, false], ['Pisang', 'pcs', 60, 15, 1500, true],
            ['Keju Cheddar', 'g', 2000, 300, 120, false], ['Bumbu Nasi Goreng', 'g', 2000, 300, 60, false],
            ['Sosis', 'pcs', 100, 20, 2500, false],
        ] as [$name, $unit, $stock, $min, $cost, $key]) {
            $ing[$name] = Ingredient::create(['name' => $name, 'unit_id' => $units[$unit], 'stock' => $stock, 'min_stock' => $min, 'cost_per_unit' => $cost, 'is_key' => $key])->id;
        }

        // Kategori
        $cats = [];
        foreach ([['Kopi', $bar], ['Non-Kopi', $bar], ['Makanan', $kitchen], ['Snack', $kitchen]] as $i => [$name, $st]) {
            $cats[$name] = Category::create(['name' => $name, 'slug' => Str::slug($name), 'station_id' => $st->id, 'sort' => $i, 'is_active' => true])->id;
        }

        // Grup opsi
        $mk = function (string $name, string $sel, bool $req, array $opts, int $max = 1) {
            $g = ModifierGroup::create(['name' => $name, 'selection' => $sel, 'is_required' => $req, 'min_select' => $req ? 1 : 0, 'max_select' => $sel === 'single' ? 1 : $max]);
            $ids = [];
            foreach ($opts as $i => $o) {
                $ids[$o[0]] = $g->options()->create(['name' => $o[0], 'price_delta' => $o[1], 'is_default' => $i === 0, 'sort' => $i])->id;
            }

            return [$g->id, $ids];
        };
        [$gSize, $oSize] = $mk('Ukuran', 'single', true, [['Reguler', 0], ['Large', 5000]]);
        [$gSugar] = $mk('Level Gula', 'single', true, [['Normal', 0], ['Less Sugar', 0], ['No Sugar', 0]]);
        [$gIce] = $mk('Es', 'single', true, [['Normal Ice', 0], ['Less Ice', 0], ['Hot', 0]]);
        [$gTop, $oTop] = $mk('Topping', 'multiple', false, [['Extra Shot', 6000], ['Boba', 5000], ['Whipped Cream', 4000], ['Sirup Hazelnut', 5000]], 3);
        [$gSpicy] = $mk('Level Pedas', 'single', true, [['Tidak Pedas', 0], ['Sedang', 0], ['Pedas', 0], ['Extra Pedas', 2000]]);
        [$gEgg, $oEgg] = $mk('Tambahan', 'multiple', false, [['Telur Ceplok', 4000], ['Keju', 5000], ['Sosis', 6000]], 3);

        // Resep tambahan per opsi (Large memakai lebih banyak bahan)
        ModifierOptionRecipe::create(['modifier_option_id' => $oSize['Large'], 'ingredient_id' => $ing['Susu Segar'], 'qty' => 60]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oSize['Large'], 'ingredient_id' => $ing['Es Batu'], 'qty' => 50]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oTop['Extra Shot'], 'ingredient_id' => $ing['Biji Kopi Arabika'], 'qty' => 9]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oTop['Boba'], 'ingredient_id' => $ing['Boba'], 'qty' => 40]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oTop['Whipped Cream'], 'ingredient_id' => $ing['Krimer / Whipped Cream'], 'qty' => 30]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oTop['Sirup Hazelnut'], 'ingredient_id' => $ing['Sirup Hazelnut'], 'qty' => 15]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oEgg['Telur Ceplok'], 'ingredient_id' => $ing['Telur'], 'qty' => 1]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oEgg['Keju'], 'ingredient_id' => $ing['Keju Cheddar'], 'qty' => 20]);
        ModifierOptionRecipe::create(['modifier_option_id' => $oEgg['Sosis'], 'ingredient_id' => $ing['Sosis'], 'qty' => 1]);

        $coffeeMods = [$gSize, $gSugar, $gIce, $gTop];
        $drinkMods = [$gSize, $gSugar, $gIce, $gTop];
        $foodMods = [$gSpicy, $gEgg];

        $menus = [
            // [kategori, nama, harga, deskripsi, label, mods, resep]
            ['Kopi', 'Espresso', 18000, 'Single shot espresso arabika, pekat dan aromatik.', [], [$gSugar], ['Biji Kopi Arabika' => 18]],
            ['Kopi', 'Americano', 22000, 'Espresso dengan air, ringan dan segar.', [], $coffeeMods, ['Biji Kopi Arabika' => 18, 'Es Batu' => 150, 'Cup Plastik 16oz' => 1]],
            ['Kopi', 'Cappuccino', 28000, 'Espresso, susu steamed, dan foam tebal.', ['best_seller'], $coffeeMods, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 150, 'Cup Plastik 16oz' => 1]],
            ['Kopi', 'Caffe Latte', 28000, 'Espresso dengan susu creamy.', [], $coffeeMods, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 180, 'Cup Plastik 16oz' => 1]],
            ['Kopi', 'Es Kopi Susu Gula Aren', 25000, 'Signature kopi susu dengan gula aren asli.', ['best_seller', 'recommended'], $coffeeMods, ['Biji Kopi Robusta' => 18, 'Susu Segar' => 150, 'Gula Aren Cair' => 30, 'Es Batu' => 150, 'Cup Plastik 16oz' => 1]],
            ['Kopi', 'Vanilla Latte', 32000, 'Latte dengan sirup vanilla.', [], $coffeeMods, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 170, 'Sirup Vanilla' => 20, 'Cup Plastik 16oz' => 1]],
            ['Kopi', 'Hazelnut Latte', 32000, 'Latte dengan sirup hazelnut.', [], $coffeeMods, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 170, 'Sirup Hazelnut' => 20, 'Cup Plastik 16oz' => 1]],
            ['Kopi', 'Mocha', 32000, 'Perpaduan espresso, coklat, dan susu.', ['new'], $coffeeMods, ['Biji Kopi Arabika' => 18, 'Susu Segar' => 150, 'Bubuk Coklat' => 20, 'Cup Plastik 16oz' => 1]],
            ['Kopi', 'V60 Manual Brew', 30000, 'Kopi seduh manual single origin.', [], [], ['Biji Kopi Arabika' => 15]],
            ['Non-Kopi', 'Matcha Latte', 30000, 'Matcha Jepang dengan susu segar.', ['best_seller'], $drinkMods, ['Bubuk Matcha' => 15, 'Susu Segar' => 180, 'Gula Pasir' => 15, 'Cup Plastik 16oz' => 1]],
            ['Non-Kopi', 'Coklat Signature', 28000, 'Coklat premium yang kental.', [], $drinkMods, ['Bubuk Coklat' => 30, 'Susu Segar' => 180, 'Gula Pasir' => 10, 'Cup Plastik 16oz' => 1]],
            ['Non-Kopi', 'Es Teh Manis', 12000, 'Teh hitam segar dengan gula.', [], [$gSugar, $gIce], ['Teh Hitam' => 5, 'Gula Pasir' => 20, 'Es Batu' => 150, 'Cup Plastik 16oz' => 1]],
            ['Non-Kopi', 'Lemon Tea', 18000, 'Teh dengan perasan lemon.', ['vegan'], [$gSugar, $gIce], ['Teh Hitam' => 5, 'Gula Pasir' => 20, 'Es Batu' => 150, 'Cup Plastik 16oz' => 1]],
            ['Non-Kopi', 'Thai Tea', 22000, 'Teh Thailand dengan susu.', ['new'], $drinkMods, ['Teh Hitam' => 8, 'Susu Segar' => 100, 'Gula Pasir' => 15, 'Es Batu' => 150, 'Cup Plastik 16oz' => 1]],
            ['Non-Kopi', 'Brown Sugar Boba Milk', 28000, 'Susu segar dengan boba gula aren.', [], $drinkMods, ['Susu Segar' => 200, 'Boba' => 50, 'Gula Aren Cair' => 30, 'Cup Plastik 16oz' => 1]],
            ['Makanan', 'Nasi Goreng Spesial', 35000, 'Nasi goreng dengan ayam, telur, dan kerupuk.', ['best_seller', 'spicy'], $foodMods, ['Beras' => 150, 'Telur' => 1, 'Ayam Fillet' => 60, 'Bumbu Nasi Goreng' => 20, 'Minyak Goreng' => 20]],
            ['Makanan', 'Mie Goreng Jawa', 32000, 'Mie telur dimasak ala Jawa.', ['spicy'], $foodMods, ['Mie Telur' => 120, 'Telur' => 1, 'Ayam Fillet' => 40, 'Minyak Goreng' => 20]],
            ['Makanan', 'Ayam Geprek Sambal Bawang', 33000, 'Ayam crispy dengan sambal bawang.', ['spicy', 'recommended'], $foodMods, ['Beras' => 150, 'Ayam Fillet' => 120, 'Minyak Goreng' => 50]],
            ['Makanan', 'Chicken Katsu Rice', 38000, 'Ayam katsu dengan saus spesial.', [], [$gEgg], ['Beras' => 150, 'Ayam Fillet' => 120, 'Minyak Goreng' => 50, 'Telur' => 1]],
            ['Makanan', 'Nasi Telur Sarapan', 22000, 'Menu sarapan pagi (07:00-11:00).', ['new'], [$gEgg], ['Beras' => 150, 'Telur' => 2, 'Minyak Goreng' => 15], '07:00', '11:00'],
            ['Snack', 'French Fries', 22000, 'Kentang goreng renyah.', ['vegan'], [], ['Kentang Beku' => 150, 'Minyak Goreng' => 40]],
            ['Snack', 'Pisang Goreng Keju', 20000, 'Pisang goreng dengan keju parut.', ['best_seller'], [], ['Pisang' => 2, 'Keju Cheddar' => 20, 'Minyak Goreng' => 30]],
            ['Snack', 'Roti Bakar Coklat Keju', 24000, 'Roti bakar dengan selai coklat & keju.', [], [], ['Roti Tawar' => 2, 'Keju Cheddar' => 20, 'Bubuk Coklat' => 15]],
            ['Snack', 'Cireng Rujak', 18000, 'Cireng renyah dengan bumbu rujak.', ['spicy'], [], ['Minyak Goreng' => 40]],
            ['Snack', 'Sosis Bakar', 22000, 'Sosis bakar dengan saus BBQ.', [], [], ['Sosis' => 2, 'Minyak Goreng' => 10]],
        ];
        $cost = app(RecipeCostService::class);
        foreach ($menus as $i => $m) {
            $menu = Menu::create([
                'category_id' => $cats[$m[0]], 'name' => $m[1], 'slug' => Str::slug($m[1]), 'sku' => 'MN'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'price' => $m[2], 'description' => $m[3], 'labels' => $m[4], 'is_available' => true, 'is_active' => true, 'sort' => $i,
                'available_from' => $m[7] ?? null, 'available_until' => $m[8] ?? null,
            ]);
            $menu->modifierGroups()->sync(collect($m[5])->mapWithKeys(fn ($g, $k) => [$g => ['sort' => $k]])->all());
            foreach ($m[6] as $ingName => $qty) {
                Recipe::create(['menu_id' => $menu->id, 'ingredient_id' => $ing[$ingName], 'qty' => $qty]);
            }
            $cost->refreshMenuCost($menu);
        }

        // Paket bundle
        $bundle = Menu::create(['category_id' => $cats['Makanan'], 'name' => 'Paket Hemat Nasgor + Es Teh', 'slug' => 'paket-hemat-nasgor-es-teh', 'sku' => 'PK001', 'price' => 42000, 'description' => 'Nasi Goreng Spesial + Es Teh Manis', 'labels' => ['recommended'], 'is_bundle' => true, 'is_available' => true, 'is_active' => true, 'sort' => 99]);
        $bundle->bundleItems()->create(['menu_id' => Menu::where('name', 'Nasi Goreng Spesial')->value('id'), 'qty' => 1]);
        $bundle->bundleItems()->create(['menu_id' => Menu::where('name', 'Es Teh Manis')->value('id'), 'qty' => 1]);
        $bundle->update(['station_id' => $kitchen->id]);
        $cost->refreshMenuCost($bundle);

        // Area & meja
        $areas = [];
        foreach (['Indoor', 'Outdoor', 'Lantai 2'] as $i => $a) {
            $areas[$a] = Area::create(['name' => $a, 'sort' => $i])->id;
        }
        $layout = [['A1', 'Indoor'], ['A2', 'Indoor'], ['A3', 'Indoor'], ['A4', 'Indoor'], ['B1', 'Outdoor'], ['B2', 'Outdoor'], ['B3', 'Outdoor'], ['C1', 'Lantai 2'], ['C2', 'Lantai 2'], ['C3', 'Lantai 2']];
        foreach ($layout as $i => [$code, $area]) {
            Table::create(['area_id' => $areas[$area], 'code' => $code, 'name' => 'Meja '.$code, 'capacity' => $i % 3 === 0 ? 6 : 4, 'qr_token' => Table::generateToken(), 'status' => 'empty', 'pos_x' => $i % 4, 'pos_y' => intdiv($i, 4)]);
        }

        Supplier::create(['name' => 'CV Biji Kopi Nusantara', 'phone' => '081111111111', 'address' => 'Bandung']);
        Supplier::create(['name' => 'Toko Susu Segar Lembang', 'phone' => '082222222222', 'address' => 'Lembang']);
        Supplier::create(['name' => 'Pasar Induk', 'phone' => '083333333333', 'address' => 'Jakarta']);

        foreach (['Sewa', 'Listrik & Air', 'Gaji', 'Internet', 'Perawatan', 'Marketing', 'Lain-lain'] as $e) {
            ExpenseCategory::create(['name' => $e]);
        }

        Voucher::create(['code' => 'HEMAT10', 'name' => 'Diskon 10%', 'type' => 'percent', 'value' => 10, 'max_discount' => 20000, 'min_spend' => 50000, 'is_active' => true]);
        Voucher::create(['code' => 'NGOPI5K', 'name' => 'Potongan Rp5.000', 'type' => 'fixed', 'value' => 5000, 'min_spend' => 30000, 'usage_limit' => 100, 'is_active' => true]);
        HappyHour::create(['name' => 'Happy Hour Kopi Sore', 'days' => [1, 2, 3, 4, 5], 'start_time' => '14:00', 'end_time' => '16:00', 'type' => 'percent', 'value' => 15, 'category_ids' => [$cats['Kopi']], 'is_active' => false]);

        Customer::create(['name' => 'Budi Santoso', 'phone' => '6281234567801', 'points' => 120]);
        Customer::create(['name' => 'Sari Dewi', 'phone' => '6281234567802', 'points' => 40]);
    }
}
