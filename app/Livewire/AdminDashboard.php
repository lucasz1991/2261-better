<?php

namespace App\Livewire;

use App\Models\ClaimRating;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AdminDashboard extends Component
{
    public int $totalRatings = 0;
    public int $linkedBaseRatings = 0;
    public int $publicRatings = 0;
    public int $pendingRatings = 0;

    public function mount(): void
    {
        if (! Schema::hasTable('claim_ratings')) {
            return;
        }

        $this->totalRatings = ClaimRating::count();
        $this->linkedBaseRatings = ClaimRating::whereNotNull('base_claim_rating_id')->count();
        $this->publicRatings = ClaimRating::where('is_public', true)->count();
        $this->pendingRatings = ClaimRating::where('status', ClaimRating::STATUS_PENDING)->count();
    }

    public function render()
    {
        return view('livewire.admin.dashboard')->layout('layouts.master');
    }
}
