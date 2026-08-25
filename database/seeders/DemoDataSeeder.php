<?php

namespace Database\Seeders;

use App\Models\doctor;
use App\Models\employee;
use App\Models\medicine;
use App\Models\nurse;
use App\Models\patient;
use App\Models\Service;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Seeds 25 doctors, 14 patients, 5 nurses, 3 accountants, 2 pharmacists,
     * 2 receptionists, 4 cleaners, 1 security, 10 medicines, and 25 services.
     *
     * Run standalone (not part of the main DatabaseSeeder chain):
     *   php artisan db:seed --class=DemoDataSeeder
     */
    public function run()
    {
        $faker = Faker::create();

        DB::transaction(function () use ($faker) {
            $this->seedDoctors($faker, 25);
            $this->seedNurses($faker, 5);
            $this->seedStaff($faker, [
                'accountant' => 3,
                'pharmacist' => 2,
                'receptionist' => 2,
                'cleaner' => 4,
                'security' => 1,
            ]);
            $this->seedPatients($faker, 14);
            $this->seedMedicines($faker);
            $this->seedServices($faker);
        });
    }

    protected function seedDoctors($faker, int $count): void
    {
        $qualifications = [
            'MBBS, FCPS (Dermatology)',
            'MBBS, MD (Plastic Surgery)',
            'MBBS, MRCS (Cosmetic Surgery)',
            'MBBS, FCPS (General Surgery)',
            'MBBS, Diploma in Aesthetic Medicine',
        ];

        for ($i = 0; $i < $count; $i++) {
            $emp = employee::create([
                'name' => 'Dr. ' . $faker->unique()->firstName . ' ' . $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->unique()->numerify('03#########'),
                'salary' => (string) $faker->numberBetween(120000, 400000),
                'address' => $faker->address,
                'qualification' => $faker->randomElement($qualifications),
                'position' => 'doctor',
                'status' => 'active',
            ]);

            doctor::create(['employee_id' => $emp->id]);
        }
    }

    protected function seedNurses($faker, int $count): void
    {
        $positions = ['Staff Nurse', 'Head Nurse', 'ICU Nurse', 'OT Nurse', 'Recovery Nurse'];

        for ($i = 0; $i < $count; $i++) {
            nurse::create([
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->unique()->numerify('03#########'),
                'gender' => $faker->randomElement(['Male', 'Female']),
                'address' => $faker->address,
                'qualification' => $faker->randomElement(['BSN', 'Diploma in Nursing', 'Registered Nurse']),
                'position' => $positions[$i % count($positions)],
                'registered' => $faker->boolean(80),
            ]);
        }
    }

    protected function seedStaff($faker, array $countsByPosition): void
    {
        $qualificationsByPosition = [
            'accountant' => ['B.Com', 'ACCA (Part Qualified)', 'M.Com'],
            'pharmacist' => ['Pharm-D', 'B.Pharm'],
            'receptionist' => ['Intermediate', 'Bachelors'],
            'cleaner' => ['Matric', 'Middle'],
            'security' => ['Matric', 'Ex-Army'],
        ];

        foreach ($countsByPosition as $position => $count) {
            $qualifications = $qualificationsByPosition[$position] ?? ['Intermediate'];

            for ($i = 0; $i < $count; $i++) {
                employee::create([
                    'name' => $faker->name,
                    'email' => $faker->unique()->safeEmail,
                    'phone' => $faker->unique()->numerify('03#########'),
                    'salary' => (string) $faker->numberBetween(25000, 90000),
                    'address' => $faker->address,
                    'qualification' => $faker->randomElement($qualifications),
                    'position' => $position,
                    'status' => 'active',
                ]);
            }
        }
    }

    protected function seedPatients($faker, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            patient::create([
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->unique()->numerify('03#########'),
                'address' => $faker->address,
                'gender' => $faker->randomElement(['Male', 'Female']),
                'age' => (string) $faker->numberBetween(18, 70),
                'bloodgroup' => $faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
            ]);
        }
    }

    protected function seedMedicines($faker): void
    {
        $medicines = [
            'Paracetamol 500mg',
            'Amoxicillin 250mg',
            'Ibuprofen 400mg',
            'Betamethasone Cream',
            'Hyaluronic Acid Dermal Filler',
            'Vitamin C Brightening Serum',
            'Lidocaine 2% Injection',
            'Tretinoin Cream 0.05%',
            'Botulinum Toxin (Botox) Vial',
            'Normal Saline Solution 500ml',
        ];

        foreach ($medicines as $name) {
            medicine::firstOrCreate(
                ['name' => $name],
                [
                    'price' => $faker->randomFloat(2, 100, 5000),
                    'quantity' => $faker->numberBetween(10, 200),
                    'code' => strtoupper($faker->unique()->bothify('MED-####??')),
                    'low_stock_threshold' => 10,
                ]
            );
        }
    }

    protected function seedServices($faker): void
    {
        $services = [
            'Face Lift' => [80000, 250000],
            'Rhinoplasty' => [100000, 300000],
            'Tummy Tuck' => [150000, 400000],
            'Breast Augmentation/Reduction' => [180000, 450000],
            'Vaginoplasty' => [120000, 300000],
            'Fat Grafting: Face, Hand, Foot' => [60000, 150000],
            'Tattoo Laser Treatment' => [5000, 25000],
            'Acne Laser Treatment' => [4000, 15000],
            'Laser Skin Rejuvenation' => [8000, 30000],
            'Vascular Laser Treatment' => [6000, 20000],
            'Laser Hair Removal – Face' => [3000, 10000],
            'Laser Hair Removal – Body' => [8000, 25000],
            'HydraFacial' => [6000, 15000],
            'Vampire Facial' => [10000, 25000],
            'Vmax HIFU Facial' => [15000, 40000],
            'BB Glow' => [8000, 18000],
            'Chemical Peel' => [4000, 12000],
            'PRP (Face)' => [8000, 20000],
            'Meso White / Meso Lift' => [6000, 16000],
            'Microdermabrasion' => [4000, 10000],
            'Oxygeneo Facial' => [7000, 18000],
            'Radiofrequency Facial' => [6000, 15000],
            'Hair PRP / PRGF' => [8000, 20000],
            'Hair Keratin' => [5000, 12000],
            'Cryo Lipo' => [15000, 40000],
        ];

        foreach ($services as $name => [$min, $max]) {
            Service::firstOrCreate(
                ['name' => $name],
                ['price' => $faker->randomFloat(2, $min, $max)]
            );
        }
    }
}
