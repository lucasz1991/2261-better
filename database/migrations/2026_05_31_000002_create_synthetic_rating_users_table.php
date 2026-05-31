<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
};
