@php
    $editSyncStates = ['Bewertung' => data_get($rating->data, 'edit_sync.status', 'unknown')];
    if ($rating->syntheticUser) {
        $editSyncStates['Benutzer'] = data_get($rating->syntheticUser->data, 'edit_sync.status', 'unknown');
    }
    $editSyncLabels = ['local' => 'Nur lokal', 'pending' => 'Übertragung ausstehend', 'synced' => 'Synchronisiert', 'failed' => 'Übertragung fehlgeschlagen', 'conflict' => 'Änderungskonflikt'];
@endphp
<div class="space-y-1">
    @foreach($editSyncStates as $subject => $editSyncState)
        <p @class(['text-[11px] leading-4', 'text-rose-700' => in_array($editSyncState, ['failed', 'conflict'], true), 'text-amber-700' => $editSyncState === 'pending', 'text-slate-500' => !in_array($editSyncState, ['failed', 'conflict', 'pending'], true)])>
            <span>{{ $subject }}:</span> {{ $editSyncLabels[$editSyncState] ?? 'Nicht geprüft' }}
        </p>
    @endforeach
</div>
