<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SearchHistory extends Model
{
    use SoftDeletes;

    protected $table = 'search_history';

    protected $fillable = [
        'searcher_id', 'found_user_id', 'query', 'method',
    ];

    protected function casts(): array
    {
        return [
            // Keep these string-shaped in API responses regardless of the
            // underlying users.id column type (char ULID or bigint) — the
            // mobile client expects string ids everywhere.
            'id' => 'string',
            'searcher_id' => 'string',
            'found_user_id' => 'string',
        ];
    }
}
