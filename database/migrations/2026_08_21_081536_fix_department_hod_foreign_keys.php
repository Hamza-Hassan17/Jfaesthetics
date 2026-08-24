<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixDepartmentHodForeignKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('hod_id')->references('id')->on('hods');
            $table->foreign('block_id')->references('id')->on('blocks');
        });

        Schema::table('hods', function (Blueprint $table) {
            $table->foreign('doctor_id')->references('id')->on('doctors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['hod_id']);
            $table->dropForeign(['block_id']);
        });

        Schema::table('hods', function (Blueprint $table) {
            $table->dropForeign(['doctor_id']);
        });
    }
}
