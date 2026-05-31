<?php

namespace App\Models;

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

    public static function createForClaimRating(?ClaimRating $claimRating = null): self
    {
        $token = strtolower((string) Str::random(12));
        $nameMode = self::syntheticUserNameMode();
        $privacySettings = self::privacySettingsForNameMode(self::privacySettingsFromAnalysis(), $nameMode);
        $persona = self::syntheticPersona($privacySettings, $nameMode);
        $username = self::usernameFromPersona($persona, $token);
        $emailDomain = self::emailDomainFromAnalysis();
        $email = self::syntheticEmail($persona, $token, $emailDomain);

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
                'name_mode' => $nameMode,
                'email_profile' => [
                    'domain' => $emailDomain,
                    'source' => $emailDomain === 'example.invalid' ? 'fallback' : 'rating_analysis',
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

        if (self::isSynthetic2261Email($email)) {
            $nameMode = self::syntheticUserNameMode();
            $privacySettings = data_get($profile, 'privacy_settings', self::privacySettingsFromAnalysis());
            $privacySettings = is_array($privacySettings) ? $privacySettings : self::privacySettingsFromAnalysis();
            $privacySettings = self::privacySettingsForNameMode($privacySettings, $nameMode);
            $persona = data_get($profile, 'persona');
            $persona = is_array($persona)
                ? $persona
                : self::syntheticPersona($privacySettings, $nameMode);
            $profileName = (string) ($persona['display_name'] ?? $profile['name'] ?? 'Anonyme Testperson');
            $username = self::usernameFromPersona($persona, (string) Str::random(12));

            if (str_starts_with($profileName, 'Interner Testnutzer 2261')) {
                $profileName = $persona['display_name'];
            }

            $syntheticUser = self::withTrashed()->firstOrCreate(
                ['email' => $email],
                [
                    'base_user_id' => $claimRating->base_user_id,
                    'name' => (string) ($profile['username'] ?? $username),
                    'first_name' => $persona['first_name'] ?? null,
                    'last_name' => $persona['last_name'] ?? null,
                    'username' => (string) ($profile['username'] ?? $username),
                    'email_domain' => self::domainFromEmail($email) ?? 'example.invalid',
                    'role' => (string) ($profile['role'] ?? 'guest'),
                    'status' => (bool) ($profile['status'] ?? true),
                    'email_verified_at' => (string) ($profile['email_verified_at'] ?? now()->toDateTimeString()),
                    'data' => [
                        'synthetic' => true,
                        'do_not_publish' => true,
                        'source_app' => '2261-better',
                        'created_from_claim_rating_id' => $claimRating->id,
                        'persona' => $persona,
                        'name_mode' => $nameMode,
                        'email_profile' => [
                            'domain' => self::domainFromEmail($email) ?? 'example.invalid',
                            'source' => data_get($profile, 'email_profile.source', 'planning_profile'),
                            'excluded_domains' => self::excludedEmailDomains(),
                        ],
                        'privacy_settings' => $privacySettings,
                    ],
                ]
            );

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

    private static function emailDomainFromAnalysis(): string
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
            return 'example.invalid';
        }

        $target = lcg_value() * array_sum($weights);
        $cursor = 0.0;

        foreach ($weights as $domain => $weight) {
            $cursor += $weight;

            if ($target <= $cursor) {
                return $domain;
            }
        }

        return (string) array_key_first($weights);
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

    private static function syntheticEmail(array $persona, string $token, string $domain): string
    {
        $namePart = (string) ($persona['alias'] ?? $persona['display_name'] ?? 'testperson');

        if (($persona['first_name'] ?? null) && ($persona['last_name'] ?? null)) {
            $namePart = $persona['first_name'].'.'.$persona['last_name'];
        }

        $namePart = Str::of($namePart)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->limit(32, '')
            ->trim('.')
            ->toString();

        if ($namePart === '') {
            $namePart = 'testperson';
        }

        $token = substr(preg_replace('/[^a-z0-9]/', '', strtolower($token)) ?: Str::random(8), 0, 8);

        return "{$namePart}-{$token}@{$domain}";
    }

    private static function usernameFromPersona(array $persona, string $token): string
    {
        $namePart = (string) ($persona['display_name'] ?? 'testperson');

        if (($persona['first_name'] ?? null) && ($persona['last_name'] ?? null)) {
            $namePart = $persona['first_name'].'.'.$persona['last_name'];
        }

        $username = Str::of($namePart)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->limit(32, '')
            ->trim('.')
            ->toString();

        if ($username === '') {
            $username = 'testperson';
        }

        $token = substr(preg_replace('/[^a-z0-9]/', '', strtolower($token)) ?: Str::random(8), 0, 4);

        return "{$username}.{$token}";
    }

    private static function isSynthetic2261Email(string $email): bool
    {
        $domain = self::domainFromEmail($email);

        return str_starts_with($email, 'synthetic-2261-')
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

    /**
     * @return array<string, mixed>
     */
    private static function syntheticPersona(array $privacySettings, string $nameMode): array
    {
        $firstNames = [
            // Weibliche Namen
            'Anna', 'Laura', 'Sophie', 'Miriam', 'Nina', 'Katharina', 'Lea', 'Julia',
            'Sarah', 'Nadine', 'Claudia', 'Melanie', 'Eva', 'Tanja', 'Maren', 'Christina',
            'Maria', 'Lisa', 'Barbara', 'Petra', 'Sandra', 'Daniela', 'Sabine', 'Angelika',
            'Silke', 'Doris', 'Christine', 'Monika', 'Elvira', 'Marianne', 'Renate', 'Gisela',
            'Kerstin', 'Susanne', 'Heike', 'Beate', 'Ingrid', 'Ursula', 'Helene', 'Karin',
            'Martina', 'Birgit', 'Michaela', 'Cornelia', 'Sylvia', 'Vera', 'Annette', 'Brigitte',
            'Christiane', 'Annegret', 'Inge', 'Edith', 'Heidrun', 'Margot', 'Sigrid', 'Waltraud',
            'Liesbeth', 'Gerda', 'Marlene', 'Rosemarie', 'Lieselotte', 'Christa', 'Karlotta', 'Anika',
            // Männliche Namen
            'Michael', 'Daniel', 'Thomas', 'Stefan', 'Markus', 'Jan', 'Lukas', 'Tim',
            'Christian', 'Andreas', 'Sebastian', 'Florian', 'Matthias', 'Philipp', 'Martin', 'Oliver',
            'Robert', 'Klaus', 'Rainer', 'Werner', 'Helmut', 'Dieter', 'Gerhard', 'Frank',
            'Hans', 'Horst', 'Juergen', 'Wolfgang', 'Bernhard', 'Friedrich', 'Karl', 'Alfons',
            'Hermann', 'Siegfried', 'Erwin', 'Wilfried', 'Edmund', 'Armin', 'Bruno', 'Udo',
            'Lothar', 'Gunter', 'Günther', 'Norbert', 'Heinz', 'Josef', 'Hubert', 'Alwin',
            'Erich', 'Oswald', 'Theodor', 'Leonhard', 'Willi', 'Otto', 'Adolf', 'Heribert',
            'Eckhard', 'Guenther', 'Reinhardt', 'Konrad', 'Eduard', 'Manfred', 'Wilmar', 'Volkmar',
        ];
        $lastNames = [
            'Meyer', 'Schneider', 'Fischer', 'Weber', 'Hoffmann', 'Wagner', 'Becker',
            'Schulz', 'Koch', 'Richter', 'Klein', 'Wolf', 'Neumann', 'Zimmermann',
            'Hartmann', 'Schmitt', 'Werner', 'Schmitz', 'Krueger', 'Lange', 'Schroeder',
            'Krause', 'Lehmann', 'Huber', 'Maier', 'Fuchs', 'Peters', 'Lang',
            'Mueller', 'Braun', 'Keller', 'Hoffmann', 'Schutz', 'Baum', 'Groß',
            'Loewe', 'Baecker', 'Hahn', 'Kramer', 'Germann', 'Heuer', 'Handler',
            'Beck', 'Gaertner', 'Raabe', 'Siegel', 'Knab', 'Steiner', 'Roth',
            'Seitz', 'Mayer', 'Erwin', 'Stieglitz', 'Ploch', 'Ostrowski', 'Sauer',
            'Zimmerman', 'Meier', 'Beier', 'Beyer', 'Kaiser', 'Kirst', 'Kister',
            'Klausen', 'Klasen', 'Klaus', 'Klausing', 'Klausnitzer', 'Klauss', 'Klaussman',
            'Scholz', 'Scholl', 'Schueler', 'Schueller', 'Schuhmann', 'Schuller', 'Schulte',
            'Schultze', 'Schuster', 'Schwaab', 'Schwabe', 'Schwab', 'Schwack', 'Schwager',
            'Schwalbe', 'Schwaller', 'Schwamm', 'Schwammberger', 'Schwander', 'Schwandt', 'Schwab',
            'Rienecker', 'Riedle', 'Riegel', 'Riehle', 'Rieker', 'Riel', 'Rieprich',
            'Riese', 'Riesinger', 'Riesselman', 'Riewald', 'Riewe', 'Riffel', 'Rige',
        ];
        $regions = [
            // Nordrhein-Westfalen
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Koeln', 'postal_code_area' => '50xxx'],
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Dortmund', 'postal_code_area' => '44xxx'],
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Duesseldorf', 'postal_code_area' => '40xxx'],
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Essen', 'postal_code_area' => '45xxx'],
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Duisburg', 'postal_code_area' => '47xxx'],
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Bochum', 'postal_code_area' => '44xxx'],
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Gelsenkirchen', 'postal_code_area' => '45xxx'],
            ['state' => 'Nordrhein-Westfalen', 'city' => 'Muenster', 'postal_code_area' => '48xxx'],
            // Bayern
            ['state' => 'Bayern', 'city' => 'Nuernberg', 'postal_code_area' => '90xxx'],
            ['state' => 'Bayern', 'city' => 'Muenchen', 'postal_code_area' => '80xxx'],
            ['state' => 'Bayern', 'city' => 'Regensburg', 'postal_code_area' => '93xxx'],
            ['state' => 'Bayern', 'city' => 'Augsburg', 'postal_code_area' => '86xxx'],
            ['state' => 'Bayern', 'city' => 'Wuerzburg', 'postal_code_area' => '97xxx'],
            ['state' => 'Bayern', 'city' => 'Bamberg', 'postal_code_area' => '96xxx'],
            ['state' => 'Bayern', 'city' => 'Ansbach', 'postal_code_area' => '91xxx'],
            ['state' => 'Bayern', 'city' => 'Bayreuth', 'postal_code_area' => '95xxx'],
            // Hessen
            ['state' => 'Hessen', 'city' => 'Frankfurt am Main', 'postal_code_area' => '60xxx'],
            ['state' => 'Hessen', 'city' => 'Wiesbaden', 'postal_code_area' => '65xxx'],
            ['state' => 'Hessen', 'city' => 'Darmstadt', 'postal_code_area' => '64xxx'],
            ['state' => 'Hessen', 'city' => 'Kassel', 'postal_code_area' => '34xxx'],
            ['state' => 'Hessen', 'city' => 'Offenbach am Main', 'postal_code_area' => '63xxx'],
            ['state' => 'Hessen', 'city' => 'Marburg', 'postal_code_area' => '35xxx'],
            // Sachsen
            ['state' => 'Sachsen', 'city' => 'Leipzig', 'postal_code_area' => '04xxx'],
            ['state' => 'Sachsen', 'city' => 'Dresden', 'postal_code_area' => '01xxx'],
            ['state' => 'Sachsen', 'city' => 'Chemnitz', 'postal_code_area' => '09xxx'],
            ['state' => 'Sachsen', 'city' => 'Zwickau', 'postal_code_area' => '08xxx'],
            ['state' => 'Sachsen', 'city' => 'Plauen', 'postal_code_area' => '08xxx'],
            // Niedersachsen
            ['state' => 'Niedersachsen', 'city' => 'Hannover', 'postal_code_area' => '30xxx'],
            ['state' => 'Niedersachsen', 'city' => 'Braunschweig', 'postal_code_area' => '38xxx'],
            ['state' => 'Niedersachsen', 'city' => 'Goettingen', 'postal_code_area' => '37xxx'],
            ['state' => 'Niedersachsen', 'city' => 'Osnabrueck', 'postal_code_area' => '49xxx'],
            ['state' => 'Niedersachsen', 'city' => 'Oldenburg', 'postal_code_area' => '26xxx'],
            // Baden-Württemberg
            ['state' => 'Baden-Wuerttemberg', 'city' => 'Stuttgart', 'postal_code_area' => '70xxx'],
            ['state' => 'Baden-Wuerttemberg', 'city' => 'Karlsruhe', 'postal_code_area' => '76xxx'],
            ['state' => 'Baden-Wuerttemberg', 'city' => 'Heidelberg', 'postal_code_area' => '69xxx'],
            ['state' => 'Baden-Wuerttemberg', 'city' => 'Ulm', 'postal_code_area' => '89xxx'],
            ['state' => 'Baden-Wuerttemberg', 'city' => 'Mannheim', 'postal_code_area' => '68xxx'],
            ['state' => 'Baden-Wuerttemberg', 'city' => 'Freiburg', 'postal_code_area' => '79xxx'],
            // Bremen, Hamburg, Berlin
            ['state' => 'Hamburg', 'city' => 'Hamburg', 'postal_code_area' => '20xxx'],
            ['state' => 'Berlin', 'city' => 'Berlin', 'postal_code_area' => '10xxx'],
            ['state' => 'Bremen', 'city' => 'Bremen', 'postal_code_area' => '28xxx'],
            // Schleswig-Holstein
            ['state' => 'Schleswig-Holstein', 'city' => 'Kiel', 'postal_code_area' => '24xxx'],
            ['state' => 'Schleswig-Holstein', 'city' => 'Luebeck', 'postal_code_area' => '23xxx'],
            // Brandenburg
            ['state' => 'Brandenburg', 'city' => 'Potsdam', 'postal_code_area' => '14xxx'],
            ['state' => 'Brandenburg', 'city' => 'Cottbus', 'postal_code_area' => '03xxx'],
            // Mecklenburg-Vorpommern
            ['state' => 'Mecklenburg-Vorpommern', 'city' => 'Rostock', 'postal_code_area' => '18xxx'],
            ['state' => 'Mecklenburg-Vorpommern', 'city' => 'Schwerin', 'postal_code_area' => '19xxx'],
            // Rheinland-Pfalz
            ['state' => 'Rheinland-Pfalz', 'city' => 'Mainz', 'postal_code_area' => '55xxx'],
            ['state' => 'Rheinland-Pfalz', 'city' => 'Ludwigshafen am Rhein', 'postal_code_area' => '67xxx'],
        ];
        $ageRanges = ['25-34', '35-44', '45-54', '55-64', '65+'];
        $households = ['Single-Haushalt', 'Paar ohne Kinder', 'Familie mit Kindern', 'Mehrpersonenhaushalt'];
        $contactPreferences = ['E-Mail', 'Telefon', 'Kundenportal', 'Brief'];
        $insuranceExperience = ['erstmaliger Schadenfall', 'gelegentliche Schadenfaelle', 'mehrere Vorfaelle in den letzten Jahren'];
        $devices = ['Smartphone', 'Notebook', 'Desktop', 'Tablet'];
        $occupations = ['Angestellt', 'Selbststaendig', 'Oeffentlicher Dienst', 'Ausbildung/Studium', 'Rentner/in'];
        $availabilityWindows = ['morgens', 'mittags', 'nachmittags', 'abends'];
        $customerSinceYears = range((int) now()->format('Y') - 18, (int) now()->format('Y') - 1);
        $claimChannels = ['Online-Portal', 'Telefon', 'E-Mail', 'Makler/Vermittler', 'Filiale'];

        $firstName = $firstNames[array_rand($firstNames)];
        $lastName = $lastNames[array_rand($lastNames)];
        $region = $regions[array_rand($regions)];
        $ageRange = $ageRanges[array_rand($ageRanges)];
        $ratingsNameVisibility = data_get($privacySettings, 'ratings.name_visibility', 'none');
        $visibleName = "{$firstName} {$lastName}";

        return [
            'synthetic_marker' => '2261-better-testperson',
            'name_mode' => 'realistic',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'alias' => null,
            'display_name' => $visibleName,

            'age_range' => $ageRange,
            'region' => $region['state'],
            'city' => $region['city'],
            'postal_code_area' => $region['postal_code_area'],
            'household_type' => $households[array_rand($households)],
            'occupation_group' => $occupations[array_rand($occupations)],
            'preferred_contact_channel' => $contactPreferences[array_rand($contactPreferences)],
            'usual_claim_channel' => $claimChannels[array_rand($claimChannels)],
            'insurance_experience' => $insuranceExperience[array_rand($insuranceExperience)],
            'customer_since_year' => $customerSinceYears[array_rand($customerSinceYears)],
            'availability_window' => $availabilityWindows[array_rand($availabilityWindows)],
            'device_context' => $devices[array_rand($devices)],
            'language' => 'de',
            'timezone' => 'Europe/Berlin',
            'is_named_publicly' => $ratingsNameVisibility !== 'none',
            'note' => 'Fiktives internes Testprofil ohne reale Personendaten.',
        ];
    }
}
