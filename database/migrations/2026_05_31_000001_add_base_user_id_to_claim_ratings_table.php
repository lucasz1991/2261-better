<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('claim_ratings')) {
            return;
        }

        Schema::table('claim_ratings', function (Blueprint $table) {
            if (! Schema::hasColumn('claim_ratings', 'base_user_id')) {
                $table->unsignedBigInteger('base_user_id')->nullable()->index()->after('base_claim_rating_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('claim_ratings') || ! Schema::hasColumn('claim_ratings', 'base_user_id')) {
            return;
        }

        Schema::table('claim_ratings', function (Blueprint $table) {
            $table->dropColumn('base_user_id');
        });
    }
};
