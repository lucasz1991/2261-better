<section class="rating-tools" aria-label="Bewertungen suchen und filtern">
    <div class="rating-filter-line">
        <label class="rating-search">
            <span class="sr-only">Suche nach Person oder Bewertung</span>
            <i class="fal fa-search" aria-hidden="true"></i>
            <input type="search" wire:model.live.debounce.300ms="search" maxlength="200" placeholder="Name, @Benutzername, Text oder ID …">
        </label>
        <label class="rating-quick-filter">
            <span class="sr-only">Bewertungsstatus</span>
            <select wire:model.live="status" aria-label="Bewertungsstatus">
                <option value="">Alle Status</option>
                @foreach($statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </label>
        @if(isset($filters))
            <label class="rating-quick-filter">
                <span class="sr-only">Ausführung</span>
                <select wire:model.live="executionFilter" aria-label="Ausführung">
                    @foreach($filters as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </label>
        @endif
        <details class="rating-filter-dropdown" wire:ignore.self wire:key="advanced-rating-filters"
            x-data x-on:click.outside="$el.removeAttribute('open')" x-on:keydown.escape.stop="$el.removeAttribute('open'); $el.querySelector('summary').focus()">
            <summary class="rating-button"><i class="fal fa-sliders-h" aria-hidden="true"></i>Filter
                @if(count($activeRatingFilters))<span class="rating-small-count">{{ count($activeRatingFilters) }}</span>@endif
                <i class="fal fa-angle-down" aria-hidden="true"></i>
            </summary>
            <div class="rating-popover rating-filter-panel">
                <div class="rating-popover-heading"><strong>Bewertungen eingrenzen</strong><button type="button" class="rating-row-action" x-on:click="$el.closest('details').removeAttribute('open'); $el.closest('details').querySelector('summary').focus()" aria-label="Filter schließen"><i class="fal fa-times" aria-hidden="true"></i></button></div>
                <div class="rating-filter-grid">
                    <label class="rating-field">Zeitraum nach
                        <select wire:model.live="filterDateField"><option value="created_at">Erstelldatum</option><option value="scheduled_for">Planung</option><option value="executed_at">Ausführung</option><option value="updated_at">Letzte Änderung</option></select>
                    </label>
                    <label class="rating-field">Von<input type="date" wire:model.live="filterDateFrom"></label>
                    <label class="rating-field">Bis einschließlich<input type="date" wire:model.live="filterDateTo"></label>
                    @foreach(['filterInsurance' => ['insurance_id', 'Versicherer'], 'filterType' => ['insurance_type_id', 'Versicherungsart'], 'filterSubtype' => ['insurance_subtype_id', 'Unterart']] as $property => [$key, $label])
                        <label class="rating-field">{{ $label }}<select wire:model.live="{{ $property }}"><option value="">Alle</option>@foreach($ratingFilterOptions[$key] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                    @endforeach
                    <div class="grid grid-cols-2 gap-2">
                        <label class="rating-field">Score ab<input type="number" min="0" max="1" step="0.01" wire:model.live.debounce.300ms="filterScoreMin" placeholder="0,00"></label>
                        <label class="rating-field">Score bis<input type="number" min="0" max="1" step="0.01" wire:model.live.debounce.300ms="filterScoreMax" placeholder="1,00"></label>
                    </div>
                    <label class="rating-field">Sichtbarkeit<select wire:model.live="filterVisibility"><option value="">Alle</option><option value="public">Öffentlich markiert</option><option value="private">Nicht öffentlich</option></select></label>
                    <label class="rating-field">Übertragung<select wire:model.live="filterSync"><option value="">Alle</option><option value="local">Nur lokal</option><option value="pending">Ausstehend</option><option value="synced">Synchronisiert</option><option value="failed">Fehlgeschlagen</option><option value="conflict">Konflikt</option><option value="unknown">Noch nicht geprüft</option></select></label>
                    <label class="rating-field">Base-Verknüpfung<select wire:model.live="filterBaseLink"><option value="">Alle</option><option value="linked">Verknüpft</option><option value="unlinked">Nicht verknüpft</option></select></label>
                    <label class="rating-field">Manuell bearbeitet<select wire:model.live="filterManual"><option value="">Alle</option><option value="yes">Ja</option><option value="no">Nein</option></select></label>
                    <label class="rating-field">Namensgenerator<select wire:model.live="filterGenerator"><option value="">Alle Versionen</option><option value="6">Version 6</option><option value="legacy">Älter / unbekannt</option></select></label>
                    <label class="rating-field">Sortieren nach<select wire:model.live="sortField"><option value="id">Bewertungs-ID</option><option value="name">Anzeigename</option><option value="username">Benutzername</option><option value="rating_score">Score</option><option value="status">Status</option><option value="is_public">Sichtbarkeit</option><option value="base_claim_rating_id">Base-ID</option><option value="executed_at">Ausführung</option>@if(isset($filters))<option value="scheduled_for">Termin</option>@endif<option value="created_at">Erstellung</option><option value="updated_at">Letzte Änderung</option></select></label>
                    <label class="rating-field">Reihenfolge<select wire:model.live="sortDirection"><option value="desc">Absteigend</option><option value="asc">Aufsteigend</option></select></label>
                </div>
                <div class="rating-popover-footer"><span>Filter werden sofort angewendet. Zeiten: Berlin.</span><button type="button" wire:click="clearFilters" class="rating-text-button">Zurücksetzen</button></div>
            </div>
        </details>
        @can('synthetic_users.quality')
            <livewire:admin.rating-name-quality wire:model.live="filterNameQuality" :key="'rating-name-quality-' . $this->getId()" />
        @endcan
        <span class="rating-result-count" aria-live="polite">{{ number_format($ratings->total(), 0, ',', '.') }} Treffer<span wire:loading.delay class="ml-2" aria-label="Wird aktualisiert"><i class="fal fa-spinner fa-spin" aria-hidden="true"></i></span></span>
    </div>
    @if($activeRatingFilters)
        <div class="rating-active-filters" aria-label="Aktive Filter">
            @foreach($activeRatingFilters as $property => $label)
                <button type="button" wire:click="removeRatingFilter('{{ $property }}')" class="rating-filter-chip" title="Diesen Filter entfernen"><span>{{ $label }}</span><i class="fal fa-times" aria-hidden="true"></i><span class="sr-only">entfernen</span></button>
            @endforeach
            <button type="button" wire:click="clearFilters" class="rating-text-button">Alle zurücksetzen</button>
        </div>
    @endif
    @foreach($ratingFilterErrors as $message)<p role="alert" class="mt-2 rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ $message }}</p>@endforeach
</section>
