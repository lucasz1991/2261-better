<?php

namespace Tests\Unit;

use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Support\Rating\SyntheticIdentityGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyntheticIdentityGeneratorTest extends TestCase
{
    private SyntheticIdentityGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-26 12:00:00');
        $this->generator = new SyntheticIdentityGenerator;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_personas_are_varied_and_demographically_consistent(): void
    {
        $displayNames = [];
        $nameSets = [];
        $regions = [];
        $householdTypes = [];
        $contactChannels = [];
        $writingStyles = [];

        for ($i = 0; $i < 400; $i++) {
            $persona = $this->generator->persona([
                'ratings' => ['name_visibility' => 'all'],
            ]);
            $age = 2026 - (int) $persona['birth_year'];
            [$minimumAge, $maximumAge] = $this->ageBounds($persona['age_range']);

            $this->assertGreaterThanOrEqual($minimumAge, $age);
            $this->assertLessThanOrEqual($maximumAge, $age);
            $this->assertGreaterThanOrEqual((int) $persona['birth_year'] + 18, (int) $persona['customer_since_year']);
            $this->assertLessThanOrEqual(2025, (int) $persona['customer_since_year']);
            $this->assertSame(2026 - (int) $persona['customer_since_year'], $persona['customer_tenure_years']);
            $this->assertSame(5, $persona['identity_version']);
            $this->assertSame('pseudonym_with_optional_context', $persona['username_mode']);
            $this->assertSame(
                trim($persona['first_name'].' '.$persona['last_name']),
                $persona['display_name']
            );
            $this->assertHouseholdIsConsistent($persona);
            $this->assertStringNotContainsString('2261', strtolower($persona['display_name'].$persona['username_alias']));
            $this->assertStringNotContainsString('synthetic', strtolower($persona['display_name'].$persona['username_alias']));

            $displayNames[$persona['display_name']] = true;
            $nameSets[$persona['name_set']] = true;
            $regions[$persona['region']] = true;
            $householdTypes[$persona['household_type']] = true;
            $contactChannels[$persona['preferred_contact_channel']] = true;
            $writingStyles[$persona['review_writing_style']] = true;
        }

        $this->assertGreaterThan(340, count($displayNames));
        $this->assertGreaterThanOrEqual(7, count($nameSets));
        $this->assertGreaterThanOrEqual(13, count($regions));
        $this->assertGreaterThanOrEqual(5, count($householdTypes));
        $this->assertGreaterThanOrEqual(5, count($contactChannels));
        $this->assertGreaterThanOrEqual(8, count($writingStyles));
    }

    public function test_name_pool_expansions_double_each_source_pool_without_duplicates(): void
    {
        $reflection = new \ReflectionClass(SyntheticIdentityGenerator::class);
        $generalFirstNames = $reflection->getConstant('FIRST_NAMES_BY_AGE');
        $generalFirstNameExpansions = $reflection->getConstant('GENERAL_FIRST_NAME_EXPANSIONS');
        $additionalNameSets = $reflection->getConstant('ADDITIONAL_NAME_SETS');
        $additionalNameSetExpansions = $reflection->getConstant('ADDITIONAL_NAME_SET_EXPANSIONS');
        $allOriginalLastNames = $reflection->getConstant('LAST_NAMES');
        $generalLastNameExpansion = $reflection->getConstant('GENERAL_LAST_NAME_EXPANSION');

        foreach ($generalFirstNames as $ageRange => $names) {
            $expansion = $generalFirstNameExpansions[$ageRange];
            $merged = array_merge($names, $expansion);

            $this->assertCount(count($names), $expansion, "Allgemeiner Vornamen-Pool {$ageRange} ist nicht verdoppelt.");
            $this->assertCount(count($merged), array_unique($merged), "Allgemeiner Vornamen-Pool {$ageRange} enthält Duplikate.");
        }

        $firstAdditionalLastName = array_search('Aydin', $allOriginalLastNames, true);
        $this->assertIsInt($firstAdditionalLastName);
        $generalLastNames = array_slice($allOriginalLastNames, 0, $firstAdditionalLastName);
        $mergedGeneralLastNames = array_merge($generalLastNames, $generalLastNameExpansion);

        $this->assertCount(count($generalLastNames), $generalLastNameExpansion);
        $this->assertCount(count($mergedGeneralLastNames), array_unique($mergedGeneralLastNames));
        $this->assertSame(array_keys($additionalNameSets), array_keys($additionalNameSetExpansions));

        foreach ($additionalNameSets as $nameSet => $sourcePools) {
            $expansionPools = $additionalNameSetExpansions[$nameSet];

            foreach ($sourcePools['first_names'] as $ageRange => $names) {
                $expansion = $expansionPools['first_names'][$ageRange];
                $merged = array_merge($names, $expansion);

                $this->assertCount(count($names), $expansion, "Vornamen-Pool {$nameSet}/{$ageRange} ist nicht verdoppelt.");
                $this->assertCount(count($merged), array_unique($merged), "Vornamen-Pool {$nameSet}/{$ageRange} enthält Duplikate.");
            }

            $mergedLastNames = array_merge($sourcePools['last_names'], $expansionPools['last_names']);

            $this->assertCount(count($sourcePools['last_names']), $expansionPools['last_names'], "Nachnamen-Pool {$nameSet} ist nicht verdoppelt.");
            $this->assertCount(count($mergedLastNames), array_unique($mergedLastNames), "Nachnamen-Pool {$nameSet} enthält Duplikate.");
        }
    }

    public function test_every_supported_city_has_realistic_username_codes(): void
    {
        $reflection = new \ReflectionClass(SyntheticIdentityGenerator::class);
        $regions = $reflection->getConstant('REGIONS');
        $locationCodes = $reflection->getConstant('USERNAME_LOCATION_CODES');
        $cities = array_unique(array_column($regions, 'city'));

        $this->assertCount(count($cities), $locationCodes);

        foreach ($cities as $city) {
            $this->assertArrayHasKey($city, $locationCodes);
            $this->assertCount(2, $locationCodes[$city]);

            foreach ($locationCodes[$city] as $code) {
                $this->assertMatchesRegularExpression('/^[a-z0-9]{1,5}$/', $code);
            }
        }

        $this->assertSame(['hh', '040'], $locationCodes['Hamburg']);
        $this->assertSame(['b', '030'], $locationCodes['Berlin']);
    }

    public function test_usernames_and_emails_are_unique_varied_and_marker_free(): void
    {
        $usernames = [];
        $emails = [];
        $domains = ['gmail.com', 'web.de', 'gmx.de', 'outlook.de', 'icloud.com'];
        $structures = ['dot' => 0, 'dash_or_underscore' => 0, 'compact' => 0, 'number' => 0];
        $aliasUsernames = 0;
        $aliasEmails = 0;

        for ($i = 0; $i < 300; $i++) {
            $persona = $this->generator->persona([
                'ratings' => ['name_visibility' => 'all'],
            ]);
            $username = $this->generator->username(
                $persona,
                fn (string $candidate): bool => isset($usernames[$candidate]),
                Str::random(12)
            );
            $email = $this->generator->email(
                $persona,
                $domains[$i % count($domains)],
                fn (string $candidate): bool => isset($emails[$candidate]),
                Str::random(12)
            );

            $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9._-]{1,41}$/', $username);
            $this->assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
            $this->assertStringNotContainsString('synthetic', $username.$email);
            $this->assertStringNotContainsString('2261', $username.$email);
            $this->assertStringNotContainsString('example.invalid', $email);
            $this->assertUsernameDoesNotRevealPersonaName($username, $persona);
            $this->assertTrue($this->generator->isPseudonymUsername($persona, $username));

            $usernames[$username] = true;
            $emails[$email] = true;
            $structures['dot'] += str_contains($username, '.') ? 1 : 0;
            $structures['dash_or_underscore'] += preg_match('/[-_]/', $username) === 1 ? 1 : 0;
            $structures['compact'] += preg_match('/^[a-z]+$/', $username) === 1 ? 1 : 0;
            $structures['number'] += preg_match('/\d/', $username) === 1 ? 1 : 0;

            $alias = Str::of($persona['username_alias'])->ascii()->lower()->toString();
            $aliasUsernames += str_contains($username, $alias) ? 1 : 0;
            $aliasEmails += str_contains(strstr($email, '@', true), $alias) ? 1 : 0;
        }

        $this->assertCount(300, $usernames);
        $this->assertCount(300, $emails);
        $this->assertCount(4, array_filter($structures));
        $this->assertGreaterThanOrEqual(12, $aliasUsernames);
        $this->assertGreaterThanOrEqual(2, $aliasEmails);
    }

    public function test_username_generation_rejects_name_based_aliases_and_pool_collisions(): void
    {
        $persona = [
            'first_name' => 'Wald',
            'last_name' => 'Fuchs-Berg',
            'birth_year' => 1988,
            'customer_since_year' => 2014,
            'username_alias' => 'wald.fuchs.berg1988',
        ];
        $usernames = [];

        $this->assertFalse($this->generator->isPseudonymUsername($persona, 'wald.fuchs88'));
        $this->assertFalse($this->generator->isPseudonymUsername($persona, 'wf88'));
        $this->assertFalse($this->generator->isPseudonymUsername($persona, 'berg2014'));
        $this->assertTrue($this->generator->isPseudonymUsername($persona, 'ruhepol'));
        $this->assertTrue($this->generator->isPseudonymUsername($persona, 'ruhepol_wfb88'));

        for ($i = 0; $i < 200; $i++) {
            $username = $this->generator->username(
                $persona,
                fn (string $candidate): bool => isset($usernames[$candidate]),
                Str::random(12)
            );

            $this->assertUsernameDoesNotRevealPersonaName($username, $persona);
            $this->assertTrue($this->generator->isPseudonymUsername($persona, $username));
            $usernames[$username] = true;
        }

        $this->assertCount(200, $usernames);
    }

    public function test_usernames_can_include_initials_birth_short_and_real_location_codes(): void
    {
        $persona = [
            'first_name' => 'Xaver',
            'last_name' => 'Quendler',
            'birth_year' => 1977,
            'customer_since_year' => 2014,
            'city' => 'Hamburg',
            'username_alias' => 'abendfeder',
        ];
        $usernames = [];
        $contexts = ['initials' => 0, 'birth_short' => 0, 'location' => 0];

        for ($i = 0; $i < 600; $i++) {
            $username = $this->generator->username(
                $persona,
                fn (string $candidate): bool => isset($usernames[$candidate]),
                Str::random(12)
            );
            $normalizedUsername = $this->normalizedIdentifier($username);

            $this->assertTrue($this->generator->isPseudonymUsername($persona, $username));
            $this->assertUsernameDoesNotRevealPersonaName($username, $persona);

            $contexts['initials'] += str_contains($normalizedUsername, 'xq') ? 1 : 0;
            $contexts['birth_short'] += str_ends_with($normalizedUsername, '77') ? 1 : 0;
            $contexts['location'] += str_contains($normalizedUsername, 'hh') || str_contains($normalizedUsername, '040') ? 1 : 0;
            $usernames[$username] = true;
        }

        $this->assertCount(600, $usernames);
        $this->assertGreaterThanOrEqual(45, $contexts['initials']);
        $this->assertGreaterThanOrEqual(45, $contexts['birth_short']);
        $this->assertGreaterThanOrEqual(45, $contexts['location']);
    }

    public function test_fallback_email_providers_are_varied_and_realistic(): void
    {
        $domains = [];

        for ($i = 0; $i < 700; $i++) {
            $domain = $this->generator->fallbackEmailDomain();

            $this->assertMatchesRegularExpression('/^[a-z0-9.-]+\.[a-z]{2,}$/', $domain);
            $this->assertNotSame('example.invalid', $domain);
            $this->assertNotSame('regulierungs-check.de', $domain);

            $domains[$domain] = true;
        }

        $this->assertGreaterThanOrEqual(18, count($domains));
    }

    public function test_collision_retries_generate_a_different_identifier(): void
    {
        $persona = [
            'first_name' => 'Anna',
            'last_name' => 'Schneider',
            'birth_year' => 1988,
            'city' => 'Köln',
        ];
        $checks = 0;

        $username = $this->generator->username(
            $persona,
            function (string $candidate) use (&$checks): bool {
                $checks++;

                return $checks <= 4;
            },
            'collisiontoken'
        );

        $this->assertSame(5, $checks);
        $this->assertMatchesRegularExpression('/^[a-z0-9][a-z0-9._-]+$/', $username);
        $this->assertStringNotContainsString('collisiontoken', $username);
        $this->assertUsernameDoesNotRevealPersonaName($username, $persona);
        $this->assertTrue($this->generator->isPseudonymUsername($persona, $username));
    }

    public function test_username_fallback_after_many_collisions_stays_a_pseudonym(): void
    {
        $persona = [
            'first_name' => 'Anna',
            'last_name' => 'Schneider',
            'birth_year' => 1988,
            'customer_since_year' => 2014,
        ];
        $checks = 0;

        $username = $this->generator->username(
            $persona,
            function (string $candidate) use (&$checks): bool {
                $checks++;

                return $checks <= 64;
            },
            'fallbacktoken'
        );

        $this->assertSame(65, $checks);
        $this->assertTrue($this->generator->isPseudonymUsername($persona, $username));
        $this->assertStringNotContainsString('fallbacktoken', $username);
    }

    public function test_model_uses_generated_username_for_both_account_name_fields(): void
    {
        $this->configureModelDatabase();

        $syntheticUser = SyntheticRatingUser::createForClaimRating();
        $persona = $syntheticUser->data['persona'];

        $this->assertSame($syntheticUser->username, $syntheticUser->name);
        $this->assertSame($persona['first_name'], $syntheticUser->first_name);
        $this->assertSame($persona['last_name'], $syntheticUser->last_name);
        $this->assertSame($persona['display_name'], $syntheticUser->publicProfile()['display_name']);
        $this->assertTrue($syntheticUser->data['synthetic']);
        $this->assertSame('2261-better-testperson', $persona['synthetic_marker']);
        $this->assertSame(5, $persona['identity_version']);
        $this->assertSame('pseudonym_with_optional_context', $persona['username_mode']);
        $this->assertSame('fallback_provider_pool', $syntheticUser->data['email_profile']['source']);
        $this->assertStringNotContainsString('synthetic', $syntheticUser->username.$syntheticUser->email);
        $this->assertStringNotContainsString('2261', $syntheticUser->username.$syntheticUser->email);
        $this->assertUsernameDoesNotRevealPersonaName($syntheticUser->username, $persona);
        $this->assertTrue($this->generator->isPseudonymUsername($persona, $syntheticUser->username));
    }

    public function test_legacy_planning_profile_gets_a_new_pseudonym_instead_of_reusing_its_name_based_username(): void
    {
        $this->configureModelDatabase();
        $persona = [
            'first_name' => 'Anna',
            'last_name' => 'Schneider',
            'display_name' => 'Anna Schneider',
            'birth_year' => 1988,
            'customer_since_year' => 2014,
        ];
        $claimRating = ClaimRating::create([
            'data' => [
                'planning' => [
                    'synthetic_user_profile' => [
                        'name' => 'Anna Schneider',
                        'username' => 'anna.schneider88',
                        'email' => 'anna.schneider88@gmx.de',
                        'persona' => $persona,
                        'privacy_settings' => [
                            'ratings' => ['name_visibility' => 'all', 'avatar_visibility' => 'none'],
                            'comments' => ['name_visibility' => 'all', 'avatar_visibility' => 'none'],
                        ],
                    ],
                ],
            ],
        ]);

        $syntheticUser = SyntheticRatingUser::ensureForClaimRating($claimRating);

        $this->assertNotSame('anna.schneider88', $syntheticUser->username);
        $this->assertSame($syntheticUser->username, $syntheticUser->name);
        $this->assertTrue($this->generator->isPseudonymUsername($persona, $syntheticUser->username));
        $this->assertSame($syntheticUser->id, $claimRating->fresh()->synthetic_rating_user_id);
    }

    /**
     * @return array{int, int}
     */
    private function ageBounds(string $ageRange): array
    {
        return match ($ageRange) {
            '25-34' => [25, 34],
            '35-44' => [35, 44],
            '45-54' => [45, 54],
            '55-64' => [55, 64],
            default => [65, 84],
        };
    }

    /**
     * @param  array<string, mixed>  $persona
     */
    private function assertHouseholdIsConsistent(array $persona): void
    {
        $householdSize = (int) $persona['household_size'];
        $children = (int) $persona['children_count'];

        match ($persona['household_type']) {
            'Single-Haushalt' => $this->assertTrue($householdSize === 1 && $children === 0),
            'Paar ohne Kinder' => $this->assertTrue($householdSize === 2 && $children === 0),
            'Familie mit Kindern' => $this->assertTrue($children >= 1 && $householdSize === $children + 2),
            'Alleinerziehend' => $this->assertTrue($children >= 1 && $householdSize === $children + 1),
            'Wohngemeinschaft' => $this->assertTrue($householdSize >= 2 && $children === 0),
            'Mehrgenerationenhaushalt' => $this->assertTrue($householdSize >= 3 && $householdSize >= $children + 2),
            default => $this->fail('Unbekannter Haushaltstyp: '.$persona['household_type']),
        };
    }

    /**
     * @param  array<string, mixed>  $persona
     */
    private function assertUsernameDoesNotRevealPersonaName(string $username, array $persona): void
    {
        $normalizedUsername = $this->normalizedIdentifier($username);
        $nameComponents = [];

        foreach (['first_name', 'last_name'] as $field) {
            $rawName = trim((string) ($persona[$field] ?? ''));
            $fullName = $this->normalizedIdentifier($rawName);

            if (strlen($fullName) >= 3) {
                $nameComponents[] = $fullName;
            }

            foreach (preg_split('/[\s-]+/u', $rawName) ?: [] as $part) {
                $part = $this->normalizedIdentifier($part);

                if (strlen($part) >= 3) {
                    $nameComponents[] = $part;
                }
            }
        }

        foreach (array_unique($nameComponents) as $nameComponent) {
            $this->assertStringNotContainsString($nameComponent, $normalizedUsername);
        }
    }

    private function normalizedIdentifier(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    private function configureModelDatabase(): void
    {
        config()->set('database.default', 'synthetic_identity_test');
        config()->set('database.connections.synthetic_identity_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('synthetic_identity_test');
        Cache::flush();

        Schema::connection('synthetic_identity_test')->create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::connection('synthetic_identity_test')->create('synthetic_rating_users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('base_user_id')->nullable();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('email_domain')->nullable();
            $table->string('role')->default('guest');
            $table->boolean('status')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('synthetic_identity_test')->create('claim_ratings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('base_user_id')->nullable();
            $table->unsignedBigInteger('synthetic_rating_user_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
