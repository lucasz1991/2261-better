<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Rating\RatingDistributionCatalog;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $ratingDefaults = [
            'daily_target' => 10,
            'type_weights' => RatingDistributionCatalog::defaultTypeWeights(),
            'subtype_weights' => RatingDistributionCatalog::defaultSubtypeWeights(),
            'hour_weights' => RatingDistributionCatalog::defaultHourWeights(),
        ];

        $ratingSettings = $this->storedSetting('rating_generation', 'settings');

        Setting::setValue('rating_generation', 'settings', [
            'daily_target' => $ratingSettings['daily_target'] ?? $ratingDefaults['daily_target'],
            'type_weights' => array_replace(
                $ratingDefaults['type_weights'],
                is_array($ratingSettings['type_weights'] ?? null) ? $ratingSettings['type_weights'] : []
            ),
            'subtype_weights' => array_replace_recursive(
                $ratingDefaults['subtype_weights'],
                is_array($ratingSettings['subtype_weights'] ?? null) ? $ratingSettings['subtype_weights'] : []
            ),
            'hour_weights' => array_replace(
                $ratingDefaults['hour_weights'],
                is_array($ratingSettings['hour_weights'] ?? null) ? $ratingSettings['hour_weights'] : []
            ),
        ]);

        Setting::setValue('openrouter', 'config', array_replace([
            'api_url' => env('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1/chat/completions'),
            'api_key' => env('OPENROUTER_API_KEY', ''),
            'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
            'referer_url' => env('APP_URL', 'http://localhost'),
        ], $this->storedSetting('openrouter', 'config')));

        Setting::setValue('database', 'config', array_replace([
            'host' => env('ANALYTICS_DB_HOST', '127.0.0.1'),
            'port' => env('ANALYTICS_DB_PORT', '3306'),
            'database' => env('ANALYTICS_DB_DATABASE', 'regulierungs-check'),
            'username' => env('ANALYTICS_DB_USERNAME', 'root'),
            'password' => env('ANALYTICS_DB_PASSWORD', ''),
        ], $this->storedSetting('database', 'config')));

        Setting::setValue('form_filling', 'config', array_replace([
            'enabled' => false,
            'mode' => 'internal_review',
            'batch_size' => 5,
            'pause_min_seconds' => 30,
            'pause_max_seconds' => 180,
            'stop_before_submit' => true,
            'use_synthetic_person_data' => true,
            'source_path' => '',
        ], $this->storedSetting('form_filling', 'config')));
    }

    private function storedSetting(string $type, string $key): array
    {
        $setting = Setting::getValueUncached($type, $key);

        return is_array($setting) ? $setting : [];
    }
}
