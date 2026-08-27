<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConsultationFormsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('consultation_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('consultant_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->date('consultation_date');
            $table->json('consultation_for')->nullable();
            $table->json('medical_history')->nullable();
            $table->string('medical_history_other')->nullable();
            $table->json('female_status')->nullable();
            $table->boolean('declaration_confirmed')->default(false);
            $table->string('patient_signature_name')->nullable();
            $table->text('recommended_treatment')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('consultation_forms', function (Blueprint $table) {
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['consultant_id']);
        });
        Schema::dropIfExists('consultation_forms');
    }
}
