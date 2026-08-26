<?php

namespace Tests\Unit;

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
            $this->assertSame(3, $persona['identity_version']);
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
    }

    public function test_model_stores_a_real_display_name_but_keeps_internal_synthetic_metadata(): void
    {
        $this->configureModelDatabase();

        $syntheticUser = SyntheticRatingUser::createForClaimRating();
        $persona = $syntheticUser->data['persona'];

        $this->assertSame($persona['display_name'], $syntheticUser->name);
        $this->assertSame($persona['first_name'], $syntheticUser->first_name);
        $this->assertSame($persona['last_name'], $syntheticUser->last_name);
        $this->assertNotSame($syntheticUser->name, $syntheticUser->username);
        $this->assertTrue($syntheticUser->data['synthetic']);
        $this->assertSame('2261-better-testperson', $persona['synthetic_marker']);
        $this->assertSame(3, $persona['identity_version']);
        $this->assertSame('fallback_provider_pool', $syntheticUser->data['email_profile']['source']);
        $this->assertStringNotContainsString('synthetic', $syntheticUser->username.$syntheticUser->email);
        $this->assertStringNotContainsString('2261', $syntheticUser->username.$syntheticUser->email);
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
    }
}
