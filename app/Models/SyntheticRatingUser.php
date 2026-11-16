<?php

namespace App\Models;

use App\Services\UsernameAllocator;
use App\Support\Rating\SyntheticIdentityGenerator;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SyntheticRatingUser extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'base_user_id',
        'name',
        'first_name',
        'last_name',
        'username',
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

    public static function createForClaimRating(?ClaimRating $claimRating = null, ?DateTimeInterface $plannedFor = null): self
    {
        if ($claimRating?->exists) {
            $claimRating->refresh();
        }

        if ($claimRating?->synthetic_rating_user_id && self::withTrashed()->whereKey($claimRating->synthetic_rating_user_id)->exists()) {
            return self::ensureForClaimRating($claimRating);
        }

        $token = strtolower((string) Str::random(12));
        $nameMode = self::syntheticUserNameMode();
        $privacySettings = self::privacySettingsForNameMode(self::privacySettingsFromAnalysis(), $nameMode);
        $identityGenerator = new SyntheticIdentityGenerator;
        $persona = $identityGenerator->persona($privacySettings);
        $emailProfile = self::emailDomainProfile($identityGenerator);
        $generationPersona = self::generationPersona($persona, $plannedFor ?? $claimRating?->scheduled_for);

        return app(UsernameAllocator::class)->create($generationPersona, function (array $allocation) use ($claimRating, $persona, $nameMode, $privacySettings, $identityGenerator, $emailProfile, $token): self {
            if ($claimRating) {
                $lockedRating = ClaimRating::query()->whereKey($claimRating->id)->lockForUpdate()->firstOrFail();

                if ($lockedRating->synthetic_rating_user_id) {
                    $existing = self::withTrashed()->find($lockedRating->synthetic_rating_user_id);

                    if ($existing) {
                        if ($existing->trashed()) {
                            $existing->restore();
                        }
                        $claimRating->setRawAttributes($lockedRating->getAttributes(), true);

                        return $existing;
                    }
                }
            }

            $username = $allocation['username'];
            $emailDomain = $emailProfile['domain'];
            $email = $identityGenerator->email(
                $persona,
                $emailDomain,
                fn (string $candidate): bool => self::withTrashed()->where('email', $candidate)->exists(),
                $token
            );
            $syntheticUser = self::create([
                'base_user_id' => null,
                'name' => $username,
                'first_name' => $persona['first_name'] ?? null,
                'last_name' => $persona['last_name'] ?? null,
                'username' => $username,
                'email' => $email,
                'email_domain' => $emailDomain,
                'role' => 'guest',
                'status' => true,
                'email_verified_at' => now(),
                'data' => [
                    'synthetic' => true,
                    'do_not_publish' => true,
                    'source_app' => '2261-better',
                    'created_by' => $claimRating ? 'claim_rating_ai_generation' : 'claim_rating_planning',
                    'created_from_claim_rating_id' => $claimRating?->id,
                    'persona' => $persona,
                    'username_generation' => $allocation,
                    'name_mode' => $nameMode,
                    'email_profile' => [
                        'domain' => $emailDomain,
                        'source' => $emailProfile['source'],
                        'excluded_domains' => self::excludedEmailDomains(),
                    ],
                    'privacy_settings' => $privacySettings,
                    'visibility_profile' => [
                        'ratings_name_visible_to' => data_get($privacySettings, 'ratings.name_visibility', 'none'),
                        'ratings_avatar_visible_to' => data_get($privacySettings, 'ratings.avatar_visibility', 'none'),
                    ],
                ],
            ]);

            if ($claimRating) {
                $claimRating->forceFill([
                    'synthetic_rating_user_id' => $syntheticUser->id,
                    'base_user_id' => null,
                ])->saveQuietly();
            }

            return $syntheticUser;
        }, $token);
    }

    public static function ensureForClaimRating(ClaimRating $claimRating): self
    {
        if ($claimRating->synthetic_rating_user_id) {
            $syntheticUser = self::withTrashed()->find((int) $claimRating->synthetic_rating_user_id);

            if ($syntheticUser) {
                if ($syntheticUser->trashed()) {
                    $syntheticUser->restore();
                }

                return $syntheticUser;
            }
        }

        $profile = data_get($claimRating->data, 'planning.synthetic_user_profile');
        $profile = is_array($profile) ? $profile : [];
        $email = (string) ($profile['email'] ?? '');

        if (self::isUsableSyntheticEmail($email)) {
            $nameMode = self::syntheticUserNameMode();
            $privacySettings = data_get($profile, 'privacy_settings', self::privacySettingsFromAnalysis());
            $privacySettings = is_array($privacySettings) ? $privacySettings : self::privacySettingsFromAnalysis();
            $privacySettings = self::privacySettingsForNameMode($privacySettings, $nameMode);
            $persona = data_get($profile, 'persona');
            $identityGenerator = new SyntheticIdentityGenerator;
            $persona = is_array($persona) ? $persona : $identityGenerator->persona($privacySettings);
            $syntheticUser = self::withTrashed()->where('email', $email)->first();

            if (! $syntheticUser) {
                $generationPersona = self::generationPersona($persona, $claimRating->scheduled_for);
                $syntheticUser = app(UsernameAllocator::class)->create($generationPersona, function (array $allocation) use ($claimRating, $profile, $email, $nameMode, $privacySettings, $persona): self {
                    $syntheticUser = self::withTrashed()->firstOrCreate(
                        ['email' => $email],
                        [
                            'base_user_id' => $claimRating->base_user_id,
                            'name' => $allocation['username'],
                            'first_name' => $persona['first_name'] ?? null,
                            'last_name' => $persona['last_name'] ?? null,
                            'username' => $allocation['username'],
                            'email_domain' => self::domainFromEmail($email),
                            'role' => (string) ($profile['role'] ?? 'guest'),
                            'status' => (bool) ($profile['status'] ?? true),
                            'email_verified_at' => (string) ($profile['email_verified_at'] ?? now()->toDateTimeString()),
                            'data' => [
                                'synthetic' => true,
                                'do_not_publish' => true,
                                'source_app' => '2261-better',
                                'created_from_claim_rating_id' => $claimRating->id,
                                'persona' => $persona,
                                'username_generation' => $allocation,
                                'name_mode' => $nameMode,
                                'email_profile' => [
                                    'domain' => self::domainFromEmail($email),
                                    'source' => data_get($profile, 'email_profile.source', 'planning_profile'),
                                    'excluded_domains' => self::excludedEmailDomains(),
                                ],
                                'privacy_settings' => $privacySettings,
                            ],
                        ]
                    );
                    $claimRating->forceFill([
                        'synthetic_rating_user_id' => $syntheticUser->id,
                        'base_user_id' => $claimRating->base_user_id ?: $syntheticUser->base_user_id,
                    ])->saveQuietly();

                    return $syntheticUser;
                }, (string) Str::random(12), (string) ($profile['username'] ?? ''));
            }

            if ($syntheticUser->trashed()) {
                $syntheticUser->restore();
            }
        } else {
            $syntheticUser = self::createForClaimRating($claimRating);
        }

        $claimRating->forceFill([
            'synthetic_rating_user_id' => $syntheticUser->id,
            'base_user_id' => $claimRating->base_user_id ?: $syntheticUser->base_user_id,
        ])->saveQuietly();

        return $syntheticUser;
    }

    private static function generationPersona(array $persona, ?DateTimeInterface $plannedFor): array
    {
        if ($plannedFor) {
            $persona['username_generation_day'] = \DateTimeImmutable::createFromInterface($plannedFor)
                ->setTimezone(new \DateTimeZone((string) config('app.timezone', 'Europe/Berlin')))
                ->format('Y-m-d');
        }

        return $persona;
    }

    /**
     * @return array<string, mixed>
     */
    public function publicProfile(): array
    {
        return [
            'id' => $this->id,
            'base_user_id' => $this->base_user_id,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'display_name' => trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->name,
            'email' => $this->email,
            'email_domain' => $this->email_domain,
            'role' => $this->role,
            'status' => $this->status,
            'email_verified_at' => $this->email_verified_at?->toDateTimeString() ?? now()->toDateTimeString(),
            'persona' => data_get($this->data ?? [], 'persona', []),
            'username_generation' => data_get($this->data ?? [], 'username_generation', []),
            'privacy_settings' => $this->privacySettings(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function privacySettings(): array
    {
        $settings = data_get($this->data ?? [], 'privacy_settings');

        return is_array($settings) ? $settings : self::defaultPrivacySettings();
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

    /**
     * @return array<string, mixed>
     */
    private static function privacySettingsFromAnalysis(): array
    {
        $analysis = Setting::getValue('rating_generation', 'analysis');
        $privacyDistributions = is_array($analysis)
            ? data_get($analysis, 'user_stats.privacy_distributions', [])
            : [];
        $privacyDistributions = is_array($privacyDistributions) ? $privacyDistributions : [];

        return [
            'comments' => [
                'name_visibility' => self::weightedPrivacyValue($privacyDistributions['comments_name_visibility'] ?? []),
                'avatar_visibility' => self::weightedPrivacyValue($privacyDistributions['comments_avatar_visibility'] ?? []),
            ],
            'ratings' => [
                'name_visibility' => self::weightedPrivacyValue($privacyDistributions['ratings_name_visibility'] ?? []),
                'avatar_visibility' => self::weightedPrivacyValue($privacyDistributions['ratings_avatar_visibility'] ?? []),
            ],
        ];
    }

    private static function weightedPrivacyValue(mixed $distribution): string
    {
        $distribution = is_array($distribution) ? $distribution : [];
        $weights = [];

        foreach ($distribution as $item) {
            if (! is_array($item)) {
                continue;
            }

            $value = (string) ($item['value'] ?? 'none');

            if (! in_array($value, ['all', 'users', 'none'], true)) {
                $value = 'none';
            }

            $weight = max(0.0, (float) ($item['count'] ?? $item['percent'] ?? 0));

            if ($weight > 0) {
                $weights[$value] = ($weights[$value] ?? 0) + $weight;
            }
        }

        if ($weights === []) {
            return 'none';
        }

        $target = lcg_value() * array_sum($weights);
        $cursor = 0.0;

        foreach ($weights as $value => $weight) {
            $cursor += $weight;

            if ($target <= $cursor) {
                return $value;
            }
        }

        return 'none';
    }

    /**
     * @return array<string, mixed>
     */
    private static function defaultPrivacySettings(): array
    {
        return [
            'comments' => [
                'name_visibility' => 'none',
                'avatar_visibility' => 'none',
            ],
            'ratings' => [
                'name_visibility' => 'none',
                'avatar_visibility' => 'none',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function privacySettingsForNameMode(array $privacySettings, string $nameMode): array
    {
        $privacySettings = array_replace_recursive(self::defaultPrivacySettings(), $privacySettings);

        $privacySettings['comments']['name_visibility'] = 'all';
        $privacySettings['ratings']['name_visibility'] = 'all';

        return $privacySettings;
    }

    private static function syntheticUserNameMode(): string
    {
        return 'realistic';
    }

    /**
     * @return array{domain: string, source: string}
     */
    private static function emailDomainProfile(SyntheticIdentityGenerator $identityGenerator): array
    {
        $analysis = Setting::getValue('rating_generation', 'analysis');
        $domains = is_array($analysis)
            ? data_get($analysis, 'user_stats.email_domains', [])
            : [];
        $domains = is_array($domains) ? $domains : [];
        $weights = [];

        foreach ($domains as $item) {
            if (! is_array($item)) {
                continue;
            }

            $domain = self::normalizeEmailDomain($item['domain'] ?? null);

            if ($domain === null || self::isExcludedEmailDomain($domain)) {
                continue;
            }

            $weight = max(0.0, (float) ($item['count'] ?? $item['percent'] ?? 0));

            if ($weight > 0) {
                $weights[$domain] = ($weights[$domain] ?? 0) + $weight;
            }
        }

        if ($weights === []) {
            return [
                'domain' => $identityGenerator->fallbackEmailDomain(),
                'source' => 'fallback_provider_pool',
            ];
        }

        $target = lcg_value() * array_sum($weights);
        $cursor = 0.0;

        foreach ($weights as $domain => $weight) {
            $cursor += $weight;

            if ($target <= $cursor) {
                return [
                    'domain' => $domain,
                    'source' => 'rating_analysis',
                ];
            }
        }

        return [
            'domain' => (string) array_key_first($weights),
            'source' => 'rating_analysis',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function excludedEmailDomains(): array
    {
        return [
            'regulierungs-check.de',
        ];
    }

    private static function isExcludedEmailDomain(string $domain): bool
    {
        foreach (self::excludedEmailDomains() as $excludedDomain) {
            if ($domain === $excludedDomain || str_ends_with($domain, '.'.$excludedDomain)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeEmailDomain(mixed $domain): ?string
    {
        if (! is_string($domain)) {
            return null;
        }

        $domain = strtolower(trim($domain));

        if ($domain === '' || ! str_contains($domain, '.') || str_contains($domain, '@')) {
            return null;
        }

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain) === 1 ? $domain : null;
    }

    private static function isUsableSyntheticEmail(string $email): bool
    {
        $domain = self::domainFromEmail($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
            && $domain !== null
            && ! self::isExcludedEmailDomain($domain);
    }

    private static function domainFromEmail(mixed $email): ?string
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return null;
        }

        return self::normalizeEmailDomain(substr(strrchr($email, '@') ?: '', 1));
    }
}
