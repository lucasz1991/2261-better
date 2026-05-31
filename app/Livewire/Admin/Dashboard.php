<?php

namespace App\Livewire\Admin;

use App\Models\ClaimRating;
use App\Models\SyntheticRatingUser;
use App\Services\BaseClaimRatingPublisher;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class Dashboard extends Component
{
    public int $totalRatings = 0;
    public int $linkedBaseRatings = 0;
    public int $linkedBaseUsers = 0;
    public int $publicRatings = 0;
    public int $pendingRatings = 0;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function retractAllSyntheticBaseData(): void
    {
        try {
            $report = app(BaseClaimRatingPublisher::class)->retractAll();
            $this->loadStats();

            if (! empty($report['failed'])) {
                session()->flash(
                    'error',
                    sprintf(
                        '%d Datensaetze wurden zurueckgerufen, %d sind fehlgeschlagen.',
                        $report['ratings_retracted'] ?? 0,
                        count($report['failed'])
                    )
                );

                return;
            }

            session()->flash(
                'success',
                sprintf(
                    '%d Bewertungen und %d verwaiste Testnutzer wurden aus der Base-Datenbank entfernt.',
                    $report['ratings_retracted'] ?? 0,
                    $report['orphan_users_deleted'] ?? 0
                )
            );
        } catch (\Throwable $exception) {
            session()->flash('error', 'Rueckruf fehlgeschlagen: '.$exception->getMessage());
        }
    }

    private function loadStats(): void
    {
        if (! Schema::hasTable('claim_ratings')) {
            return;
        }

        $this->totalRatings = ClaimRating::count();
        $this->linkedBaseRatings = ClaimRating::whereNotNull('base_claim_rating_id')->count();
        $this->linkedBaseUsers = Schema::hasTable('synthetic_rating_users')
            ? SyntheticRatingUser::whereNotNull('base_user_id')->count()
            : 0;
        $this->publicRatings = ClaimRating::where('is_public', true)->count();
        $this->pendingRatings = ClaimRating::where('status', ClaimRating::STATUS_PENDING)->count();
    }

    public function render()
    {
        return view('livewire.admin.dashboard')->layout('layouts.admin');
    }
}
