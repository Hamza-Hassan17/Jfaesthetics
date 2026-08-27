<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddDoctorVisitFieldsToAppointmentsTable extends Migration
{
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('case_no')->nullable()->after('patient_id');
            $table->unsignedInteger('age')->nullable()->after('doctor_id');
            $table->string('location')->nullable()->after('age');
        });

        // Widen prescription (used as "Notes / Rx") from a short string to a
        // full text field to match the ruled writing area on the paper form.
        // Raw SQL avoids needing doctrine/dbal just for a column type change,
        // but MODIFY COLUMN is MySQL-only syntax — SQLite (used by the test
        // suite) doesn't support it and doesn't enforce column length
        // anyway, so it's safe to skip there.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY prescription TEXT NULL');
        }
    }

    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['case_no', 'age', 'location']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY prescription VARCHAR(255) NULL');
        }
    }
}
