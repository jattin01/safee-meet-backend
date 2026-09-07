<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index('name');
        });

        $defaults = [
            'Electrician', 'Plumber', 'Carpenter', 'Painter', 'Mason', 'Welder',
            'AC Technician', 'Appliance Technician', 'CCTV Technician', 'Cleaner',
            'Driver', 'Mechanic', 'Gardener', 'Security Guard', 'General Worker', 'Other',
        ];

        $existing = Schema::hasColumn('users', 'job_title')
            ? DB::table('users')->whereNotNull('job_title')->pluck('job_title')->all()
            : [];

        $rows = [];
        foreach (array_merge($defaults, $existing) as $value) {
            $name = preg_replace('/\s+/u', ' ', trim((string) $value));
            if ($name === '') {
                continue;
            }

            $normalized = Str::lower($name);
            $rows[$normalized] ??= [
                'name' => $name,
                'normalized_name' => $normalized,
                'description' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('job_titles')->insert(array_values($rows));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('job_titles');
    }
};
