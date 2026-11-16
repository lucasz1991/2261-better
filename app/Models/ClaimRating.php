<?php

namespace App\Models;

use Carbon\CarbonImmutable;
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
        $search = mb_substr(trim((string) $search), 0, 200);
        $search = str_starts_with($search, '@') ? substr($search, 1) : $search;

        if ($search === '') {
            return $query;
        }

        $like = '%'.$search.'%';
        $tokens = array_slice(preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 4);

        return $query->where(function (Builder $query) use ($search, $like, $tokens): void {
            $query->where('moderator_comment', 'like', $like)
                ->orWhere('last_execution_error', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhere('answers', 'like', $like)
                ->orWhere('attachments->scorings->ai_overall_comment', 'like', $like);

            foreach (['insurance', 'insurance_type', 'insurance_subtype'] as $context) {
                $query->orWhere('data->base_context->'.$context.'->name', 'like', $like);
            }

            // Profiles may exist before a local user is materialized.
            foreach (['name', 'username', 'first_name', 'last_name', 'display_name', 'email', 'persona->display_name'] as $field) {
                $query->orWhere('data->planning->synthetic_user_profile->'.$field, 'like', $like);
            }

            if (ctype_digit($search) && strlen($search) <= 18) {
                foreach (['id', 'base_claim_rating_id', 'base_user_id', 'synthetic_rating_user_id', 'insurance_id', 'insurance_type_id', 'insurance_subtype_id'] as $field) {
                    $query->orWhere($field, (int) $search);
                }
            }

            $query->orWhereHas('syntheticUser', function (Builder $users) use ($like, $tokens): void {
                $users->where(function (Builder $names) use ($like, $tokens): void {
                    foreach (['name', 'username', 'first_name', 'last_name', 'email'] as $field) {
                        $names->orWhere($field, 'like', $like);
                    }

                    if (count($tokens) > 1) {
                        $names->orWhere(function (Builder $fullName) use ($tokens): void {
                            foreach ($tokens as $token) {
                                $fullName->where(function (Builder $part) use ($token): void {
                                    $part->where('first_name', 'like', '%'.$token.'%')
                                        ->orWhere('last_name', 'like', '%'.$token.'%');
                                });
                            }
                        });
                    }
                });
            });
        });
    }

    public function scopeAdminFilters(Builder $query, array $filters): Builder
    {
        if (filled($filters['status'] ?? null)) {
            $query->where('status', $filters['status']);
        }

        $dateField = in_array($filters['date_field'] ?? '', ['created_at', 'scheduled_for', 'executed_at', 'updated_at'], true)
            ? $filters['date_field'] : 'created_at';
        $timezone = config('app.timezone', 'Europe/Berlin');
        foreach (['date_from' => '>=', 'date_to' => '<'] as $key => $operator) {
            if (filled($filters[$key] ?? null)) {
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $filters[$key], $timezone);
                $query->where($dateField, $operator, $key === 'date_to' ? $date->addDay() : $date);
            }
        }

        foreach (['insurance_id', 'insurance_type_id', 'insurance_subtype_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, (int) $filters[$field]);
            }
        }

        foreach (['score_min' => '>=', 'score_max' => '<='] as $field => $operator) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where('rating_score', $operator, (float) $filters[$field]);
            }
        }

        if (in_array($filters['visibility'] ?? '', ['public', 'private'], true)) {
            $query->where('is_public', $filters['visibility'] === 'public');
        }

        if (($filters['base_link'] ?? '') === 'linked') {
            $query->whereNotNull('base_claim_rating_id');
        } elseif (($filters['base_link'] ?? '') === 'unlinked') {
            $query->whereNull('base_claim_rating_id');
        }

        $userManual = fn (Builder $users) => $users->whereNotNull('data->manual_edit->edited_at');
        if (($filters['manual'] ?? '') === 'yes') {
            $query->where(fn (Builder $edited) => $edited->whereNotNull('data->manual_edit->edited_at')
                ->orWhereHas('syntheticUser', $userManual));
        } elseif (($filters['manual'] ?? '') === 'no') {
            $query->whereNull('data->manual_edit->edited_at')->whereDoesntHave('syntheticUser', $userManual);
        }

        if (($filters['generator'] ?? '') === '6') {
            $query->whereHas('syntheticUser', fn (Builder $users) => $users->where('data->username_generation->generator_version', 6));
        } elseif (($filters['generator'] ?? '') === 'legacy') {
            $query->whereDoesntHave('syntheticUser', fn (Builder $users) => $users->where('data->username_generation->generator_version', '>=', 6));
        }

        return $query->effectiveEditSync($filters['sync'] ?? '');
    }

    /**
     * Keep the rating and shared-user revision states independent. A user edit
     * affects every linked rating, so actionable user states take precedence.
     */
    public function scopeEffectiveEditSync(Builder $query, string $state): Builder
    {
        $priority = ['conflict', 'failed', 'pending', 'local'];
        $position = array_search($state, $priority, true);
        $excluded = $position !== false ? array_slice($priority, 0, $position) : $priority;

        if (! in_array($state, [...$priority, 'unknown', 'synced'], true)) {
            return $query;
        }

        if ($excluded !== []) {
            $query->where(fn (Builder $rating) => $rating->whereNull('data->edit_sync->status')
                ->orWhereNotIn('data->edit_sync->status', $excluded))
                ->whereDoesntHave('syntheticUser', fn (Builder $user) => $user->whereIn('data->edit_sync->status', $excluded));
        }

        if ($position !== false) {
            return $query->where(fn (Builder $rating) => $rating->where('data->edit_sync->status', $state)
                ->orWhereHas('syntheticUser', fn (Builder $user) => $user->where('data->edit_sync->status', $state)));
        }

        if ($state === 'synced') {
            return $query->where('data->edit_sync->status', 'synced')
                ->where(fn (Builder $rating) => $rating->whereDoesntHave('syntheticUser')
                    ->orWhereHas('syntheticUser', fn (Builder $user) => $user->where('data->edit_sync->status', 'synced')));
        }

        $known = [...$priority, 'synced'];
        $unknown = fn (Builder $row) => $row->whereNull('data->edit_sync->status')
            ->orWhereNotIn('data->edit_sync->status', $known);

        return $query->where(fn (Builder $rating) => $rating->where($unknown)
            ->orWhereHas('syntheticUser', fn (Builder $user) => $user->where($unknown)));
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
