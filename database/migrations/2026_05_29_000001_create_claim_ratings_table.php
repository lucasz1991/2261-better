<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('base_claim_rating_id')->nullable()->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('insurance_subtype_id')->nullable()->index();
            $table->unsignedBigInteger('insurance_type_id')->nullable()->index();
            $table->unsignedBigInteger('rating_questionnaire_versions_id')->nullable()->index();
            $table->unsignedBigInteger('insurance_id')->nullable()->index();
            $table->json('answers')->nullable();
            $table->string('status')->default('pending')->index();
            $table->json('attachments')->nullable();
            $table->decimal('rating_score', 3, 2)->nullable();
            $table->json('tag_ids')->nullable();
            $table->text('moderator_comment')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->json('admin_review')->nullable();
            $table->json('data')->nullable();
            $table->uuid('verification_hash')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_ratings');
    }
};
