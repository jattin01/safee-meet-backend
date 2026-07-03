<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Admin;
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        Role::updateOrCreate(
          
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'status' => 1,
                
            ]
        );

        Role::updateOrCreate(
            [
                'name' => 'Admin',
                'slug' => 'admin',
                'status' => 1,
            ]
        );
    }
    
}
