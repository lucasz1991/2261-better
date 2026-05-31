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
            if (! Schema::hasColumn('claim_ratings', 'scheduled_for')) {
                $table->dateTime('scheduled_for')->nullable()->index();
            }

            if (! Schema::hasColumn('claim_ratings', 'execution_started_at')) {
                $table->dateTime('execution_started_at')->nullable();
            }

            if (! Schema::hasColumn('claim_ratings', 'executed_at')) {
                $table->dateTime('executed_at')->nullable()->index();
            }

            if (! Schema::hasColumn('claim_ratings', 'execution_attempts')) {
                $table->unsignedSmallInteger('execution_attempts')->default(0);
            }

            if (! Schema::hasColumn('claim_ratings', 'last_execution_error')) {
                $table->text('last_execution_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('claim_ratings')) {
            return;
        }

        Schema::table('claim_ratings', function (Blueprint $table) {
            $columns = [
                'scheduled_for',
                'execution_started_at',
                'executed_at',
                'execution_attempts',
                'last_execution_error',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('claim_ratings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
