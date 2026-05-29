<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Rating\RatingDistributionCatalog;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'type' => 'rating_generation',
                'key' => 'settings',
                'value' => [
                    'daily_target' => 10,
                    'type_weights' => RatingDistributionCatalog::defaultTypeWeights(),
                    'subtype_weights' => RatingDistributionCatalog::defaultSubtypeWeights(),
                ],
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['type' => $setting['type'], 'key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
