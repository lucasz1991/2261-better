<section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Bewertungen suchen und filtern">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <label class="block text-sm font-medium text-slate-700 md:col-span-2">
            Suche nach Person oder Bewertung
            <input type="search" wire:model.live.debounce.300ms="search" maxlength="200"
                placeholder="Name, @Benutzername, E-Mail, Text oder ID"
                class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
        </label>
        <label class="block text-sm font-medium text-slate-700">
            Bewertungsstatus
            <select wire:model.live="status" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Alle Status</option>
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if(isset($filters))
            <label class="block text-sm font-medium text-slate-700">
                Ausführung
                <select wire:model.live="executionFilter" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach($filters as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @else
            <label class="block text-sm font-medium text-slate-700">
                Einträge pro Seite
                <select wire:model.live="perPage" class="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
            </label>
        @endif
    </div>

    <details class="mt-4 rounded-lg border border-slate-200" wire:key="advanced-rating-filters">
        <summary class="cursor-pointer px-3 py-2 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">Weitere Filter: Zeitraum, Versicherung, Benutzer und Übertragung</summary>
        <div class="grid gap-3 border-t border-slate-100 p-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="text-xs font-medium text-slate-600">Datumsart
                <select wire:model.live="filterDateField" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="created_at">Erstellt am</option><option value="scheduled_for">Geplant am</option>
                    <option value="executed_at">Ausgeführt am</option><option value="updated_at">Geändert am</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Von (Europe/Berlin)
                <input type="date" wire:model.live="filterDateFrom" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </label>
            <label class="text-xs font-medium text-slate-600">Bis einschließlich
                <input type="date" wire:model.live="filterDateTo" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
            </label>
            <label class="text-xs font-medium text-slate-600">Übertragung (Bewertung + Benutzer)
                <select wire:model.live="filterSync" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Alle</option><option value="local">Nur lokal</option><option value="pending">Ausstehend</option>
                    <option value="synced">Synchronisiert</option><option value="failed">Fehlgeschlagen</option><option value="conflict">Konflikt</option>
                    <option value="unknown">Noch nicht geprüft</option>
                </select>
                <span class="mt-1 block font-normal text-slate-500">Konflikte, Fehler und ausstehende Änderungen haben Vorrang.</span>
            </label>
            @foreach(['filterInsurance' => ['insurance_id', 'Versicherer'], 'filterType' => ['insurance_type_id', 'Versicherungsart'], 'filterSubtype' => ['insurance_subtype_id', 'Unterart']] as $property => [$key, $label])
                <label class="text-xs font-medium text-slate-600">{{ $label }}
                    <select wire:model.live="{{ $property }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                        <option value="">Alle</option>
                        @foreach($ratingFilterOptions[$key] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                </label>
            @endforeach
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs font-medium text-slate-600">Score ab
                    <input type="number" min="0" max="1" step="0.01" wire:model.live.debounce.300ms="filterScoreMin" placeholder="0,00" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                </label>
                <label class="text-xs font-medium text-slate-600">Score bis
                    <input type="number" min="0" max="1" step="0.01" wire:model.live.debounce.300ms="filterScoreMax" placeholder="1,00" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                </label>
            </div>
            <label class="text-xs font-medium text-slate-600">Sichtbarkeit (lokaler Stand)
                <select wire:model.live="filterVisibility" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Alle</option><option value="public">Öffentlich markiert</option><option value="private">Nicht öffentlich</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Base-Bewertung verknüpft
                <select wire:model.live="filterBaseLink" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Alle</option><option value="linked">Ja</option><option value="unlinked">Nein</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Manuell bearbeitet
                <select wire:model.live="filterManual" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Alle</option><option value="yes">Ja</option><option value="no">Nein</option>
                </select>
            </label>
            <label class="text-xs font-medium text-slate-600">Benutzernamen-Generator
                <select wire:model.live="filterGenerator" class="mt-1 block w-full rounded-md border-slate-300 text-sm">
                    <option value="">Alle</option><option value="6">Version 6</option><option value="legacy">Älter / unbekannt</option>
                </select>
            </label>
        </div>
    </details>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500">
        <div class="flex flex-wrap items-center gap-2" aria-live="polite">
            <strong class="text-slate-700">{{ $ratings->total() }} Treffer</strong>
            <span wire:loading.delay class="text-blue-700">Wird aktualisiert …</span>
            @foreach($activeRatingFilters as $property => $label)
                <button type="button" wire:click="removeRatingFilter('{{ $property }}')" class="inline-flex max-w-xs items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-blue-800 hover:bg-blue-100" title="Diesen Filter entfernen">
                    <span class="truncate">{{ $label }}</span><span aria-hidden="true">×</span><span class="sr-only">entfernen</span>
                </button>
            @endforeach
        </div>
        @if(isset($filters))
            <label class="flex items-center gap-2">Pro Seite
                <select wire:model.live="perPage" class="rounded-md border-slate-300 py-1 text-xs">
                    @foreach([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
            </label>
        @endif
        <button type="button" wire:click="clearFilters" class="font-medium text-blue-700 underline">Filter zurücksetzen</button>
    </div>
    @foreach($ratingFilterErrors as $message)
        <p role="alert" class="mt-3 rounded-md bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</p>
    @endforeach
</section>
