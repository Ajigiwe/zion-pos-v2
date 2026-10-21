<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1: direct port of client/lib/core/database/tables.dart (Drift schema v8).
 *
 * Conventions kept from the Flutter app:
 *  - money in decimal(12,2) major units (was `real()`)
 *  - quantities in decimal(14,3) (was `real()`)
 *  - enum-ish fields as indexed strings so future values never need a
 *    schema change (was `textEnum<T>()`)
 *
 * Conventions changed for Laravel:
 *  - big-int primary keys; offline clients mint UUIDs into `uuid` /
 *    `idempotency_key` columns instead of UUID text PKs
 *  - `rev`/`dirty` sync columns are dropped entirely (no LAN sync)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->decimal('cost_price', 12, 2);
            $table->decimal('selling_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('reorder_level', 14, 3)->default(0);
            $table->string('tracking_type')->default('quantity'); // quantity|serialized
            $table->boolean('is_active')->default(true);
            // Cached current stock (ledger sum), re-derived after every movement.
            $table->decimal('stock_cached', 14, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->uuid('idempotency_key')->nullable()->unique(); // offline retries
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('till_id')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('payment_status')->default('PAID')->index();   // PAID|VOID|REFUNDED
            $table->string('sale_status')->default('COMPLETED')->index(); // COMPLETED|...
            $table->timestamps(); // replaces createdAt/updatedAt
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 12, 2); // snapshot at sale time
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2);
            $table->string('serial_number_id')->nullable(); // future serialized tracking
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->string('payment_method')->index(); // cash|mobile_money|card|bank_transfer|exchange_credit
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable(); // e.g. MoMo transaction id
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('original_sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('refund_method'); // PaymentMethod
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
        });

        Schema::create('exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('exchange_number')->unique();
            $table->foreignId('original_sale_id')->constrained('sales')->restrictOnDelete();
            $table->foreignId('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->foreignId('replacement_sale_id')->constrained('sales')->restrictOnDelete();
            $table->decimal('return_total', 12, 2);
            $table->decimal('replacement_total', 12, 2);
            $table->decimal('difference', 12, 2); // + customer pays / - cash back
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index(); // LOGIN|SALE|REFUND|EXCHANGE|STOCK_ADJUSTMENT|USER_CREATE|BACKUP
            $table->string('entity_type')->nullable();
            $table->string('entity_id')->nullable();
            $table->text('details')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('created_at')->index()->useCurrent();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('movement_type')->index(); // opening_stock|purchase|bulk_import|sale|refund|exchange_return|exchange_sale|damage|adjustment|transfer_in|transfer_out
            $table->decimal('quantity', 14, 3);       // signed: + in / - out
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->index()->useCurrent();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->string('import_type'); // PRODUCT_IMPORT|OPENING_STOCK|BULK_STOCK
            $table->string('mode');        // which §21 bulk mode produced it
            $table->string('file_name');
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('successful_rows');
            $table->unsignedInteger('failed_rows');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('COMPLETED'); // PENDING|COMPLETED|FAILED
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary(); // store.name, store.address, store.phone, ...
            $table->text('value');
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('exchanges');
        Schema::dropIfExists('refund_items');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
