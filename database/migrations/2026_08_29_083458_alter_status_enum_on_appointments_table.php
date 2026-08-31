<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AlterStatusEnumOnAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Production was hotfixed directly with this raw MySQL ALTER before
     * this file made it back into the repo (see the previous migration,
     * which converts the same column to a plain string precisely to
     * avoid needing an ENUM rebuild) — kept here so migrate:status
     * matches what's actually live, guarded to MySQL only since
     * `MODIFY` isn't valid SQLite syntax and would break the SQLite
     * test suite.
     *
     * @return void
     */
    public function up()
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY status ENUM('pending','booked','completed','cancelled','no_show') NOT NULL DEFAULT 'booked'");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY status ENUM('pending','completed') NOT NULL DEFAULT 'pending'");
        }
    }
}