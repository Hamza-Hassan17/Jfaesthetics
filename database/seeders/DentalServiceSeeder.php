<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * The Dental site's fixed service price list. Where the list gives a
 * range, the lower price is stored and the range kept in the name; the
 * charge can still be adjusted on the invoice line.
 *
 * Safe to run again: existing names are updated, not duplicated.
 */
class DentalServiceSeeder extends Seeder
{
    public const SERVICES = [
        // General Dentistry
        'Dental Consultation' => 2000,
        'Periapical X-Ray' => 1000,
        'OPG X-Ray' => 4000,
        'Scaling & Polishing' => 15000,
        'Deep Curettage / Quadrant' => 5000,
        'Fluoride Therapy / Sitting' => 10000,
        // Dental Fillings
        'Dental Posts' => 10000,
        'GIC Filling' => 6000,
        'Laser Filling (10,000 - 15,000)' => 10000,
        'Core Build-Up' => 15000,
        // Root Canal
        'Manual Root Canal (Anterior Tooth)' => 15000,
        'Manual Root Canal (Posterior Tooth)' => 20000,
        'Rotary Root Canal (Anterior Tooth)' => 20000,
        'Rotary Root Canal (Posterior Tooth)' => 25000,
        // Crowns & Bridges
        'Metal Crown' => 10000,
        'PFM Crown' => 20000,
        'PFM Crown (Screw Retained)' => 30000,
        'Zirconia Crown' => 40000,
        'E-Max Crown' => 50000,
        // Aligners
        'Aligners / Arch' => 200000,
        'Aligners Full Treatment' => 400000,
        'Retainers / Arch' => 25000,
        'Fixed Retainer / Arch' => 30000,
        // Orthodontics (Braces)
        'Metal Braces / Arch' => 100000,
        'Invisible/Ceramic Braces / Arch' => 150000,
        // Dental Appliances
        'Night Guard' => 15000,
        'Habit Breaker' => 40000,
        'Mouth Guard' => 20000,
        'Removable Appliances (50,000 - 100,000)' => 50000,
        'Functional Appliances' => 70000,
        'Space Maintainer' => 20000,
        // Hollywood Smile
        'Porcelain Veneering / Tooth' => 30000,
        'Zirconia Veneering / Tooth (40,000 - 50,000)' => 40000,
        'E-Max Veneer / Tooth' => 45000,
        'Laser Whitening (35,000 - 45,000)' => 35000,
        'Teeth Bleaching (35,000 - 40,000)' => 35000,
        // Pediatric Dentistry
        'Pulpotomy' => 15000,
        'Pulpectomy' => 15000,
        'Pits and Fissure Sealants (Per Tooth)' => 8000,
        'Fluoride Varnish' => 20000,
        // Dental Prosthesis
        'Over Denture - Implant Supported (100,000 - 150,000)' => 100000,
        'Partial Denture / Tooth' => 10000,
        'Complete Denture / Arch (100,000 - 150,000)' => 100000,
        'Cast Partial Denture / Arch (100,000 - 200,000)' => 100000,
        // Implants
        'Chinese Implant' => 200000,
        'Korean Implant' => 150000,
        'German Implant' => 300000,
        'USA Implant' => 400000,
        'Bone Grafts' => 40000,
        'Membranes' => 700000,
        'Immediate Implant with Fix Tooth in 72 Hours' => 200000,
        // Dental Surgery
        'Simple Extraction (Single Root)' => 8000,
        'Simple Extraction (Multi Root)' => 12000,
        'Surgical Extraction (25,000 - 35,000)' => 25000,
        'Surgical Complicated / Tooth' => 50000,
        'Gum Surgeries / Gingivectomy / Tooth' => 75000,
    ];

    public function run()
    {
        foreach (self::SERVICES as $name => $price) {
            Service::updateOrCreate(['name' => $name], ['price' => $price]);
        }
    }
}
