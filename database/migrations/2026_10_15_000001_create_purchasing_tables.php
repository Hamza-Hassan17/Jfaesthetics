<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePurchasingTables extends Migration
{
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_no')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('closed_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->integer('qty_ordered');
            $table->integer('qty_received')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_no')->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_bill_no')->nullable();
            $table->date('receive_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_status')->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained('purchase_order_items');
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->string('batch_no');
            $table->date('expiry_date')->nullable();
            $table->integer('qty');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->foreignId('batch_id')->nullable()->constrained('item_batches');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_no')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('mode');
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // goods_receipt_id null = allocated to the supplier's opening balance.
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_payment_id')->constrained('supplier_payments')->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts');
            $table->decimal('amount', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplier_payment_allocations');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
}
