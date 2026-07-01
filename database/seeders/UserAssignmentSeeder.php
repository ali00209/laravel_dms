<?php

namespace Database\Seeders;

use App\Models\Core\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use App\Models\Auth\UserAssignment;
use Spatie\Permission\Models\Role;

class UserAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::find(2);
        $org = Organization::first();
        $role = Role::findByName("admin");

        if (!$user || !$org || !$role) {
            return;
        }

        UserAssignment::create([
            "user_id" => $user->id,
            "role_id" => $role->id,
            "assignable_type" => "organization",
            "assignable_id" => $org->id,
        ]);

        $this->command->info("Admin role assigned to umair@gmail.com");
    }
}
