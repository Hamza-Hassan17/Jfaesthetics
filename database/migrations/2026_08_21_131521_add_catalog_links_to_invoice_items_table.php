<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCatalogLinksToInvoiceItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropForeign(['medicine_id']);
            $table->dropColumn(['service_id', 'medicine_id']);
        });
    }
}
