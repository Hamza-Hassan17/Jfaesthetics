<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExtendAppointmentStatusLifecycle extends Migration
{
    /**
     * Extends appointments.status from ('pending','completed') to a real
     * lifecycle: ('booked','completed','cancelled','no_show') — needed for
     * the Appointment Report to show real activity, not just paid visits.
     * Existing 'pending' rows become 'booked' (same meaning, new name).
     *
     * Converts the column from a DB-level ENUM to a plain string, with the
     * allowed values enforced in app code (validation) instead — an ENUM
     * needs a full column rebuild to change on both MySQL and SQLite
     * (doctrine/dbal isn't installed here for the former; SQLite bakes the
     * ENUM in as a CHECK constraint at table-creation time for the
     * latter), and a plain string sidesteps needing dbal for any future
     * status list change too.
     *
     * Renaming a column also needs dbal, so this shuffles through a
     * temporary column instead of a straight rename. The drop steps use
     * raw SQL rather than Blueprint::dropColumn() — Laravel 8's SQLite
     * grammar routes dropColumn() through doctrine/dbal to rebuild the
     * table, which isn't installed here either; a raw ALTER TABLE ...
     * DROP COLUMN works natively on both MySQL and SQLite 3.35+.
     */
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status_new', 20)->nullable()->after('status');
        });

        DB::table('appointments')->where('status', 'pending')->update(['status_new' => 'booked']);
        DB::table('appointments')->where('status', 'completed')->update(['status_new' => 'completed']);
        DB::table('appointments')->whereNull('status_new')->update(['status_new' => 'booked']);

        DB::statement('ALTER TABLE appointments DROP COLUMN status');

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status', 20)->default('booked')->after('phone');
        });

        DB::table('appointments')->update(['status' => DB::raw('status_new')]);

        DB::statement('ALTER TABLE appointments DROP COLUMN status_new');
    }

    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status_old', 20)->nullable()->after('status');
        });

        DB::table('appointments')->whereIn('status', ['cancelled', 'no_show', 'booked'])->update(['status_old' => 'pending']);
        DB::table('appointments')->where('status', 'completed')->update(['status_old' => 'completed']);

        DB::statement('ALTER TABLE appointments DROP COLUMN status');

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('phone');
        });

        DB::table('appointments')->update(['status' => DB::raw('status_old')]);

        DB::statement('ALTER TABLE appointments DROP COLUMN status_old');
    }
}
