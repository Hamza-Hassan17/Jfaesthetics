<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RedesignConsultationFormsFields extends Migration
{
    /**
     * Consultation For / Medical History move from multi-select checkboxes
     * (JSON array) to single-select dropdowns (plain string) per redesign.
     *
     * The old JSON columns are only dropped on MySQL — Schema::
     * dropColumn() on SQLite (used by the test suite) needs doctrine/dbal
     * to rebuild the table, and that package's current version can't be
     * installed here without breaking carbon-doctrine-types' constraint on
     * this Laravel 8 lockfile. Skipping the drop on SQLite just leaves two
     * inert unused columns there; the model's accessors/mutators only ever
     * read/write the new *_single columns either way.
     */
    public function up()
    {
        Schema::table('consultation_forms', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('patient_id');
            $table->string('consultation_for_single')->nullable()->after('consultation_for');
            $table->string('medical_history_single')->nullable()->after('medical_history');
        });

        foreach (DB::table('consultation_forms')->get(['id', 'consultation_for', 'medical_history']) as $row) {
            $consultationFor = json_decode($row->consultation_for ?? '[]', true) ?: [];
            $medicalHistory = json_decode($row->medical_history ?? '[]', true) ?: [];

            DB::table('consultation_forms')->where('id', $row->id)->update([
                'consultation_for_single' => $consultationFor[0] ?? null,
                'medical_history_single' => $medicalHistory[0] ?? null,
            ]);
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('consultation_forms', function (Blueprint $table) {
                $table->dropColumn(['consultation_for', 'medical_history']);
            });
        }
    }

    public function down()
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            Schema::table('consultation_forms', function (Blueprint $table) {
                $table->json('consultation_for')->nullable();
                $table->json('medical_history')->nullable();
            });
        }

        Schema::table('consultation_forms', function (Blueprint $table) {
            $table->dropColumn(['phone', 'consultation_for_single', 'medical_history_single']);
        });
    }
}
