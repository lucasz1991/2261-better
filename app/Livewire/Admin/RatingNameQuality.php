<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\PreviewsRatingUsernames;
use App\Services\UsernameQualityReportStore;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Component;

class RatingNameQuality extends Component
{
    use PreviewsRatingUsernames;

    #[Modelable]
    public string $filterNameQuality = '';

    #[Locked]
    public bool $nameQualityOpen = false;

    public function mount(): void
    {
        Gate::authorize('synthetic_users.quality');
    }

    public function toggleNameQuality(): void
    {
        Gate::authorize('synthetic_users.quality');
        $this->nameQualityOpen = ! $this->nameQualityOpen;
        if ($this->nameQualityOpen) {
            app(UsernameQualityReportStore::class)->report(true);
            $this->dispatch('rating-name-quality-updated');
        } elseif ($this->previewRunning) {
            $this->cancelPreview();
        }
    }

    public function refreshQuality(): void
    {
        Gate::authorize('synthetic_users.quality');
        app(UsernameQualityReportStore::class)->refresh();
        $this->nameQualityOpen = true;
        $this->dispatch('rating-name-quality-updated');
    }

    public function updatedFilterNameQuality(): void
    {
        Gate::authorize('synthetic_users.quality');
        if ($this->filterNameQuality !== '') {
            abort_unless(array_key_exists($this->filterNameQuality, UsernameQualityReportStore::options()), 422);
            app(UsernameQualityReportStore::class)->report(true);
        }
        // Modelable synchronizes the value; the event explicitly resets the
        // parent pagination/selection even when no parent update hook runs.
        $this->dispatch('rating-name-quality-filter-changed', filter: $this->filterNameQuality);
    }

    #[On('rating-user-edited')]
    public function refreshAfterUserEdit(): void
    {
        Gate::authorize('synthetic_users.quality');
        if ($this->nameQualityOpen || $this->filterNameQuality !== '') {
            app(UsernameQualityReportStore::class)->refresh();
            $this->dispatch('rating-name-quality-updated');
        }
    }

    public function render()
    {
        Gate::authorize('synthetic_users.quality');

        return view('livewire.admin.rating-name-quality', [
            'nameQualityOptions' => UsernameQualityReportStore::options(),
            'nameQualityReport' => app(UsernameQualityReportStore::class)->report($this->nameQualityOpen || $this->filterNameQuality !== ''),
        ]);
    }
}
