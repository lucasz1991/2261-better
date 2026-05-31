<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyntheticRatingUser extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'base_user_id',
        'name',
        'email',
        'email_domain',
        'role',
        'status',
        'email_verified_at',
        'data',
    ];

    protected $casts = [
        'base_user_id' => 'integer',
        'status' => 'boolean',
        'email_verified_at' => 'datetime',
        'data' => 'array',
    ];

    public function claimRatings(): HasMany
    {
        return $this->hasMany(ClaimRating::class);
    }

    public function markBaseUserCreated(int $baseUserId): void
    {
        $data = $this->data ?? [];
        $data['base_user'] = array_merge($data['base_user'] ?? [], [
            'created_at' => now()->toDateTimeString(),
            'base_user_id' => $baseUserId,
        ]);

        $this->forceFill([
            'base_user_id' => $baseUserId,
            'data' => $data,
        ])->saveQuietly();
    }

    public function markBaseUserRetracted(): void
    {
        $data = $this->data ?? [];
        $data['base_user'] = array_merge($data['base_user'] ?? [], [
            'retracted_at' => now()->toDateTimeString(),
            'base_user_id' => $this->base_user_id,
        ]);

        $this->forceFill([
            'base_user_id' => null,
            'data' => $data,
        ])->saveQuietly();
    }
}
