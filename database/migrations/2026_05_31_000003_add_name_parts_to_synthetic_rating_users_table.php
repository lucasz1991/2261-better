<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('synthetic_rating_users')) {
            return;
        }

        Schema::table('synthetic_rating_users', function (Blueprint $table): void {
            if (! Schema::hasColumn('synthetic_rating_users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }

            if (! Schema::hasColumn('synthetic_rating_users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('synthetic_rating_users', 'username')) {
                $table->string('username')->nullable()->after('last_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('synthetic_rating_users')) {
            return;
        }

        Schema::table('synthetic_rating_users', function (Blueprint $table): void {
            foreach (['username', 'last_name', 'first_name'] as $column) {
                if (Schema::hasColumn('synthetic_rating_users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
