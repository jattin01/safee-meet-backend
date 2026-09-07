<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'normalized_name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithUsersCount(Builder $query): Builder
    {
        return $query->addSelect([
            'users_count' => User::query()
                ->selectRaw('COUNT(*)')
                ->whereNotNull('job_title')
                ->whereRaw('LOWER(TRIM(users.job_title)) = job_titles.normalized_name'),
        ]);
    }

    public function usersCount(): int
    {
        return User::query()
            ->whereNotNull('job_title')
            ->whereRaw('LOWER(TRIM(job_title)) = ?', [$this->normalized_name])
            ->count();
    }
}
