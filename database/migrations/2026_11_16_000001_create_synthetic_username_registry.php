<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('synthetic_username_locks')) {
            Schema::create('synthetic_username_locks', function (Blueprint $table): void {
                $table->string('namespace', 64)->primary();
                $table->unsignedBigInteger('revision')->default(0);
                $table->timestamps();
            });
        }

        DB::table('synthetic_username_locks')->insertOrIgnore([
            'namespace' => 'synthetic_rating_users',
            'revision' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! Schema::hasTable('synthetic_username_registry')) {
            Schema::create('synthetic_username_registry', function (Blueprint $table): void {
                $table->id();
                $table->string('key_hash', 64)->unique();
                $table->string('username_key');
                $table->string('username');
                $table->unsignedBigInteger('synthetic_rating_user_id')->index();
                $table->string('core_key', 191)->nullable()->index();
                $table->json('family_keys')->nullable();
                $table->json('metadata')->nullable();
                $table->string('source', 24)->default('legacy');
                $table->timestamp('assigned_at')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('synthetic_username_conflicts')) {
            Schema::create('synthetic_username_conflicts', function (Blueprint $table): void {
                $table->id();
                $table->string('key_hash', 64)->index();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('conflicting_user_id');
                $table->timestamp('detected_at');
                $table->unique(['key_hash', 'conflicting_user_id'], 'synthetic_username_conflict_unique');
            });
        }

        if (! Schema::hasTable('synthetic_username_allocations')) {
            Schema::create('synthetic_username_allocations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('registry_id')->index();
                $table->unsignedBigInteger('synthetic_rating_user_id')->index();
                $table->string('username');
                $table->json('metadata')->nullable();
                $table->timestamp('assigned_at')->index();
            });
        }

        if (! Schema::hasColumn('synthetic_rating_users', 'username')) {
            return;
        }

        // Reserve existing values without renaming or deleting any identity.
        // Conflicts remain visible for an explicit, audited correction.
        DB::table('synthetic_rating_users')->whereNotNull('username')->orderBy('id')->chunkById(250, function ($users): void {
            foreach ($users as $user) {
                $key = Str::lower(Str::ascii(trim((string) $user->username)));

                if ($key === '') {
                    continue;
                }

                $hash = hash('sha256', $key);
                $existing = DB::table('synthetic_username_registry')->where('key_hash', $hash)->first();

                if ($existing && (int) $existing->synthetic_rating_user_id !== (int) $user->id) {
                    DB::table('synthetic_username_conflicts')->insertOrIgnore([
                        'key_hash' => $hash,
                        'owner_user_id' => $existing->synthetic_rating_user_id,
                        'conflicting_user_id' => $user->id,
                        'detected_at' => now(),
                    ]);

                    continue;
                }

                $data = json_decode((string) ($user->data ?? ''), true);
                $metadata = is_array($data) && is_array($data['username_generation'] ?? null)
                    ? $data['username_generation']
                    : ['confidence' => 'unknown', 'source' => 'legacy'];

                DB::table('synthetic_username_registry')->insertOrIgnore([
                    'key_hash' => $hash,
                    'username_key' => $key,
                    'username' => $user->username,
                    'synthetic_rating_user_id' => $user->id,
                    'core_key' => $metadata['core_key'] ?? null,
                    'family_keys' => json_encode($metadata['family_keys'] ?? []),
                    'metadata' => json_encode($metadata),
                    'source' => 'legacy',
                    'assigned_at' => $user->created_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $registry = DB::table('synthetic_username_registry')->where('key_hash', $hash)->first();

                if (! DB::table('synthetic_username_allocations')->where('registry_id', $registry->id)->exists()) {
                    DB::table('synthetic_username_allocations')->insert([
                        'registry_id' => $registry->id,
                        'synthetic_rating_user_id' => $user->id,
                        'username' => $user->username,
                        'metadata' => json_encode($metadata),
                        'assigned_at' => $user->created_at ?? now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synthetic_username_allocations');
        Schema::dropIfExists('synthetic_username_conflicts');
        Schema::dropIfExists('synthetic_username_registry');
        Schema::dropIfExists('synthetic_username_locks');
    }
};
