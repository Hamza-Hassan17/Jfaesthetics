<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixMedicinesQuantityAndPriceColumnTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = DB::connection()->getDriverName();
        $intCast = $driver === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
        $decimalCast = $driver === 'sqlite' ? 'REAL' : 'DECIMAL(12,2)';

        Schema::table('medicines', function (Blueprint $table) {
            $table->integer('quantity_new')->default(0)->after('quantity');
            $table->decimal('price_new', 12, 2)->default(0)->after('price');
        });

        DB::statement("UPDATE medicines SET quantity_new = CAST(quantity AS {$intCast}), price_new = CAST(price AS {$decimalCast})");

        DB::statement('ALTER TABLE medicines DROP COLUMN quantity');
        DB::statement('ALTER TABLE medicines DROP COLUMN price');

        if ($driver === 'sqlite') {
            DB::statement('ALTER TABLE medicines RENAME COLUMN quantity_new TO quantity');
            DB::statement('ALTER TABLE medicines RENAME COLUMN price_new TO price');
        } else {
            DB::statement('ALTER TABLE medicines CHANGE quantity_new quantity INT NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE medicines CHANGE price_new price DECIMAL(12,2) NOT NULL DEFAULT 0');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $driver = DB::connection()->getDriverName();

        Schema::table('medicines', function (Blueprint $table) {
            $table->string('quantity_old')->default('0')->after('quantity');
            $table->string('price_old')->default('0')->after('price');
        });

        DB::statement('UPDATE medicines SET quantity_old = quantity, price_old = price');

        DB::statement('ALTER TABLE medicines DROP COLUMN quantity');
        DB::statement('ALTER TABLE medicines DROP COLUMN price');

        if ($driver === 'sqlite') {
            DB::statement('ALTER TABLE medicines RENAME COLUMN quantity_old TO quantity');
            DB::statement('ALTER TABLE medicines RENAME COLUMN price_old TO price');
        } else {
            DB::statement('ALTER TABLE medicines CHANGE quantity_old quantity VARCHAR(255) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE medicines CHANGE price_old price VARCHAR(255) NOT NULL DEFAULT 0');
        }
    }
}
