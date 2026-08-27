<?php

namespace Database\Seeders;

use App\Models\doctor;
use App\Models\employee;
use Illuminate\Database\Seeder;

class MediLifeDoctorSeeder extends Seeder
{
    /**
     * MediLife Clinics' Appointments/Doctor Visit tab has exactly one fixed
     * doctor, per request — creates the employee + doctor profile once so
     * the Appointments component always has a real doctor_id to point to.
     *
     * Run standalone: php artisan db:seed --class=MediLifeDoctorSeeder
     */
    public function run()
    {
        $employee = employee::firstOrCreate(
            ['email' => 'drfabreennaz@hotmail.com'],
            [
                'name' => 'Dr. Fabreen Naz',
                'phone' => '0321-2331421',
                'position' => 'doctor',
                'status' => 'active',
                'salary' => 0,
                'address' => '63-C/2, 24th Commercial Street, Touheed Commercial Area, Phase V, DHA, Karachi.',
                'qualification' => 'MBBS',
                'image' => 'doctor.png',
            ]
        );

        doctor::firstOrCreate(['employee_id' => $employee->id]);
    }
}
