<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        // ---------- Menu ----------
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 50)->nullable()->unique();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('cost_cache')->default(0); // HPP dari resep
            $table->json('labels')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bundle')->default(false);
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'is_available']);
            $table->index('updated_at');
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('selection', ['single', 'multiple'])->default('single');
            $table->boolean('is_required')->default(false);
            $table->unsignedTinyInteger('min_select')->default(0);
            $table->unsignedTinyInteger('max_select')->default(1);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->bigInteger('price_delta')->default(0);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('menu_modifier_group', function (Blueprint $table) {
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->primary(['menu_id', 'modifier_group_id']);
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
        });

        // ---------- Meja ----------
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->unsignedTinyInteger('capacity')->default(4);
            $table->string('qr_token', 64)->unique();
            $table->enum('status', ['empty', 'occupied', 'awaiting_payment', 'needs_cleaning'])->default('empty');
            $table->unsignedSmallInteger('pos_x')->default(0);
            $table->unsignedSmallInteger('pos_y')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('status');
        });

        // ---------- Pelanggan & promo ----------
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 30)->unique();
            $table->string('email')->nullable();
            $table->integer('points')->default(0);
            $table->unsignedBigInteger('total_spent')->default(0);
            $table->unsignedInteger('visit_count')->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->enum('type', ['percent', 'fixed']);
            $table->unsignedBigInteger('value');
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedBigInteger('min_spend')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('happy_hours', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->json('days'); // 1=Senin..7=Minggu
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('type', ['percent', 'fixed']);
            $table->unsignedBigInteger('value');
            $table->json('category_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ---------- Shift ----------
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedBigInteger('opening_cash')->default(0);
            $table->unsignedBigInteger('cash_sales')->default(0);
            $table->unsignedBigInteger('non_cash_sales')->default(0);
            $table->unsignedBigInteger('cash_refunds')->default(0);
            $table->unsignedBigInteger('expected_cash')->default(0);
            $table->unsignedBigInteger('counted_cash')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->text('note')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // ---------- Transaksi ----------
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->unsignedInteger('last_invoice')->default(0);
            $table->unsignedInteger('last_queue')->default(0);
            $table->unsignedInteger('last_order')->default(0);
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->unique();
            $table->string('tracking_key', 40)->unique();
            $table->foreignId('parent_bill_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('merged_into_id')->nullable()->constrained('bills')->nullOnDelete();
            $table->foreignId('table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['dine_in', 'takeaway', 'delivery']);
            $table->unsignedInteger('queue_no')->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->text('delivery_address')->nullable();
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->enum('status', ['open', 'awaiting_payment', 'paid', 'void', 'refunded', 'merged'])->default('open');
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->enum('discount_type', ['percent', 'fixed'])->nullable();
            $table->unsignedBigInteger('discount_value')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0); // diskon manual
            $table->unsignedBigInteger('voucher_amount')->default(0);
            $table->unsignedBigInteger('promo_amount')->default(0); // happy hour
            $table->integer('points_redeemed')->default(0);
            $table->unsignedBigInteger('points_amount')->default(0);
            $table->unsignedBigInteger('service_charge')->default(0);
            $table->unsignedBigInteger('tax')->default(0);
            $table->bigInteger('rounding')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('change_amount')->default(0);
            $table->unsignedBigInteger('cost_total')->default(0);
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->string('note')->nullable();
            $table->boolean('bill_requested')->default(false);
            $table->boolean('stock_deducted')->default(false);
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('cleaned_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
            $table->index('created_at');
            $table->index('paid_at');
            $table->index(['table_id', 'status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order_no');
            $table->enum('source', ['qr', 'link', 'staff']);
            $table->enum('status', ['pending_confirmation', 'confirmed', 'rejected', 'cancelled'])->default('confirmed');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('client_session', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('note')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('menu_name', 150);
            $table->unsignedBigInteger('unit_price');
            $table->bigInteger('modifiers_total')->default(0);
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('line_total');
            $table->unsignedBigInteger('cost_snapshot')->default(0); // HPP per porsi
            $table->string('note', 255)->nullable();
            $table->enum('status', ['new', 'processing', 'ready', 'served', 'cancelled'])->default('new');
            $table->boolean('stock_deducted')->default(false);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('served_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['station_id', 'status', 'updated_at']);
            $table->index(['bill_id', 'status']);
            $table->index('updated_at');
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_name', 100);
            $table->string('option_name', 100);
            $table->bigInteger('price_delta')->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('method', ['cash', 'qris', 'transfer', 'card', 'gateway']);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('received')->default(0);
            $table->unsignedBigInteger('change')->default(0);
            $table->string('reference', 100)->nullable();
            $table->string('proof_image')->nullable();
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_ref', 100)->nullable()->unique();
            $table->json('gateway_payload')->nullable();
            $table->string('gateway_qr', 2000)->nullable();
            $table->string('gateway_url', 500)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded'])->default('paid');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['method', 'paid_at']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->enum('method', ['cash', 'qris', 'transfer', 'card', 'gateway'])->default('cash');
            $table->string('reason');
            $table->boolean('restock')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('table_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['waiter', 'bill']);
            $table->enum('status', ['open', 'handled'])->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });

        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('points');
            $table->enum('type', ['earn', 'redeem', 'adjust', 'reverse']);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // ---------- Stok ----------
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('symbol', 10)->unique();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->decimal('stock', 14, 3)->default(0);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->decimal('cost_per_unit', 14, 4)->default(0);
            $table->boolean('is_key')->default(false); // bahan utama -> auto habis
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3);
            $table->unique(['menu_id', 'ingredient_id']);
        });

        Schema::create('modifier_option_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 3); // bisa negatif (misal No Sugar)
            $table->unique(['modifier_option_id', 'ingredient_id']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_no', 40)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('total')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('date');
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 14, 3);
            $table->decimal('unit_cost', 14, 4);
            $table->unsignedBigInteger('subtotal');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['purchase', 'sale', 'adjustment', 'opname', 'waste', 'return']);
            $table->decimal('qty', 14, 3);
            $table->decimal('stock_after', 14, 3);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ingredient_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_no', 40)->unique();
            $table->date('date');
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 14, 3);
            $table->decimal('counted_qty', 14, 3);
            $table->decimal('difference', 14, 3);
        });

        // ---------- Biaya ----------
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('date');
        });
    }

    public function down(): void
    {
        foreach ([
            'expenses', 'expense_categories', 'stock_opname_items', 'stock_opnames', 'stock_movements',
            'purchase_items', 'purchases', 'modifier_option_recipes', 'recipes', 'ingredients', 'suppliers', 'units',
            'point_transactions', 'voucher_usages', 'table_calls', 'refunds', 'payments', 'order_item_modifiers',
            'order_items', 'orders', 'bills', 'invoice_counters', 'shifts', 'happy_hours', 'vouchers', 'customers',
            'tables', 'areas', 'bundle_items', 'menu_modifier_group', 'modifier_options', 'modifier_groups',
            'menus', 'categories', 'stations', 'activity_logs', 'settings',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
