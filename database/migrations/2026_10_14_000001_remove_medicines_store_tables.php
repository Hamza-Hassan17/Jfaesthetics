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

        // Drop whatever foreign keys actually reference `medicines` on this
        // database (the live one has none on invoice_items), by real name.
        $foreignKeys = DB::select(
            'SELECT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?',
            ['medicines']
        );
        foreach ($foreignKeys as $fk) {
            DB::statement("ALTER TABLE `{$fk->TABLE_NAME}` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        Schema::dropIfExists('medicines');
    }

    public function down()
    {
        // Irreversible by design: the old medicines data is not restored.
    }
}
