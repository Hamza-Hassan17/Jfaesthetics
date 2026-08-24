<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $settings = [
            [
                'key' => 'title',
                'value' => 'JF Aesthetics'
            ],
            [
                'key' => 'business_email',
                'value' => 'info@jfaesthetics.pk'
            ],
            [
                'key' => 'address',
                'value' => 'Defence Phase 5, Near Medicare Clinic, Karachi'
            ],
            [
                'key' => 'business_phone',
                'value' => '03327841753'
            ],
            [
                'key' => 'working_horse',
                'value' => '12:00 PM - 9:00 PM'
            ],
            [
                'key' => 'description',
                'value' => 'lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore um.'
            ],
            [
                'key' => 'logo',
                'value' => 'logo.png'
            ],
            [
                'key' => 'icon',
                'value' => 'icon.png'
            ],
            [
                'key' => 'facebook',
                'value' => '#'
            ],
            [
                'key' => 'twitter',
                'value' => '#'
            ],
            [
                'key' => 'instagram',
                'value' => '#'
            ],
            [
                'key' => 'linkedin',
                'value' => '#'
            ],
            [
                'key' => 'youtube',
                'value' => '#'
            ],
            [
                'key' => 'pinterest',
                'value' => '#'
            ]
        ];

        foreach ($settings as $setting) {
            \App\Models\Settings::firstOrCreate($setting);
        }
    }
}
