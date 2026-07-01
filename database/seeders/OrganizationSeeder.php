<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Core\Organization;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::first();

        if ($org) {
            return;
        }

        Organization::create([
            "name" => "Test Trading Co",
            "legal_name" => "Test Trading Company (Pvt) Ltd",
            "business_start_date" => "2015-04-01",
            "ntn" => "1234567-8",
            "strn" => "987654321",
            "incorporation_no" => "INC-2020-0001",
            "email" => "info@acmetrade.local",
            "contact_no" => "+92 300 1234567",
            "website" => "https=>//acmetrade.example",
            "address" => "123 Industrial Area, Phase 1",
            "city" => "Karachi",
            "state" => "Sindh",
            "country" => "Pakistan",
            "zip_code" => "75500",
            "longitude" => 67.0099,
            "latitude" => 24.8607,
            "logo" => null,
            "favicon" => null,
            "currency_code" => "PKR",
        ]);

        $this->command->info("Created Test Organization.");
    }
}
