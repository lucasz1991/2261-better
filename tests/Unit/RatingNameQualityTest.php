<?php

namespace Tests\Unit;

use App\Livewire\Admin\ClaimRatings;
use App\Livewire\Admin\PlannedClaimRatings;
use App\Livewire\Admin\RatingNameQuality;
use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Models\User;
use App\Services\UsernameQualityAnalyzer;
use App\Services\UsernameQualityReportStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RatingNameQualityTest extends TestCase
{
    private bool $qualityAllowed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config()->set('database.connections.rating_quality_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'rating_quality_test');
        DB::purge('rating_quality_test');
        Cache::flush();
        Gate::define('ratings.view', fn (?User $user = null) => true);
        Gate::define('synthetic_users.quality', fn (?User $user = null) => $this->qualityAllowed);
        foreach ([
            '2026_05_29_000001_create_claim_ratings_table.php',
            '2026_05_30_000001_add_schedule_fields_to_claim_ratings_table.php',
            '2026_05_31_000001_add_base_user_id_to_claim_ratings_table.php',
            '2026_05_31_000002_create_synthetic_rating_users_table.php',
            '2026_05_31_000003_add_name_parts_to_synthetic_rating_users_table.php',
            '2026_11_16_000001_create_synthetic_username_registry.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    protected function tearDown(): void
    {
        DB::purge('rating_quality_test');
        $this->travelBack();
        parent::tearDown();
    }

    public function test_normal_list_does_not_start_an_expensive_name_analysis(): void
    {
        $rating = $this->rating($this->user('morgenlicht', 'light:morning'));
        $this->mock(UsernameQualityAnalyzer::class)->shouldNotReceive('analyze');
        $component = new NameQualityRatingsHarness;

        $this->assertSame([$rating->id], $component->query()->pluck('id')->all());
        $data = $component->qualityData([$rating]);
        $this->assertNull($data['nameQualityReport']);
        $this->assertSame([], $data['nameQualityBadges']);
    }

    public function test_core_filter_applies_before_pagination_and_keeps_shared_user_ratings(): void
    {
        $ordinary = $this->user('sternrad', 'star:wheel');
        for ($i = 0; $i < 20; $i++) {
            $this->rating($ordinary);
        }
        $first = $this->user('waldpfad82', 'path:forest');
        $second = $this->user('waldpfad_hh', 'path:forest');
        $expected = [$this->rating($first)->id, $this->rating($first)->id, $this->rating($second)->id];
        $component = new NameQualityRatingsHarness;
        $component->filterNameQuality = 'core';
        $page = $component->query()->orderBy('id')->paginate(15);

        $this->assertSame(3, $page->total());
        $this->assertSame($expected, $page->pluck('id')->all());
        $this->assertSame('core', $component->qualityData($page)['nameQualityBadges'][$first->id][0]['type']);
        $this->assertSame('Benutzernamen: Gleiche Wortbasis', $component->chips()['filterNameQuality']);
    }

    public function test_multiple_ratings_by_one_user_are_not_a_username_duplicate(): void
    {
        $user = $this->user('wellenreiter', 'wave:rider');
        $this->rating($user);
        $this->rating($user);
        $component = new NameQualityRatingsHarness;
        foreach (['duplicate', 'core'] as $filter) {
            $component->filterNameQuality = $filter;
            $this->assertSame(0, $component->query()->count());
        }
    }

    public function test_planned_select_all_honours_name_quality_search_and_replannability(): void
    {
        $first = $this->user('waldpfad82', 'path:forest');
        $second = $this->user('waldpfad_hh', 'path:forest');
        $other = $this->user('sternrad', 'star:wheel');
        $wanted = $this->rating($first, false);
        $this->rating($second, false);
        $this->rating($first, true);
        $this->rating($other, false);
        $component = new PlannedClaimRatings;
        $component->search = 'waldpfad82';
        $component->filterNameQuality = 'core';
        $component->selectAllUpcoming();

        $this->assertSame([(string) $wanted->id], $component->selectedRatingIds);
        $component->paginators['page'] = 3;
        $component->selectNameQualityFilter('duplicate');
        $this->assertSame([], $component->selectedRatingIds);
        $this->assertSame(1, $component->paginators['page']);
        $component->selectedRatingIds = [(string) $wanted->id];
        $component->removeRatingFilter('filterNameQuality');
        $this->assertSame('', $component->filterNameQuality);
        $this->assertSame([], $component->selectedRatingIds);
    }

    public function test_report_refresh_resets_filtered_selection_and_clear_resets_name_filter(): void
    {
        $component = new PlannedClaimRatings;
        $component->filterNameQuality = 'similar';
        $component->selectedRatingIds = ['123'];
        $component->paginators['page'] = 5;
        $component->nameQualityUpdated();
        $this->assertSame([], $component->selectedRatingIds);
        $this->assertSame(1, $component->paginators['page']);
        $component->clearFilters();
        $this->assertSame('', $component->filterNameQuality);
    }

    public function test_quality_permission_is_required_for_filter_and_summary(): void
    {
        $rating = $this->rating($this->user('morgenlicht', 'light:morning'));
        app(UsernameQualityReportStore::class)->report(true);
        $this->qualityAllowed = false;
        $component = new NameQualityRatingsHarness;
        $this->assertNull($component->qualityData([$rating])['nameQualityReport']);
        $this->assertSame([], $component->qualityData([$rating])['nameQualityBadges']);
        $component->filterNameQuality = 'core';
        $this->expectException(AuthorizationException::class);
        $component->query()->get();
    }

    public function test_shared_module_rejects_unauthorized_analysis(): void
    {
        $this->qualityAllowed = false;
        $this->expectException(AuthorizationException::class);
        (new RatingNameQuality)->toggleNameQuality();
    }

    public function test_open_shared_module_refreshes_after_user_edit_without_opening_closed_module(): void
    {
        $first = $this->user('waldpfad82', 'path:forest');
        $second = $this->user('waldpfad_hh', 'path:forest');
        $store = app(UsernameQualityReportStore::class);
        $component = new RatingNameQuality;
        $component->refreshAfterUserEdit();
        $this->assertNull($store->report());
        $component->toggleNameQuality();
        $this->assertSame(1, $store->report()['base_repeats']);
        $second->forceFill(['data' => ['username_generation' => ['core_key' => 'star:wheel', 'family_keys' => [], 'confidence' => 'exact']]])->save();
        $component->refreshAfterUserEdit();
        $this->assertSame(0, $store->report()['base_repeats']);
        $this->assertTrue($component->nameQualityOpen);
    }

    public function test_registry_revision_invalidates_cache_even_with_same_second_timestamp(): void
    {
        $first = $this->user('waldpfad82', 'path:forest');
        $second = $this->user('waldpfad_hh', 'path:forest');
        $store = app(UsernameQualityReportStore::class);
        $this->assertSame(1, $store->report(true)['base_repeats']);
        $timestamp = $second->updated_at;
        $second->forceFill(['data' => ['username_generation' => ['core_key' => 'star:wheel', 'family_keys' => [], 'confidence' => 'exact']]])->save();
        $this->assertTrue($timestamp->equalTo($second->updated_at));
        DB::table('synthetic_username_locks')->increment('revision');
        $this->assertNull($store->report());
        $this->assertSame(0, $store->report(true)['base_repeats']);
    }

    public function test_unrecognized_names_and_invalid_filters_are_not_silently_unfiltered(): void
    {
        $unknown = $this->user('zzqxv7620', null);
        $known = $this->user('morgenlicht', 'light:morning');
        $wanted = $this->rating($unknown);
        $this->rating($known);
        $component = new NameQualityRatingsHarness;
        $component->filterNameQuality = 'unknown';
        $this->assertSame([$wanted->id], $component->query()->pluck('id')->all());
        $component->filterNameQuality = 'not-a-filter';
        $this->assertSame(0, $component->query()->count());
    }

    private function user(string $username, ?string $core): SyntheticRatingUser
    {
        return SyntheticRatingUser::create([
            'name' => $username, 'username' => $username,
            'email' => uniqid('quality-', true).'@example.invalid',
            'data' => $core ? ['username_generation' => ['core_key' => $core, 'base' => $username, 'family_keys' => [], 'confidence' => 'exact']] : [],
        ]);
    }

    private function rating(SyntheticRatingUser $user, bool $executed = true): ClaimRating
    {
        return ClaimRating::create([
            'synthetic_rating_user_id' => $user->id,
            'status' => $executed ? ClaimRating::STATUS_PUBLISHED : ClaimRating::STATUS_SCHEDULED,
            'executed_at' => $executed ? now() : null,
            'scheduled_for' => now()->addDays(2),
            'data' => ['synthetic' => true],
        ]);
    }
}

class NameQualityRatingsHarness extends ClaimRatings
{
    public function query(): Builder
    {
        return $this->applyRatingFilters($this->ratingDetailQuery());
    }

    public function qualityData(iterable $ratings): array
    {
        return $this->nameQualityViewData($ratings);
    }

    public function chips(): array
    {
        return $this->activeRatingFilters();
    }
}
