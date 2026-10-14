<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddInventoryColumnsToInvoiceItemsTable extends Migration
{
    public function up()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('line_type')->default('custom')->after('invoice_id');
            $table->foreignId('inventory_item_id')->nullable()->after('medicine_id')->constrained('inventory_items')->nullOnDelete();
            $table->unsignedBigInteger('parent_line_id')->nullable()->after('inventory_item_id');
            $table->boolean('is_billable')->default(true)->after('parent_line_id');
            $table->decimal('unit_cost', 12, 2)->nullable()->after('is_billable');
        });

        // Old medicine lines become custom: they keep their name and price
        // but no longer point at stock (spec decision 9: start fresh).
        DB::table('invoice_items')->whereNotNull('service_id')->update(['line_type' => 'service']);
    }

    public function down()
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['inventory_item_id']);
            $table->dropColumn(['line_type', 'inventory_item_id', 'parent_line_id', 'is_billable', 'unit_cost']);
        });
    }
}
