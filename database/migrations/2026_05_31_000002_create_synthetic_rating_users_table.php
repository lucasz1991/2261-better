<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('synthetic_rating_users')) {
            Schema::create('synthetic_rating_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('base_user_id')->nullable()->unique();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('email_domain')->nullable()->index();
                $table->string('role')->default('guest')->index();
                $table->boolean('status')->default(false)->index();
                $table->timestamp('email_verified_at')->nullable();
                $table->json('data')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('claim_ratings')) {
            return;
        }

        Schema::table('claim_ratings', function (Blueprint $table) {
            if (! Schema::hasColumn('claim_ratings', 'synthetic_rating_user_id')) {
                $table
                    ->foreignId('synthetic_rating_user_id')
                    ->nullable()
                    ->after('base_user_id')
                    ->constrained('synthetic_rating_users')
                    ->nullOnDelete();
            }
        });

        $this->backfillExistingSyntheticUsers();
    }

    public function down(): void
    {
        if (Schema::hasTable('claim_ratings') && Schema::hasColumn('claim_ratings', 'synthetic_rating_user_id')) {
            Schema::table('claim_ratings', function (Blueprint $table) {
                $table->dropConstrainedForeignId('synthetic_rating_user_id');
            });
        }

        Schema::dropIfExists('synthetic_rating_users');
    }

    private function backfillExistingSyntheticUsers(): void
    {
        if (! Schema::hasTable('claim_ratings') || ! Schema::hasColumn('claim_ratings', 'synthetic_rating_user_id')) {
            return;
        }

        DB::table('claim_ratings')
            ->whereNull('synthetic_rating_user_id')
            ->where(function ($query): void {
                $query
                    ->whereNotNull('base_user_id')
                    ->orWhere('data', 'like', '%synthetic_user_profile%');
            })
            ->orderBy('id')
            ->get(['id', 'base_user_id', 'data'])
            ->each(function (object $rating): void {
                $data = json_decode((string) ($rating->data ?? ''), true);
                $data = is_array($data) ? $data : [];
                $profile = data_get($data, 'planning.synthetic_user_profile', []);
                $profile = is_array($profile) ? $profile : [];
                $email = (string) ($profile['email'] ?? '');

                if (! str_starts_with($email, 'synthetic-2261-') || ! str_ends_with($email, '@example.invalid')) {
                    $email = 'synthetic-2261-rating-' . $rating->id . '@example.invalid';
                }

                $existing = DB::table('synthetic_rating_users')
                    ->where('email', $email)
                    ->first(['id']);

                $payload = [
                    'base_user_id' => $rating->base_user_id,
                    'name' => (string) ($profile['name'] ?? 'Interner Testnutzer 2261 #' . $rating->id),
                    'email' => $email,
                    'email_domain' => 'example.invalid',
                    'role' => (string) ($profile['role'] ?? 'guest'),
                    'status' => (bool) ($profile['status'] ?? false),
                    'email_verified_at' => null,
                    'data' => json_encode([
                        'synthetic' => true,
                        'do_not_publish' => true,
                        'source_app' => '2261-better',
                        'backfilled_from_claim_rating_id' => $rating->id,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('synthetic_rating_users')
                        ->where('id', $existing->id)
                        ->update($payload);

                    $syntheticUserId = (int) $existing->id;
                } else {
                    $payload['created_at'] = now();
                    $syntheticUserId = (int) DB::table('synthetic_rating_users')->insertGetId($payload);
                }

                DB::table('claim_ratings')
                    ->where('id', $rating->id)
                    ->update(['synthetic_rating_user_id' => $syntheticUserId]);
            });
    }
};
