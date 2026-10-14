<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateServiceRecipesAndLineTracking extends Migration
{
    public function up()
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->unsignedInteger('qty_per_session');
            $table->boolean('is_billable')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'item_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            // Recipe snapshot copied onto a child line when it is created.
            $table->unsignedInteger('qty_per_session')->nullable()->after('session_number');
            // Staff typed the qty by hand: sessions changes no longer recalculate it.
            $table->boolean('qty_overridden')->default(false)->after('qty_per_session');
            // Qty actually taken from stock (≤ billed quantity after a stock-only reduction).
            $table->integer('stock_qty')->nullable()->after('quantity');
            $table->unsignedInteger('position')->default(0)->after('invoice_id');
        });

        DB::table('invoice_items')->where('line_type', 'product')->update(['stock_qty' => DB::raw('quantity')]);
        DB::table('invoice_items')->update(['position' => DB::raw('id')]);

        // Which batch (and at what cost) later covered stock that was sold
        // into negative. One shortfall can be settled by several batches.
        Schema::create('shortfall_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('owner_line_id')->nullable();
            $table->foreignId('batch_id')->constrained('item_batches');
            $table->string('settled_by_type');
            $table->unsignedBigInteger('settled_by_id');
            $table->unsignedBigInteger('settled_by_line_id')->nullable();
            $table->integer('qty');
            $table->decimal('unit_cost', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['owner_type', 'owner_id', 'owner_line_id']);
            $table->index(['settled_by_type', 'settled_by_id', 'settled_by_line_id'], 'shortfall_settlements_settled_by_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shortfall_settlements');
        Schema::dropIfExists('service_items');

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->dropColumn(['qty_per_session', 'qty_overridden', 'stock_qty', 'position']);
            });
        }
    }
}
