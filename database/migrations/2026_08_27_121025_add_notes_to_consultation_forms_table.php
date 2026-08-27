<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotesToConsultationFormsTable extends Migration
{
    public function up()
    {
        Schema::table('consultation_forms', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('recommended_treatment');
        });
    }

    public function down()
    {
        Schema::table('consultation_forms', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
}
