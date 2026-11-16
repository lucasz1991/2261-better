<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatingEditRevision extends Model
{
    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
        'base_snapshot' => 'array',
        'synced_at' => 'datetime',
    ];

    public function getStatusLabelAttribute(): string
    {
        return [
            'local' => 'Lokal gespeichert',
            'pending' => 'Übertragung ausstehend',
            'synced' => 'Synchronisiert',
            'failed' => 'Übertragung fehlgeschlagen',
            'conflict' => 'Konflikt – Prüfung erforderlich',
            'superseded' => 'Durch neuere Änderung ersetzt',
            'discarded' => 'Verworfen – lokal zurückgesetzt',
        ][$this->status] ?? $this->status;
    }
}
