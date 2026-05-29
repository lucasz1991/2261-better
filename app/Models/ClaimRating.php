<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClaimRating extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RATED = 'rated';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';

    protected $fillable = [
        'base_claim_rating_id',
        'user_id',
        'insurance_subtype_id',
        'insurance_type_id',
        'rating_questionnaire_versions_id',
        'insurance_id',
        'answers',
        'status',
        'attachments',
        'rating_score',
        'tag_ids',
        'moderator_comment',
        'is_public',
        'admin_review',
        'data',
        'verification_hash',
    ];

    protected $casts = [
        'answers' => 'array',
        'attachments' => 'array',
        'tag_ids' => 'array',
        'admin_review' => 'array',
        'data' => 'array',
        'is_public' => 'boolean',
        'rating_score' => 'decimal:2',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Ausstehend',
            self::STATUS_RATED => 'Bewertet',
            self::STATUS_APPROVED => 'Freigegeben',
            self::STATUS_REJECTED => 'Abgelehnt',
            self::STATUS_PUBLISHED => 'Publiziert',
            self::STATUS_PENDING_VALIDATION => 'In Pruefung',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query
                ->where('moderator_comment', 'like', '%' . $search . '%')
                ->orWhere('status', 'like', '%' . $search . '%');

            if (is_numeric($search)) {
                $query
                    ->orWhere('id', (int) $search)
                    ->orWhere('base_claim_rating_id', (int) $search)
                    ->orWhere('insurance_id', (int) $search)
                    ->orWhere('insurance_type_id', (int) $search)
                    ->orWhere('insurance_subtype_id', (int) $search);
            }
        });
    }
}
