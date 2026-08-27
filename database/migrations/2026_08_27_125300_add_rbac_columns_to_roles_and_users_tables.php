<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRbacColumnsToRolesAndUsersTables extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            // 0 = highest authority (Super Admin), larger = lower authority.
            $table->unsignedTinyInteger('rank')->default(2)->after('name');
            // Built-in roles (Super Admin, Admin, Doctor, Receptionist, Accountant)
            // cannot be deleted.
            $table->boolean('is_system_role')->default(false)->after('rank');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role_id');
            $table->foreignId('doctor_id')->nullable()->after('is_active')->constrained('doctors')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['doctor_id']);
            $table->dropColumn(['is_active', 'doctor_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['rank', 'is_system_role']);
        });
    }
}
