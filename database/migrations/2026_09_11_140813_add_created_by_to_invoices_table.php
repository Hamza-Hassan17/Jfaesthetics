<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreatedByToInvoicesTable extends Migration
{
    /**
     * Tracks which user actually created each invoice, so non-admin roles
     * can be scoped to only their own invoices. printed_by (a plain name
     * string) already existed but isn't reliable for this - it's not a
     * real foreign key, so it breaks on renames/duplicate names. This is
     * a brand-new nullable column (not widening an existing one), so no
     * doctrine/dbal-avoiding shuffle needed here.
     */
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('doctor_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
}
