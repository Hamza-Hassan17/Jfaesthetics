<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMedilifeFieldsToAppointmentsTable extends Migration
{
    public function up()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('age');
            // Stored per-row (not just hardcoded in the UI) so historical
            // records stay accurate even if the fee changes in future.
            $table->unsignedInteger('consultation_fee')->default(1500)->after('phone');
        });
    }

    public function down()
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['phone', 'consultation_fee']);
        });
    }
}
