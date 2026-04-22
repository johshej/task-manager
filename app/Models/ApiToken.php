<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class ApiToken extends PersonalAccessToken
{
    use HasUuids;

    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'is_ai',
        'version',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_ai' => 'boolean',
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
