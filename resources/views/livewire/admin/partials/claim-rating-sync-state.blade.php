@php
    $editSyncStates = ['Bewertung' => data_get($rating->data, 'edit_sync.status', 'unknown')];
    if ($rating->syntheticUser) {
        $editSyncStates['Benutzer'] = data_get($rating->syntheticUser->data, 'edit_sync.status', 'unknown');
    }
    $editSyncLabels = ['local' => 'Nur lokal', 'pending' => 'Übertragung ausstehend', 'synced' => 'Änderung synchronisiert', 'failed' => 'Übertragung fehlgeschlagen', 'conflict' => 'Änderungskonflikt'];
@endphp
<div class="flex flex-col items-start gap-1">
    @foreach($editSyncStates as $subject => $editSyncState)
        <span @class(['inline-flex rounded-md border px-2 py-1 text-xs font-medium',
            'border-emerald-200 bg-emerald-50 text-emerald-800' => $editSyncState === 'synced',
            'border-rose-200 bg-rose-50 text-rose-800' => in_array($editSyncState, ['failed', 'conflict'], true),
            'border-amber-200 bg-amber-50 text-amber-800' => $editSyncState === 'pending',
            'border-slate-200 bg-slate-50 text-slate-600' => !in_array($editSyncState, ['synced', 'failed', 'conflict', 'pending'], true),
        ])>{{ $subject }}: {{ $editSyncLabels[$editSyncState] ?? 'Übertragung nicht geprüft' }}</span>
    @endforeach
</div>
