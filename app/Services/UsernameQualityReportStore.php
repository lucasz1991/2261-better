<?php

namespace App\Services;

use App\Models\SyntheticRatingUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsernameQualityReportStore
{
    public static function options(): array
    {
        return [
            'duplicate' => 'Identische Benutzernamen',
            'core' => 'Gleiche Wortbasis',
            'similar' => 'Ähnliche Schreibweise',
            'family' => 'Häufige Wortfamilie',
            'unknown' => 'Wortbasis nicht erkannt',
        ];
    }

    public function report(bool $analyze = false): ?array
    {
        $key = $this->cacheKey();
        // Normal list views may reuse a report but never start the scan.
        $report = $analyze
            ? Cache::remember($key, 300, fn () => app(UsernameQualityAnalyzer::class)->analyze())
            : Cache::get($key);

        return is_array($report) ? $report : null;
    }

    public function refresh(): array
    {
        Cache::forget($this->cacheKey());

        return $this->report(true);
    }

    private function cacheKey(): string
    {
        $connection = (new SyntheticRatingUser)->getConnection();
        $revision = Schema::hasTable('synthetic_username_locks')
            ? DB::table('synthetic_username_locks')->where('namespace', 'synthetic_rating_users')->value('revision')
            : null;
        $stamp = SyntheticRatingUser::query()->toBase()
            ->selectRaw('COUNT(*) as user_count, MAX(id) as last_id, MAX(updated_at) as last_update')->first();

        return '2261.rating-username-quality.'.auth()->id().'.'.sha1(json_encode([
            $connection->getName(), $connection->getDatabaseName(), $revision,
            $stamp->user_count, $stamp->last_id, $stamp->last_update,
        ]));
    }
}
