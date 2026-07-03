<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;


class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::where('slug', 'super_admin')->first();

        if (!$role) {
            return;
        }

        Admin::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'role_id' => $role->id,
                'name' => 'Super Admin',
                'phone' => '9999999999',
                'password' => Hash::make('password123'),
                'status' => 1,
            ]
            
        );
    }
}
