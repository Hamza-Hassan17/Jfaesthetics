<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductInvoicesTables extends Migration
{
    public function up()
    {
        Schema::create('product_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('pi_no')->unique();
            // Optional link to a patient invoice; the patient then comes from it.
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('walkin_name')->nullable();
            $table->string('walkin_phone')->nullable();
            // manual = created on the Product Invoices screen; recipe = kept in
            // sync automatically with the linked invoice's service recipes.
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['invoice_id', 'source']);
        });

        Schema::create('product_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_invoice_id')->constrained('product_invoices')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            // The patient-invoice service line a recipe consumable belongs to.
            $table->unsignedBigInteger('service_line_id')->nullable();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->integer('quantity');
            $table->integer('stock_qty')->nullable();
            $table->unsignedInteger('qty_per_session')->nullable();
            $table->boolean('qty_overridden')->default(false);
            $table->boolean('is_billable')->default(true);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('discount_type')->default('flat');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('after_discount', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('service_line_id');
        });

        // Service lines created from now on get their recipe on a product
        // invoice. Older lines (false) are never given consumables after
        // the fact, so editing an old invoice doesn't deduct stock.
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->boolean('recipe_applied')->default(false)->after('qty_overridden');
        });

        Schema::create('product_invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_invoice_id')->constrained('product_invoices')->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode')->default('Cash');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down()
    {
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn('recipe_applied'));
        }
        Schema::dropIfExists('product_invoice_payments');
        Schema::dropIfExists('product_invoice_items');
        Schema::dropIfExists('product_invoices');
    }
}
