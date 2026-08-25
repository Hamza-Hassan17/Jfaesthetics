<?php

namespace Database\Seeders;

use App\Models\patient;
use Illuminate\Database\Seeder;

class WalkInPatientSeeder extends Seeder
{
    /**
     * Creates a generic "Walk in Patient" record for billing patients who
     * don't need a full patient profile (point-of-sale style placeholder).
     *
     * Run standalone: php artisan db:seed --class=WalkInPatientSeeder
     */
    public function run()
    {
        patient::firstOrCreate(['name' => 'Walk in Patient']);
    }
}
