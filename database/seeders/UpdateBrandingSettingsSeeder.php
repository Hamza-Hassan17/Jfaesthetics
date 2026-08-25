<?php

namespace Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class UpdateBrandingSettingsSeeder extends Seeder
{
    /**
     * Updates existing settings rows (or creates them if missing) with the
     * new branding details. Uses updateOrCreate keyed on 'key' only, unlike
     * SettingSeeder's firstOrCreate(key+value) which can't fix an existing
     * row that already holds a different value.
     *
     * Run standalone: php artisan db:seed --class=UpdateBrandingSettingsSeeder
     */
    public function run()
    {
        $updates = [
            'address' => 'Plot 63C-1, 24th Street, Touheed Commercial Area, Phase-5, D.H.A., Karachi.',
            'business_phone' => '03041110652',
            'website' => 'www.jfaesthetic.pk',
        ];

        foreach ($updates as $key => $value) {
            Settings::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
