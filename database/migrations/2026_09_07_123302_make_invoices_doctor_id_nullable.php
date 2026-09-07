<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakeInvoicesDoctorIdNullable extends Migration
{
    /**
     * The Doctor field was removed from invoice creation, so
     * invoices.doctor_id (a NOT NULL foreign key) needs to allow null.
     * Widening a foreign key column needs doctrine/dbal for
     * ->nullable()->change(), which isn't installed here, so this
     * shuffles through a temporary column instead (same approach used
     * for appointments.status and employees/nurses.email) - plain
     * ADD/DROP COLUMN only, works identically on MySQL and SQLite.
     */
    public function up()
    {
        /**
         * SQLite's ALTER TABLE has no DROP FOREIGN KEY at all (Laravel's
         * grammar rejects dropForeign() outright there), so the FK
         * drop/recreate is MySQL-only; the SQLite test database never had
         * FK enforcement relied on elsewhere in this app anyway.
         */
        $isMysql = DB::connection()->getDriverName() === 'mysql';

        if ($isMysql) {
            // Discovered by name rather than assuming Laravel's default
            // ("invoices_doctor_id_foreign") - production doesn't actually
            // have a constraint under that name, so dropForeign(['doctor_id'])
            // fails there with "Can't DROP FOREIGN KEY ... check that it exists".
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'invoices'
                  AND COLUMN_NAME = 'doctor_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE invoices DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
            }
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id_new')->nullable()->after('doctor_id');
        });

        DB::table('invoices')->update(['doctor_id_new' => DB::raw('doctor_id')]);

        DB::statement('ALTER TABLE invoices DROP COLUMN doctor_id');

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id')->nullable()->after('patient_id');
        });

        DB::table('invoices')->update(['doctor_id' => DB::raw('doctor_id_new')]);

        DB::statement('ALTER TABLE invoices DROP COLUMN doctor_id_new');

        if ($isMysql) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('doctor_id')->references('id')->on('doctors')->nullOnDelete();
            });
        }
    }

    /**
     * Not reversed back to NOT NULL — any invoice saved without a
     * doctor since this ran would violate that constraint, and there's
     * no meaningful doctor to backfill it with.
     */
    public function down()
    {
        //
    }
}
