<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory spec decision 9 ("start fresh") + A + D: the Medicines Store's
 * data is intentionally discarded. invoice_items.medicine_id stays as an
 * unused nullable column; only its foreign key goes.
 */
class RemoveMedicinesStoreTables extends Migration
{
    public function up()
    {
        Schema::dropIfExists('stock_movements');

        // SQLite (test suite only) can't drop a foreign key without
        // rebuilding the table, so there the empty medicines table is left
        // in place for invoice_items.medicine_id to keep pointing at.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('medicines')->delete();
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['medicine_id']);
        });

        Schema::dropIfExists('medicines');
    }

    public function down()
    {
        // Irreversible by design: the old medicines data is not restored.
    }
}
