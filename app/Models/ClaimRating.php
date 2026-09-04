<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETRACTED = 'retracted';

    protected $fillable = [
        'base_claim_rating_id',
        'base_user_id',
        'synthetic_rating_user_id',
        'user_id',
        'insurance_subtype_id',
        'insurance_type_id',
        'rating_questionnaire_versions_id',
        'insurance_id',
        'answers',
        'status',
        'scheduled_for',
        'execution_started_at',
        'executed_at',
        'execution_attempts',
        'last_execution_error',
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
        'base_user_id' => 'integer',
        'synthetic_rating_user_id' => 'integer',
        'rating_score' => 'decimal:2',
        'scheduled_for' => 'datetime',
        'execution_started_at' => 'datetime',
        'executed_at' => 'datetime',
        'execution_attempts' => 'integer',
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
            self::STATUS_SCHEDULED => 'Geplant',
            self::STATUS_PROCESSING => 'In Ausfuehrung',
            self::STATUS_FAILED => 'Fehlgeschlagen',
            self::STATUS_RETRACTED => 'Zurueckgerufen',
        ];
    }

    public function syntheticUser(): BelongsTo
    {
        return $this->belongsTo(SyntheticRatingUser::class, 'synthetic_rating_user_id');
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
                ->where('moderator_comment', 'like', '%'.$search.'%')
                ->orWhere('last_execution_error', 'like', '%'.$search.'%')
                ->orWhere('status', 'like', '%'.$search.'%')
                ->orWhere('data->base_context->insurance->name', 'like', '%'.$search.'%');

            if (is_numeric($search)) {
                $query
                    ->orWhere('id', (int) $search)
                    ->orWhere('base_claim_rating_id', (int) $search)
                    ->orWhere('base_user_id', (int) $search)
                    ->orWhere('synthetic_rating_user_id', (int) $search)
                    ->orWhere('insurance_id', (int) $search)
                    ->orWhere('insurance_type_id', (int) $search)
                    ->orWhere('insurance_subtype_id', (int) $search);
            }

            $query->orWhereHas('syntheticUser', function (Builder $query) use ($search): void {
                $query
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        });
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query
                ->whereNotNull('scheduled_for')
                ->orWhereIn('status', [
                    self::STATUS_SCHEDULED,
                    self::STATUS_PROCESSING,
                    self::STATUS_FAILED,
                    self::STATUS_RETRACTED,
                ]);
        });
    }

    public function scopeWithoutManualOnlyAfterRetract(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', self::STATUS_RETRACTED)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('data->execution_control->manual_only_after_retract')
                    ->orWhere('data->execution_control->manual_only_after_retract', false);
            });
    }

    public function scopeReplannable(Builder $query, ?DateTimeInterface $at = null): Builder
    {
        return $query
            ->where('data->synthetic', true)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '>', $at ?? now())
            ->whereNull('execution_started_at')
            ->whereNull('executed_at')
            ->whereIn('status', [
                self::STATUS_SCHEDULED,
                self::STATUS_RATED,
                self::STATUS_FAILED,
            ])
            ->withoutManualOnlyAfterRetract();
    }

    public function isManualOnlyAfterRetract(): bool
    {
        return (bool) data_get($this->data ?? [], 'execution_control.manual_only_after_retract', false);
    }

    public function isRetracted(): bool
    {
        return $this->status === self::STATUS_RETRACTED || $this->isManualOnlyAfterRetract();
    }

    public function canBeDeletedFromPlan(): bool
    {
        return ! $this->executed_at
            && ! $this->base_claim_rating_id
            && ! $this->base_user_id
            && $this->status !== self::STATUS_PROCESSING;
    }

    public function isReplannableAt(?DateTimeInterface $at = null): bool
    {
        if (! (bool) data_get($this->data ?? [], 'synthetic', false)
            || ! $this->scheduled_for
            || ! $this->scheduled_for->gt($at ?? now())
            || $this->execution_started_at
            || $this->executed_at
            || $this->isRetracted()
            || ! in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_RATED, self::STATUS_FAILED], true)) {
            return false;
        }

        return true;
    }

    public function getExecutionStateLabelAttribute(): string
    {
        if ($this->isRetracted()) {
            return 'Zurueckgerufen';
        }

        if ($this->executed_at) {
            return 'Ausgefuehrt';
        }

        if ($this->status === self::STATUS_FAILED || filled($this->last_execution_error)) {
            return 'Fehler';
        }

        if ($this->status === self::STATUS_PROCESSING) {
            return 'Laeuft';
        }

        if ($this->scheduled_for && $this->scheduled_for->isPast()) {
            return 'Faellig';
        }

        if (filled(data_get($this->data, 'ai_generation.generated_at'))) {
            return 'AI vorbereitet';
        }

        return 'Geplant';
    }
}
