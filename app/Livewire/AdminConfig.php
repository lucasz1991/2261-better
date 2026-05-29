<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Support\Rating\RatingDistributionCatalog;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AdminConfig extends Component
{
    public int $dailyTarget = 10;

    /** @var array<int|string, float|int|string|null> */
    public array $typeWeights = [];

    /** @var array<int|string, array<int|string, float|int|string|null>> */
    public array $subtypeWeights = [];

    public function mount(): void
    {
        Gate::authorize('settings.manage');
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $settings = $this->storedSettings();

        $this->dailyTarget = (int) ($settings['daily_target'] ?? 10);
        $this->typeWeights = $this->mergeTypeWeights($settings['type_weights'] ?? []);
        $this->subtypeWeights = $this->mergeSubtypeWeights($settings['subtype_weights'] ?? []);
    }

    public function save(): void
    {
        $this->validate([
            'dailyTarget' => ['required', 'integer', 'min:0', 'max:500'],
            'typeWeights.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'subtypeWeights.*.*' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        Setting::setValue('rating_generation', 'settings', [
            'daily_target' => $this->dailyTarget,
            'type_weights' => $this->numericWeights($this->typeWeights),
            'subtype_weights' => $this->nestedNumericWeights($this->subtypeWeights),
        ]);

        session()->flash('success', 'Bewertungs-Einstellungen wurden gespeichert.');
    }

    public function resetDistribution(): void
    {
        $this->typeWeights = RatingDistributionCatalog::defaultTypeWeights();
        $this->subtypeWeights = RatingDistributionCatalog::defaultSubtypeWeights();
    }

    public function getTypeWeightTotalProperty(): float
    {
        return array_sum($this->numericWeights($this->typeWeights));
    }

    public function getSubtypeWeightTotalProperty(): float
    {
        $total = 0.0;

        foreach ($this->nestedNumericWeights($this->subtypeWeights) as $weights) {
            $total += array_sum($weights);
        }

        return $total;
    }

    public function render()
    {
        return view('livewire.admin-config', [
            'catalog' => RatingDistributionCatalog::types(),
        ])->layout('layouts.master');
    }

    private function storedSettings(): array
    {
        $settings = Setting::getValueUncached('rating_generation', 'settings');

        if (is_string($settings)) {
            $decoded = json_decode($settings, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param array<int|string, mixed> $storedWeights
     * @return array<int, float>
     */
    private function mergeTypeWeights(array $storedWeights): array
    {
        $weights = RatingDistributionCatalog::defaultTypeWeights();

        foreach ($weights as $typeId => $default) {
            $weights[$typeId] = $this->toWeight($storedWeights[$typeId] ?? $storedWeights[(string) $typeId] ?? $default);
        }

        return $weights;
    }

    /**
     * @param array<int|string, mixed> $storedWeights
     * @return array<int, array<int, float>>
     */
    private function mergeSubtypeWeights(array $storedWeights): array
    {
        $weights = RatingDistributionCatalog::defaultSubtypeWeights();

        foreach ($weights as $typeId => $subtypes) {
            $typeStoredWeights = $storedWeights[$typeId] ?? $storedWeights[(string) $typeId] ?? [];
            $typeStoredWeights = is_array($typeStoredWeights) ? $typeStoredWeights : [];

            foreach ($subtypes as $subtypeId => $default) {
                $weights[$typeId][$subtypeId] = $this->toWeight(
                    $typeStoredWeights[$subtypeId]
                    ?? $typeStoredWeights[(string) $subtypeId]
                    ?? $default
                );
            }
        }

        return $weights;
    }

    /**
     * @param array<int|string, mixed> $weights
     * @return array<int, float>
     */
    private function numericWeights(array $weights): array
    {
        $numeric = [];

        foreach ($weights as $id => $weight) {
            $numeric[(int) $id] = $this->toWeight($weight);
        }

        return $numeric;
    }

    /**
     * @param array<int|string, mixed> $weights
     * @return array<int, array<int, float>>
     */
    private function nestedNumericWeights(array $weights): array
    {
        $numeric = [];

        foreach ($weights as $typeId => $subtypes) {
            if (! is_array($subtypes)) {
                continue;
            }

            foreach ($subtypes as $subtypeId => $weight) {
                $numeric[(int) $typeId][(int) $subtypeId] = $this->toWeight($weight);
            }
        }

        return $numeric;
    }

    private function toWeight(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return max(0.0, (float) str_replace(',', '.', (string) $value));
    }
}
