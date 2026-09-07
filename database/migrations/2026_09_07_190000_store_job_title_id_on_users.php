<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('job_title', '')->update(['job_title' => null]);

        DB::table('job_titles')
            ->orderBy('id')
            ->each(function (object $jobTitle): void {
                DB::table('users')
                    ->whereRaw('LOWER(TRIM(job_title)) = ?', [$jobTitle->normalized_name])
                    ->update(['job_title' => $jobTitle->id]);
            });

        $unresolved = DB::table('users')
            ->whereNotNull('job_title')
            ->pluck('job_title')
            ->filter(fn ($value): bool => ! ctype_digit((string) $value))
            ->unique()
            ->values();

        if ($unresolved->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot convert users.job_title to IDs; unresolved values: '.$unresolved->implode(', ')
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('job_title')->nullable()->change();
            $table->foreign('job_title')->references('id')->on('job_titles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['job_title']);
            $table->string('job_title')->nullable()->change();
        });

        DB::table('job_titles')
            ->orderBy('id')
            ->each(function (object $jobTitle): void {
                DB::table('users')
                    ->where('job_title', (string) $jobTitle->id)
                    ->update(['job_title' => $jobTitle->name]);
            });
    }
};
