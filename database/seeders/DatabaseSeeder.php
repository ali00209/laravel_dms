<?php

namespace Database\Seeders;

use App\Models\Core\Organization;
use Database\Seeders\Inventory\BrandSeeder;
use Database\Seeders\Inventory\CategorySeeder;
use Database\Seeders\Inventory\VariationSeeder;
use Database\Seeders\Inventory\VariationValuesSeeder;
use Database\Seeders\EmployeeSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\Policy\OrganizationPolicySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Inventory\UomSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\VoucherTypeSeeder;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Test oeganization in development

        if (config("app.env") == "development") {
            $this->call([
                OrganizationSeeder::class,
                RoleSeeder::class,
                PermissionSeeder::class,
                UserSeeder::class,
                UserAssignmentSeeder::class,
                OrganizationPolicySeeder::class,
            ]);
        }

        $organization = Organization::first();

        if (!$organization) {
            $this->call([
                RoleSeeder::class,
                PermissionSeeder::class,
                UserSeeder::class,
            ]);
        }

        if ($organization) {
            $this->call([
                UomSeeder::class,
                CategorySeeder::class,
                VariationSeeder::class,
                VariationValuesSeeder::class,
                BrandSeeder::class,
                VoucherTypeSeeder::class,
                AccountSeeder::class,
                EmployeeSeeder::class,
            ]);
        }
    }
}
