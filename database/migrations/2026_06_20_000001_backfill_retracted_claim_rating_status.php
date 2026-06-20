<?php

use App\Models\ClaimRating;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('claim_ratings')
            || ! Schema::hasColumn('claim_ratings', 'status')
            || ! Schema::hasColumn('claim_ratings', 'data')) {
            return;
        }

        DB::table('claim_ratings')
            ->select(['id', 'data'])
            ->orderBy('id')
            ->chunkById(100, function ($ratings): void {
                foreach ($ratings as $rating) {
                    $data = $this->decodeJson($rating->data ?? null);

                    if ((bool) data_get($data, 'execution_control.manual_only_after_retract', false)) {
                        DB::table('claim_ratings')
                            ->where('id', $rating->id)
                            ->update(['status' => ClaimRating::STATUS_RETRACTED]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('claim_ratings') || ! Schema::hasColumn('claim_ratings', 'status')) {
            return;
        }

        DB::table('claim_ratings')
            ->where('status', ClaimRating::STATUS_RETRACTED)
            ->update(['status' => ClaimRating::STATUS_SCHEDULED]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
