<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // public function up(): void
    // {
    //     // The admin_roles rename shipped in the roles-table migration never took
    //     // effect locally because that migration had already run under its old
    //     // "roles" name/content. Bring existing databases in line without losing
    //     // the seeded admin roles or the FK on admins.role_id.
    //     if (Schema::hasTable('roles') && ! Schema::hasTable('admin_roles')) {
    //         Schema::rename('roles', 'admin_roles');
    //     }
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     if (Schema::hasTable('admin_roles') && ! Schema::hasTable('roles')) {
    //         Schema::rename('admin_roles', 'roles');
    //     }
    // }
};
