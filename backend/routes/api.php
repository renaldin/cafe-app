<?php

use App\Http\Controllers\Api\V1\Public\PublicController;
use App\Http\Controllers\Api\V1\Staff\ActivityLogController;
use App\Http\Controllers\Api\V1\Staff\AreaController;
use App\Http\Controllers\Api\V1\Staff\AuthController;
use App\Http\Controllers\Api\V1\Staff\BillController;
use App\Http\Controllers\Api\V1\Staff\CategoryController;
use App\Http\Controllers\Api\V1\Staff\CustomerController;
use App\Http\Controllers\Api\V1\Staff\DashboardController;
use App\Http\Controllers\Api\V1\Staff\ExpenseController;
use App\Http\Controllers\Api\V1\Staff\IngredientController;
use App\Http\Controllers\Api\V1\Staff\KdsController;
use App\Http\Controllers\Api\V1\Staff\MenuController;
use App\Http\Controllers\Api\V1\Staff\ModifierGroupController;
use App\Http\Controllers\Api\V1\Staff\OrderController;
use App\Http\Controllers\Api\V1\Staff\PurchaseController;
use App\Http\Controllers\Api\V1\Staff\ReportController;
use App\Http\Controllers\Api\V1\Staff\RoleController;
use App\Http\Controllers\Api\V1\Staff\SettingController;
use App\Http\Controllers\Api\V1\Staff\ShiftController;
use App\Http\Controllers\Api\V1\Staff\StockController;
use App\Http\Controllers\Api\V1\Staff\SupplierController;
use App\Http\Controllers\Api\V1\Staff\TableController;
use App\Http\Controllers\Api\V1\Staff\UserController;
use App\Http\Controllers\Api\V1\Staff\VoucherController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // =========================== PUBLIK (tanpa login) ===========================
    Route::prefix('public')->middleware('throttle:public')->group(function () {
        Route::get('store', [PublicController::class, 'store']);
        Route::get('menu', [PublicController::class, 'menu']);
        Route::get('tables/{token}', [PublicController::class, 'table'])->where('token', '[A-Za-z0-9]{48}');
        Route::post('tables/{token}/call', [PublicController::class, 'call'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:public-action');
        Route::post('orders', [PublicController::class, 'placeOrder'])->middleware('throttle:public-order');
        Route::get('track/{key}', [PublicController::class, 'track'])->where('key', '[a-z0-9]{32}');
        Route::post('track/{key}/pay', [PublicController::class, 'payOnline'])->where('key', '[a-z0-9]{32}')->middleware('throttle:public-action');
        Route::get('track/{key}/payments/{payment}', [PublicController::class, 'paymentStatus'])->where('key', '[a-z0-9]{32}')->whereNumber('payment');
    });
    Route::post('public/webhooks/{gateway}', [PublicController::class, 'webhook'])->whereIn('gateway', ['midtrans', 'xendit'])->middleware('throttle:120,1');

    // =========================== STAF ===========================
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active', 'throttle:staff'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::put('auth/password', [AuthController::class, 'password']);

        // Dashboard
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])->middleware('can:dashboard.view');
        Route::get('dashboard/charts', [DashboardController::class, 'charts'])->middleware('can:dashboard.view');

        // Menu & katalog
        Route::get('stations', [CategoryController::class, 'stations']);
        Route::get('categories', [CategoryController::class, 'index'])->middleware('can:menu.view');
        Route::middleware('can:menu.manage')->group(function () {
            Route::post('categories', [CategoryController::class, 'store']);
            Route::put('categories/{category}', [CategoryController::class, 'update']);
            Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
            Route::post('menus', [MenuController::class, 'store']);
            Route::put('menus/{menu}', [MenuController::class, 'update']);
            Route::delete('menus/{menu}', [MenuController::class, 'destroy']);
            Route::post('menus/{menu}/image', [MenuController::class, 'image']);
            Route::get('modifier-groups', [ModifierGroupController::class, 'index']);
            Route::post('modifier-groups', [ModifierGroupController::class, 'store']);
            Route::put('modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'update']);
            Route::delete('modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'destroy']);
        });
        Route::middleware('can:menu.import_export')->group(function () {
            Route::get('menus/export', [MenuController::class, 'export']);
            Route::post('menus/import', [MenuController::class, 'import']);
        });
        Route::get('menus', [MenuController::class, 'index'])->middleware('can:menu.view');
        Route::get('menus/{menu}', [MenuController::class, 'show'])->middleware('can:menu.view');
        Route::patch('menus/{menu}/availability', [MenuController::class, 'availability'])->middleware('can:menu.toggle');
        Route::put('menus/{menu}/recipe', [MenuController::class, 'recipe'])->middleware('can:recipe.manage');

        // Meja
        Route::get('tables', [TableController::class, 'index'])->middleware('can:table.view');
        Route::get('areas', [AreaController::class, 'index'])->middleware('can:table.view');
        Route::middleware('can:table.manage')->group(function () {
            Route::post('tables', [TableController::class, 'store']);
            Route::put('tables/{table}', [TableController::class, 'update']);
            Route::delete('tables/{table}', [TableController::class, 'destroy']);
            Route::post('areas', [AreaController::class, 'store']);
            Route::put('areas/{area}', [AreaController::class, 'update']);
            Route::delete('areas/{area}', [AreaController::class, 'destroy']);
        });
        Route::post('tables/{table}/regenerate-token', [TableController::class, 'regenerate'])->middleware('can:table.regenerate_qr');
        Route::patch('tables/{table}/status', [TableController::class, 'status'])->middleware('can:table.status');
        Route::get('table-calls', [TableController::class, 'calls'])->middleware('can:order.view');
        Route::patch('table-calls/{call}', [TableController::class, 'handleCall'])->middleware('can:order.view');

        // Pesanan
        Route::get('orders', [OrderController::class, 'index'])->middleware('can:order.view');
        Route::get('orders/feed', [OrderController::class, 'feed'])->middleware('can:order.view');
        Route::post('orders', [OrderController::class, 'store'])->middleware('can:order.create');
        Route::post('orders/{order}/confirm', [OrderController::class, 'confirm'])->middleware('can:order.confirm');
        Route::post('orders/{order}/reject', [OrderController::class, 'reject'])->middleware('can:order.confirm');
        Route::delete('order-items/{item}', [OrderController::class, 'cancelItem'])->middleware('can:bill.manage');

        // KDS
        Route::get('kds', [KdsController::class, 'index'])->middleware('can:kds.view');
        Route::patch('kds/items/{item}', [KdsController::class, 'updateItem'])->middleware('can:kds.update');
        Route::patch('kds/orders/{order}', [KdsController::class, 'updateOrder'])->middleware('can:kds.update');

        // Bill & kasir
        Route::get('bills', [BillController::class, 'index'])->middleware('can:bill.view');
        Route::get('bills/{bill}', [BillController::class, 'show'])->middleware('can:bill.view');
        Route::get('bills/{bill}/receipt', [BillController::class, 'receipt'])->middleware('can:bill.view');
        Route::post('bills/{bill}/request-bill', [BillController::class, 'requestBill'])->middleware('can:order.create');
        Route::middleware('can:bill.manage')->group(function () {
            Route::post('bills/{bill}/voucher', [BillController::class, 'voucher']);
            Route::post('bills/{bill}/points', [BillController::class, 'points']);
            Route::post('bills/{bill}/customer', [BillController::class, 'customer']);
            Route::post('bills/{bill}/split', [BillController::class, 'split']);
            Route::post('bills/{bill}/delivery', [BillController::class, 'delivery']);
            Route::post('bills/{bill}/merge', [BillController::class, 'merge']);
            Route::post('bills/{bill}/move-table', [BillController::class, 'moveTable']);
        });
        Route::post('bills/{bill}/discount', [BillController::class, 'discount'])->middleware('can:bill.discount');
        Route::post('bills/{bill}/payments', [BillController::class, 'pay'])->middleware(['can:payment.process', 'shift']);
        Route::post('bills/{bill}/void', [BillController::class, 'void'])->middleware('can:bill.void');
        Route::post('bills/{bill}/refund', [BillController::class, 'refund'])->middleware('can:bill.refund');

        // Shift
        Route::get('shifts/current', [ShiftController::class, 'current'])->middleware('can:shift.manage');
        Route::post('shifts/open', [ShiftController::class, 'open'])->middleware('can:shift.manage');
        Route::post('shifts/{shift}/close', [ShiftController::class, 'close'])->middleware('can:shift.manage');
        Route::get('shifts', [ShiftController::class, 'index'])->middleware('can:shift.manage');
        Route::get('shifts/{shift}', [ShiftController::class, 'show'])->middleware('can:shift.manage');

        // Stok
        Route::get('units', [IngredientController::class, 'units'])->middleware('can:stock.view');
        Route::get('ingredients', [IngredientController::class, 'index'])->middleware('can:stock.view');
        Route::get('stock/movements', [StockController::class, 'movements'])->middleware('can:stock.view');
        Route::get('stock/low', [StockController::class, 'low'])->middleware('can:stock.view');
        Route::get('stock-opnames', [StockController::class, 'opnames'])->middleware('can:stock.view');
        Route::get('stock-opnames/{opname}', [StockController::class, 'opname'])->middleware('can:stock.view');
        Route::get('purchases', [PurchaseController::class, 'index'])->middleware('can:stock.view');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('can:stock.view');
        Route::middleware('can:stock.manage')->group(function () {
            Route::post('ingredients', [IngredientController::class, 'store']);
            Route::put('ingredients/{ingredient}', [IngredientController::class, 'update']);
            Route::delete('ingredients/{ingredient}', [IngredientController::class, 'destroy']);
            Route::post('purchases', [PurchaseController::class, 'store']);
            Route::post('stock/adjustments', [StockController::class, 'adjust']);
            Route::post('stock/waste', [StockController::class, 'waste']);
            Route::post('stock-opnames', [StockController::class, 'storeOpname']);
            Route::post('stock-opnames/{opname}/finalize', [StockController::class, 'finalizeOpname']);
        });
        Route::get('suppliers', [SupplierController::class, 'index'])->middleware('can:stock.view');
        Route::middleware('can:supplier.manage')->group(function () {
            Route::post('suppliers', [SupplierController::class, 'store']);
            Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        });

        // Pelanggan & promo
        Route::get('customers', [CustomerController::class, 'index'])->middleware('can:customer.view');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('can:customer.view');
        Route::middleware('can:customer.manage')->group(function () {
            Route::post('customers', [CustomerController::class, 'store']);
            Route::put('customers/{customer}', [CustomerController::class, 'update']);
            Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
            Route::post('customers/{customer}/points', [CustomerController::class, 'adjustPoints']);
        });
        Route::post('vouchers/check', [VoucherController::class, 'check'])->middleware('can:bill.manage');
        Route::middleware('can:promo.manage')->group(function () {
            Route::get('vouchers', [VoucherController::class, 'index']);
            Route::post('vouchers', [VoucherController::class, 'store']);
            Route::put('vouchers/{voucher}', [VoucherController::class, 'update']);
            Route::delete('vouchers/{voucher}', [VoucherController::class, 'destroy']);
            Route::get('happy-hours', [VoucherController::class, 'happyHours']);
            Route::post('happy-hours', [VoucherController::class, 'storeHappyHour']);
            Route::put('happy-hours/{happyHour}', [VoucherController::class, 'updateHappyHour']);
            Route::delete('happy-hours/{happyHour}', [VoucherController::class, 'destroyHappyHour']);
        });

        // Biaya operasional
        Route::middleware('can:expense.manage')->group(function () {
            Route::get('expense-categories', [ExpenseController::class, 'categories']);
            Route::post('expense-categories', [ExpenseController::class, 'storeCategory']);
            Route::delete('expense-categories/{expenseCategory}', [ExpenseController::class, 'destroyCategory']);
            Route::get('expenses', [ExpenseController::class, 'index']);
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
        });

        // Laporan
        Route::get('reports', [ReportController::class, 'types'])->middleware('can:report.view');
        Route::get('reports/{type}', [ReportController::class, 'show'])->middleware('can:report.view');

        // Administrasi
        Route::middleware('can:user.manage')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::put('users/{user}', [UserController::class, 'update']);
            Route::delete('users/{user}', [UserController::class, 'destroy']);
            Route::get('roles/list', [RoleController::class, 'index']);
        });
        Route::middleware('can:role.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);
        });
        Route::get('settings', [SettingController::class, 'show'])->middleware('can:setting.manage');
        Route::put('settings', [SettingController::class, 'update'])->middleware('can:setting.manage');
        Route::post('settings/upload', [SettingController::class, 'upload'])->middleware('can:setting.manage');
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->middleware('can:log.view');
    });
});
