<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Benutzer &amp; Namensqualität</h1>
            <p class="mt-1 text-sm text-slate-600">Benutzer finden, wiederkehrende Namen prüfen und Änderungen über ihre Bewertung bearbeiten.</p>
        </div>
        <a href="{{ route('admin.reviews') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Zu den Bewertungen</a>
    </div>

    @if(session('success'))<div role="status" class="rounded-md bg-emerald-50 p-3 text-emerald-900">{{ session('success') }}</div>@endif
    @if(session('error'))<div role="alert" class="rounded-md bg-rose-50 p-3 text-rose-900">{{ session('error') }}</div>@endif

    @if($report)
        <section aria-label="Namensqualität" class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['Benutzer', $report['total'], 'Aktive lokale Datensätze'],
                    ['Exakte Konflikte', count($report['duplicate_ids']), $report['duplicate_groups'].' betroffene Gruppen'],
                    ['Wiederholte Basen', $report['base_repeats'], 'Weitere Namen mit bekannter gleicher Basis'],
                    ['Katalog', $catalog['total_bases'] ?? 0, 'Verfügbare kanonische Wortbasen'],
                ] as [$label, $value, $hint])
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-sm text-slate-600">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900">{{ number_format($value, 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
                    </div>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
                <p>{{ $report['recognized'] }} von {{ $report['total'] }} Basen zugeordnet · {{ $report['unknown'] }} unbekannt · Stand {{ $report['generated_at'] }}.</p>
                <button type="button" wire:click="refreshQuality" wire:loading.attr="disabled" class="font-medium text-blue-700 underline underline-offset-2">Analyse aktualisieren</button>
            </div>
            <p class="text-xs text-slate-500">Lokaler Bestand. Ähnliche Schreibweisen sind Hinweise aus den jüngsten 1.000 Benutzern; Wortfamilien beziehen sich auf die jüngsten 200. Die öffentliche Base wurde hier nicht abgeglichen.</p>
        </section>

        <details wire:ignore.self class="rounded-lg border border-blue-200 bg-blue-50/40 p-4">
            <summary class="cursor-pointer font-semibold text-blue-950">Generator-Vorschau: 1.000 Namen ohne Benutzeranlage</summary>
            <div class="mt-4 space-y-4" @if($previewRunning) wire:poll.1500ms="advancePreview" @endif>
                <p class="text-sm text-slate-600">Alter und neuer Generator erhalten dieselben Personas und denselben lokalen Ausgangsbestand. Der neue Generator berücksichtigt zusätzlich Wortbasen und Familien. Die Vorschau reserviert keine Namen und verändert keine Bewertungen. Eine erneute Prüfung erfolgt erst bei einer tatsächlichen Vergabe.</p>
                <div class="flex flex-wrap gap-3">
                    <button type="button" wire:click="startPreview" wire:loading.attr="disabled" @disabled($previewRunning) class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50">Vorschau starten</button>
                    @if($previewRunning)<button type="button" wire:click="cancelPreview" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm">Anhalten</button>@endif
                </div>
                @error('preview')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                @if($preview)
                    <div role="status" aria-live="polite" class="space-y-2">
                        <p class="text-sm font-medium text-slate-900">{{ $previewRunning ? 'Vorschau läuft' : 'Vorschau angehalten oder abgeschlossen' }}: {{ $preview['generated'] ?? 0 }} / 1.000 erzeugt</p>
                        <progress class="h-2 w-full accent-blue-700" max="1000" value="{{ $preview['generated'] ?? 0 }}" aria-label="Fortschritt der Vorschau"></progress>
                        @if(isset($preview['distinct_cores']))
                            <p class="text-sm text-slate-700">{{ $preview['distinct_cores'] }} unterschiedliche Basen · {{ $preview['base_repeats'] }} Basiswiederholungen · {{ $preview['failed'] }} fehlgeschlagene Vergaben · {{ $preview['elapsed'] }} s Rechenzeit.</p>
                            <p class="text-xs text-slate-500">Bestandsaufnahme {{ $preview['started_at'] }} · {{ $preview['baseline_count'] }} lokale Registereinträge berücksichtigt.</p>
                            <div class="overflow-x-auto rounded border border-blue-100 bg-white">
                                <table class="w-full text-left text-sm"><thead class="bg-blue-50"><tr><th class="p-2">Vergleich</th><th class="p-2">Erzeugt</th><th class="p-2">Unterschiedliche Basen</th><th class="p-2">Basiswiederholungen</th></tr></thead><tbody>
                                    <tr><td class="p-2">Bisheriger Generator</td><td class="p-2">{{ $preview['legacy_generated'] ?? 0 }}</td><td class="p-2">{{ $preview['legacy_distinct_cores'] ?? 0 }}</td><td class="p-2">{{ $preview['legacy_base_repeats'] ?? 0 }}</td></tr>
                                    <tr class="font-medium text-emerald-800"><td class="p-2">Neuer Generator</td><td class="p-2">{{ $preview['generated'] }}</td><td class="p-2">{{ $preview['distinct_cores'] }}</td><td class="p-2">{{ $preview['base_repeats'] }}</td></tr>
                                </tbody></table>
                            </div>
                            <p class="text-xs text-slate-500">Alte Basen erkannt: {{ $preview['legacy_recognized'] ?? 0 }} / {{ $preview['legacy_generated'] ?? 0 }}. Alte Fehlversuche: {{ $preview['legacy_failed'] ?? 0 }}. Zufallsstichprobe, keine garantierte Produktionsverteilung.</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach($preview['samples'] ?? [] as $sample)<span class="rounded border border-blue-100 bg-white px-2 py-1 text-xs text-slate-700">{{ $sample }}</span>@endforeach
                    </div>
                @endif
            </div>
        </details>
    @endif

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div class="xl:col-span-2">
            <label for="user-search" class="mb-1 block text-sm font-medium text-slate-700">Benutzer suchen</label>
            <input id="user-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Name, Benutzername, Vorname, Nachname, E-Mail oder ID" class="w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="user-quality" class="mb-1 block text-sm font-medium text-slate-700">Namensqualität</label>
            <select id="user-quality" wire:model.live="quality" class="w-full rounded-md border-slate-300 text-sm" @disabled(!$report)>
                <option value="">Alle Benutzer</option><option value="duplicate">Exakte Konflikte</option><option value="core">Gleiche Wortbasis</option><option value="similar">Ähnliche Schreibweise</option><option value="family">Häufige Wortfamilie</option>
            </select>
        </div>
        <div>
            <label for="user-linked" class="mb-1 block text-sm font-medium text-slate-700">Base-Verknüpfung</label>
            <select id="user-linked" wire:model.live="linked" class="w-full rounded-md border-slate-300 text-sm"><option value="">Alle</option><option value="yes">Verknüpft</option><option value="no">Nicht verknüpft</option></select>
        </div>
        <div>
            <label for="user-sort" class="mb-1 block text-sm font-medium text-slate-700">Sortierung</label>
            <select id="user-sort" wire:model.live="sort" class="w-full rounded-md border-slate-300 text-sm"><option value="id">Neueste zuerst</option><option value="updated_at">Zuletzt geändert</option><option value="name">Anzeigename A–Z</option><option value="username">Benutzername A–Z</option></select>
        </div>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
        <p><span class="font-semibold text-slate-900">{{ $users->total() }}</span> Treffer</p>
        <div class="flex items-center gap-2"><label for="user-sync">Benutzer-Synchronisierung</label><select id="user-sync" wire:model.live="sync" class="rounded-md border-slate-300 text-sm"><option value="">Alle</option><option value="local">Lokal gespeichert</option><option value="pending">Ausstehend</option><option value="synced">Synchronisiert</option><option value="failed">Übertragung fehlgeschlagen</option><option value="conflict">Konflikt</option></select></div>
        <div class="flex items-center gap-3"><label for="user-page-size">Pro Seite</label><select id="user-page-size" wire:model.live="perPage" class="rounded-md border-slate-300 text-sm"><option>25</option><option>50</option><option>100</option></select><button type="button" wire:click="clearFilters" class="text-blue-700 underline underline-offset-2">Filter zurücksetzen</button></div>
    </div>
    <div class="overflow-x-auto rounded-lg border border-slate-200" wire:loading.class="opacity-60">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Benutzer</th><th class="px-4 py-3">Person</th><th class="px-4 py-3">Bewertungen</th><th class="px-4 py-3">Namensprüfung</th><th class="px-4 py-3">Aktion</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $user)
                    @php
                        $info = $report['rows'][$user->id] ?? [];
                        $relatedRating = $user->claimRatings->first(fn ($rating) => (bool) data_get($rating->data, 'synthetic'));
                    @endphp
                    <tr wire:key="synthetic-user-{{ $user->id }}">
                        <td class="px-4 py-3"><p class="font-semibold text-slate-900">{{ $user->name }}</p><p class="text-blue-700">{{ '@'.$user->username }}</p><p class="mt-1 text-xs text-slate-500">#{{ $user->id }} @if($user->base_user_id) · Base #{{ $user->base_user_id }} @endif</p><p class="mt-1 text-xs font-medium text-slate-600">{{ ['local'=>'Lokal gespeichert','pending'=>'Übertragung ausstehend','synced'=>'Synchronisiert','failed'=>'Übertragung fehlgeschlagen','conflict'=>'Änderungskonflikt'][data_get($user->data, 'edit_sync.status', '')] ?? 'Noch kein Änderungsabgleich' }}</p></td>
                        <td class="px-4 py-3"><p>{{ trim($user->first_name.' '.$user->last_name) ?: '—' }}</p><p class="text-xs text-slate-500">{{ $user->email }}</p></td>
                        <td class="px-4 py-3"><span class="font-medium">{{ $user->claim_ratings_count }}</span><p class="text-xs text-slate-500">Änderungen am Benutzer gelten für alle zugehörigen Bewertungen.</p></td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            @if(in_array($user->id, $report['duplicate_ids'] ?? [], true))<p class="font-semibold text-rose-700">Exakte Kennung mehrfach vergeben</p>@endif
                            @if(in_array($user->id, $report['core_ids'] ?? [], true))<p class="font-medium text-amber-700">Wiederholte Wortbasis</p>@endif
                            @if(in_array($user->id, $report['similar_ids'] ?? [], true))<p class="text-amber-700">Ähnliche Schreibweise zu Benutzer #{{ $report['similar'][$user->id] }}</p>@endif
                            @if($info['core_key'] ?? null)<p>Basis: {{ $info['core_key'] }}</p><p>{{ in_array($info['confidence'] ?? '', ['exact', 'certain'], true) ? 'Sicher zugeordnet' : 'Heuristisch zugeordnet' }}</p>@else<p>Basis nicht sicher zugeordnet</p>@endif
                        </td>
                        <td class="px-4 py-3">@if($relatedRating)<button type="button" wire:click="openRatingModal({{ $relatedRating->id }})" class="rounded-md border border-slate-300 px-3 py-2 font-medium text-blue-700 hover:bg-blue-50">Benutzer &amp; Bewertung öffnen</button>@else<span class="text-xs text-slate-500">Keine zugehörige Bewertung vorhanden</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-6 py-10 text-center text-slate-500">Keine Benutzer für diese Auswahl gefunden. Ändern Sie die Suche oder setzen Sie die Filter zurück.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
    @include('livewire.admin.partials.claim-rating-detail-modal')
</div>
