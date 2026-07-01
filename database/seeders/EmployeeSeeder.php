<?php

namespace Database\Seeders;

use App\Models\Core\Employee;
use App\Models\Core\Organization;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::first();

        if (!$org) {
            return;
        }

        Employee::create([
            "organization_id" => $org->id,
            "name" => "Mr. A",
            "monthly_salary" => 100,
            "designation" => "Staff",
            "department" => "General",
            "hire_date" => now()->subYear(),
            "is_active" => true,
        ]);

        Employee::create([
            "organization_id" => $org->id,
            "name" => "Mr. B",
            "monthly_salary" => 250,
            "designation" => "Senior Staff",
            "department" => "General",
            "hire_date" => now()->subYear(),
            "is_active" => true,
        ]);

        $this->command->info(
            "Created Mr. A (salary: 100) and Mr. B (salary: 250).",
        );
    }
}
