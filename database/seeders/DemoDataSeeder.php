<?php

namespace Database\Seeders;

use App\Models\doctor;
use App\Models\employee;
use App\Models\medicine;
use App\Models\nurse;
use App\Models\patient;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Seeds 25 doctors, 14 patients, 5 nurses, 3 accountants, 2 pharmacists,
     * 2 receptionists, 4 cleaners, 1 security, 10 medicines, and 25 services.
     *
     * No dependency on fakerphp/faker (a dev-only package not present on
     * production installs run with `composer install --no-dev`) — all random
     * data is generated from plain PHP below.
     *
     * Run standalone (not part of the main DatabaseSeeder chain):
     *   php artisan db:seed --class=DemoDataSeeder
     */
    protected $firstNames = [
        'Ahmed', 'Ayesha', 'Bilal', 'Sara', 'Hassan', 'Zainab', 'Usman', 'Mariam',
        'Ali', 'Hira', 'Omar', 'Fatima', 'Hamza', 'Sana', 'Kamran', 'Rabia',
        'Faisal', 'Nida', 'Imran', 'Sadia', 'Tariq', 'Amna', 'Waqas', 'Iqra',
        'Adeel', 'Maryam', 'Shahzad', 'Noor', 'Junaid', 'Aliya', 'Rizwan', 'Saba',
        'Farhan', 'Mahnoor', 'Asad', 'Komal', 'Salman', 'Anum', 'Naveed', 'Rida',
    ];

    protected $lastNames = [
        'Khan', 'Ahmed', 'Malik', 'Hashmi', 'Siddiqui', 'Rehman', 'Chaudhry', 'Baig',
        'Sheikh', 'Qureshi', 'Butt', 'Awan', 'Raza', 'Iqbal', 'Farooq', 'Zulfiqar',
        'Mahmood', 'Javed', 'Aslam', 'Yousaf', 'Soomro', 'Abbasi', 'Gill', 'Warraich',
    ];

    protected $streets = [
        'Main Boulevard', 'Canal Road', 'Model Town', 'Gulberg', 'Jail Road',
        'DHA Phase 5', 'Bahria Town', 'Johar Town', 'Garden Town', 'Cavalry Ground',
    ];

    protected $cities = ['Lahore', 'Karachi', 'Islamabad', 'Rawalpindi', 'Faisalabad'];

    protected $usedPhones = [];
    protected $usedEmails = [];

    public function run()
    {
        DB::transaction(function () {
            $this->seedDoctors(25);
            $this->seedNurses(5);
            $this->seedStaff([
                'accountant' => 3,
                'pharmacist' => 2,
                'receptionist' => 2,
                'cleaner' => 4,
                'security' => 1,
            ]);
            $this->seedPatients(14);
            $this->seedMedicines();
            $this->seedServices();
        });
    }

    protected function randomName(): array
    {
        return [
            $this->firstNames[array_rand($this->firstNames)],
            $this->lastNames[array_rand($this->lastNames)],
        ];
    }

    protected function randomPhone(): string
    {
        do {
            $phone = '03' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        } while (isset($this->usedPhones[$phone]));

        $this->usedPhones[$phone] = true;

        return $phone;
    }

    protected function randomEmail(string $first, string $last): string
    {
        $domains = ['example.com', 'mail.com', 'inbox.com'];

        do {
            $email = strtolower($first) . '.' . strtolower($last) . random_int(100, 99999) . '@' . $domains[array_rand($domains)];
        } while (isset($this->usedEmails[$email]));

        $this->usedEmails[$email] = true;

        return $email;
    }

    protected function randomAddress(): string
    {
        return 'House ' . random_int(1, 400) . ', ' . $this->streets[array_rand($this->streets)] . ', ' . $this->cities[array_rand($this->cities)];
    }

    protected function randomPriceBetween(int $min, int $max): float
    {
        return round(random_int($min * 100, $max * 100) / 100, 2);
    }

    protected function seedDoctors(int $count): void
    {
        $qualifications = [
            'MBBS, FCPS (Dermatology)',
            'MBBS, MD (Plastic Surgery)',
            'MBBS, MRCS (Cosmetic Surgery)',
            'MBBS, FCPS (General Surgery)',
            'MBBS, Diploma in Aesthetic Medicine',
        ];

        for ($i = 0; $i < $count; $i++) {
            [$first, $last] = $this->randomName();

            $emp = employee::create([
                'name' => 'Dr. ' . $first . ' ' . $last,
                'email' => $this->randomEmail($first, $last),
                'phone' => $this->randomPhone(),
                'salary' => (string) random_int(120000, 400000),
                'address' => $this->randomAddress(),
                'qualification' => $qualifications[array_rand($qualifications)],
                'position' => 'doctor',
                'status' => 'active',
            ]);

            doctor::create(['employee_id' => $emp->id]);
        }
    }

    protected function seedNurses(int $count): void
    {
        $positions = ['Staff Nurse', 'Head Nurse', 'ICU Nurse', 'OT Nurse', 'Recovery Nurse'];
        $qualifications = ['BSN', 'Diploma in Nursing', 'Registered Nurse'];

        for ($i = 0; $i < $count; $i++) {
            [$first, $last] = $this->randomName();

            nurse::create([
                'name' => $first . ' ' . $last,
                'email' => $this->randomEmail($first, $last),
                'phone' => $this->randomPhone(),
                'gender' => random_int(0, 1) ? 'Female' : 'Male',
                'address' => $this->randomAddress(),
                'qualification' => $qualifications[array_rand($qualifications)],
                'position' => $positions[$i % count($positions)],
                'registered' => random_int(1, 100) <= 80,
            ]);
        }
    }

    protected function seedStaff(array $countsByPosition): void
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
                [$first, $last] = $this->randomName();

                employee::create([
                    'name' => $first . ' ' . $last,
                    'email' => $this->randomEmail($first, $last),
                    'phone' => $this->randomPhone(),
                    'salary' => (string) random_int(25000, 90000),
                    'address' => $this->randomAddress(),
                    'qualification' => $qualifications[array_rand($qualifications)],
                    'position' => $position,
                    'status' => 'active',
                ]);
            }
        }
    }

    protected function seedPatients(int $count): void
    {
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

        for ($i = 0; $i < $count; $i++) {
            [$first, $last] = $this->randomName();

            patient::create([
                'name' => $first . ' ' . $last,
                'email' => $this->randomEmail($first, $last),
                'phone' => $this->randomPhone(),
                'address' => $this->randomAddress(),
                'gender' => random_int(0, 1) ? 'Female' : 'Male',
                'age' => (string) random_int(18, 70),
                'bloodgroup' => $bloodGroups[array_rand($bloodGroups)],
            ]);
        }
    }

    protected function seedMedicines(): void
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

        $usedCodes = [];

        foreach ($medicines as $name) {
            do {
                $code = 'MED-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)
                    . chr(random_int(65, 90)) . chr(random_int(65, 90));
            } while (isset($usedCodes[$code]));
            $usedCodes[$code] = true;

            medicine::firstOrCreate(
                ['name' => $name],
                [
                    'price' => $this->randomPriceBetween(100, 5000),
                    'quantity' => random_int(10, 200),
                    'code' => $code,
                    'low_stock_threshold' => 10,
                ]
            );
        }
    }

    protected function seedServices(): void
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
                ['price' => $this->randomPriceBetween($min, $max)]
            );
        }
    }
}
