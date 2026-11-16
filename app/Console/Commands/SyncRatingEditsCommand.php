<?php

namespace App\Console\Commands;

use App\Models\RatingEditRevision;
use App\Services\RatingEditSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncRatingEditsCommand extends Command
{
    protected $signature = 'ratings:sync-edits {--limit=50 : Maximum revisions per run}';

    protected $description = 'Retry pending 2261 rating/profile changes without republishing ratings';

    public function handle(RatingEditSynchronizer $synchronizer): int
    {
        if (! Schema::hasTable('rating_edit_revisions')) {
            $this->warn('Bearbeitungsmigration fehlt.');

            return self::FAILURE;
        }
        $limit = max(1, min(200, (int) $this->option('limit')));
        $entries = RatingEditRevision::query()->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 10)->where(function ($query): void {
                $query->where('status', 'pending')->orWhere('updated_at', '<=', now()->subMinutes(5));
            })->oldest('id')->limit($limit)->get();
        $synced = 0;
        foreach ($entries as $entry) {
            $synced += $synchronizer->synchronize($entry)->status === 'synced' ? 1 : 0;
        }
        $this->info("{$synced} von {$entries->count()} Änderungen synchronisiert.");

        return self::SUCCESS;
    }
}
