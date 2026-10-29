<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Per-user access to the sister app (Aesthetics <-> Dental), set by a Super Admin. */
class AddCanSwitchAppToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_switch_app')->default(false)->after('is_active');
        });
    }

    public function down()
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_switch_app'));
        }
    }
}
