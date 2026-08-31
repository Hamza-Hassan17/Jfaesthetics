<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeEmployeesAndNursesEmailNullable extends Migration
{
    /**
     * employees.email and nurses.email were both NOT NULL — email is now
     * optional across the data-entry forms (Patient, Employee/Doctor,
     * Nurse, Requested Appointments), so the columns need to allow it too.
     * patients.email and requested_appointments.email are already
     * nullable at the DB level.
     *
     * Widening a column's nullability needs doctrine/dbal for
     * ->nullable()->change(), which isn't installed here, so this
     * shuffles through a temporary column instead (same approach as
     * the appointments.status migration) — plain ADD/DROP COLUMN only,
     * no ALTER COLUMN, so it works identically on MySQL and SQLite.
     */
    public function up()
    {
        $this->makeNullable('employees');
        $this->makeNullable('nurses');
    }

    public function down()
    {
        // Deliberately not reversed — going back to NOT NULL would fail
        // on any row that picked up a null email in the meantime, and
        // there's no meaningful placeholder value to backfill it with.
    }

    private function makeNullable(string $table)
    {
        Schema::table($table, function (Blueprint $blueprint) use ($table) {
            $blueprint->string('email_new')->nullable()->after('email');
        });

        DB::table($table)->update(['email_new' => DB::raw('email')]);

        DB::statement("ALTER TABLE {$table} DROP COLUMN email");

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->string('email')->nullable()->after('name');
        });

        DB::table($table)->update(['email' => DB::raw('email_new')]);

        DB::statement("ALTER TABLE {$table} DROP COLUMN email_new");
    }
}
