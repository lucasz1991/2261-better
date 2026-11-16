<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rating_edit_revisions')) {
            return;
        }

        Schema::create('rating_edit_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->unsignedBigInteger('claim_rating_id')->index();
            $table->unsignedBigInteger('synthetic_rating_user_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedInteger('rating_revision');
            $table->unsignedInteger('user_revision');
            $table->string('reason', 1000);
            $table->json('changes');
            $table->json('base_snapshot')->nullable();
            $table->string('status', 24)->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_error', 1000)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_edit_revisions');
    }
};
