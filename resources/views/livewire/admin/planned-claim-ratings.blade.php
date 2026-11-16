<div class="rating-workspace w-full min-w-0">
    @php
        $nextScheduledFor = $stats['next_scheduled_for'] ?? null;
        $nextScheduledLabel = $nextScheduledFor ? $nextScheduledFor->format('d.m.Y · H:i') : 'Kein Lauf geplant';
        $selectedRatingLookup = collect($selectedRatingIds)
            ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();
        $selectedRatingCount = count($selectedRatingLookup);
        $replannableCount = (int) ($stats['replannable'] ?? 0);
    @endphp

    <header class="rating-page-header">
        <div class="flex min-w-0 flex-wrap items-center gap-2.5">
            <h1 class="text-xl font-semibold tracking-tight text-slate-900">Geplante Bewertungen</h1>
            <span class="text-xs tabular-nums text-slate-500">{{ number_format($ratings->total(), 0, ',', '.') }} Einträge</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs text-slate-500">Nächster Lauf <strong class="ml-1 font-medium tabular-nums text-slate-700">{{ $nextScheduledLabel }}</strong></span>
            <details class="relative" wire:ignore.self wire:key="planned-ai-settings" x-data x-on:click.outside="$el.open = false" x-on:keydown.escape.prevent.stop="$el.open = false">
                <summary class="rating-button cursor-pointer">AI-Einstellungen</summary>
                <div class="rating-ai-panel rating-shell absolute right-0 z-30 mt-2 max-h-[70vh] w-[min(92vw,980px)] overflow-y-auto p-1">
        <h2 class="px-4 py-3 text-sm font-semibold text-slate-800">AI-Einstellungen</h2>
        <p class="px-4 pb-4 text-xs leading-5 text-slate-500">Änderungen gelten für neue Vorbereitungen. Bereits vorbereitete Bewertungen können über „AI neu“ mit den neuen Einstellungen erzeugt werden.</p>
        <form wire:submit.prevent="saveAiVariationSettings" class="grid gap-4 border-t border-slate-100 p-4 xl:grid-cols-3">
            <div class="rounded-lg bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Regulierungsdauer</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block text-xs font-medium text-slate-600">
                        Mindestdauer Tage
                        <input type="number" min="7" max="90" wire:model.defer="aiVariationSettings.min_duration_days" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Maximaldauer Tage
                        <input type="number" min="21" max="365" wire:model.defer="aiVariationSettings.max_duration_days" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Abschluss mind. vor Termin
                        <input type="number" min="0" max="30" wire:model.defer="aiVariationSettings.closed_ended_min_offset_days" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Abschluss max. vor Termin
                        <input type="number" min="1" max="120" wire:model.defer="aiVariationSettings.closed_ended_max_offset_days" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                </div>
            </div>

            <div class="rounded-lg bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Dauer-Mix Gewichtung</h3>
                <p class="mt-1 text-xs text-slate-500">Gewichte müssen nicht exakt 100 ergeben; sie werden relativ genutzt.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <label class="block text-xs font-medium text-slate-600">
                        Kurz
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.duration_mix.short" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Normal
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.duration_mix.normal" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Lang
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.duration_mix.long" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                </div>
            </div>

            <div class="rounded-lg bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">Regulierungsarten</h3>
                <p class="mt-1 text-xs text-slate-500">Gewichtete Verteilung plus optionales Übersteuern der AI-Antwort.</p>
                <div class="mt-3 grid gap-3 sm:grid-cols-5 xl:grid-cols-2">
                    <label class="block text-xs font-medium text-slate-600">
                        Vollzahlung
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.regulation_type_weights.vollzahlung" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Teilzahlung
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.regulation_type_weights.teilzahlung" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Ablehnung
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.regulation_type_weights.ablehnung" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600">
                        Ausstehend
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.regulation_type_weights.austehend" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                    <label class="block text-xs font-medium text-slate-600 sm:col-span-5 xl:col-span-2">
                        AI-Übersteuerung %
                        <input type="number" min="0" max="100" wire:model.defer="aiVariationSettings.regulation_ai_override_percent" class="mt-1 block w-full rounded-md border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 xl:col-span-3">
                <button type="button" wire:click="resetAiVariationSettings" wire:confirm="AI-Variationen wirklich auf Standardwerte zurücksetzen?" class="rating-button">
                    <i class="fal fa-undo"></i>
                    Standards
                </button>
                <button type="submit" class="rating-button rating-button-primary">
                    <i class="fal fa-save"></i>
                    Variationen speichern
                </button>
            </div>
        </form>
                </div>
            </details>
        </div>
    </header>

    @if(session('success'))
        <div role="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div role="alert" class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif



    @include('livewire.admin.partials.claim-rating-filters')

    <section class="rating-shell min-w-0 overflow-hidden" aria-label="Geplante Bewertungen">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-3">
            <div class="flex items-center gap-2 text-sm">
                <strong class="font-semibold tabular-nums text-slate-800">{{ $selectedRatingCount }}</strong>
                <span class="text-slate-500">ausgewählt</span>
                <details class="relative text-xs text-slate-500" wire:ignore.self wire:key="planned-selection-help" x-data x-on:click.outside="$el.open = false" x-on:keydown.escape.prevent.stop="$el.open = false">
                    <summary class="cursor-pointer rounded px-2 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-blue-500">Hinweise</summary>
                    <p class="rating-shell absolute left-0 z-20 mt-2 w-64 p-3 text-xs leading-5">Ausgewählt werden nur zukünftige, noch nicht gestartete Treffer der aktiven Filter. Ein Filterwechsel leert die Auswahl. Die Neuplanung ersetzt die gewählten Bewertungen und ihre eigenen Base-Verknüpfungen; Anzahl und Kalendertag bleiben erhalten.</p>
                </details>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="selectCurrentPage" wire:loading.attr="disabled" class="rating-button" aria-label="Diese Seite auswählen">Seite auswählen</button>
                <button type="button" wire:click="selectAllUpcoming" wire:loading.attr="disabled" wire:target="selectAllUpcoming,replanSelected" @disabled($replannableCount === 0) class="rating-button" aria-label="Alle {{ $replannableCount }} neu planbaren Treffer">Alle {{ $replannableCount }} Treffer</button>
                @if($selectedRatingCount > 0)
                <button type="button" wire:click="clearRatingSelection" @disabled($selectedRatingCount === 0) class="rating-button">Auswahl leeren</button>
                <button type="button" wire:click="replanSelected"
                    wire:confirm="{{ $selectedRatingCount }} ausgewählte bevorstehende Bewertung(en) wirklich verwerfen und vollständig neu planen? Vorhandene synthetische Base-Verknüpfungen werden gelöscht und beim Ersatz neu erstellt. Bereits ausgeführte oder inzwischen gestartete Einträge bleiben unverändert."
                    wire:loading.attr="disabled" wire:target="replanSelected" @disabled($selectedRatingCount === 0) class="rating-button rating-button-primary">
                    <span wire:loading.remove wire:target="replanSelected">Auswahl neu planen</span><span wire:loading wire:target="replanSelected">Wird neu geplant…</span>
                </button>
                @endif
            </div>
        </div>
        <div class="max-w-full overflow-x-auto" role="region" aria-label="Tabelle der geplanten Bewertungen" tabindex="0">
            <table class="rating-table w-full min-w-[1060px] text-left text-sm">
                <thead>
                    <tr>
                        <th class="w-12 text-center"><span class="sr-only">Für Neuplanung auswählen</span></th>
                        <th><div class="flex items-center gap-2"><button type="button" wire:click="sortBy('name')">Benutzer</button><button type="button" wire:click="sortBy('username')" title="Nach Benutzername sortieren" aria-label="Nach Benutzername sortieren" class="text-slate-400">@ <span aria-hidden="true">↕</span></button></div></th>
                        <th><div class="flex items-center gap-2"><button type="button" wire:click="sortBy('id')" class="inline-flex items-center gap-1">Bewertung <span aria-hidden="true">{{ $sortField === 'id' ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button><button type="button" wire:click="sortBy('base_claim_rating_id')" class="text-xs text-slate-400" title="Nach Base-ID sortieren">Base</button></div></th>
                        <th><button type="button" wire:click="sortBy('scheduled_for')" class="inline-flex items-center gap-1">Termin <span aria-hidden="true">{{ $sortField === 'scheduled_for' ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
                        <th><button type="button" wire:click="sortBy('rating_score')" class="inline-flex items-center gap-1">Score <span aria-hidden="true">{{ $sortField === 'rating_score' ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></button></th>
                        <th>Status &amp; Übertragung</th>
                        <th class="text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ratings as $rating)
                        @php
                            $state = $rating->execution_state_label;
                            $isPrepared = filled(data_get($rating->data, 'ai_generation.generated_at'));
                            $insuranceName = data_get($rating->data, 'base_context.insurance.name') ?: 'Versicherung #' . ($rating->insurance_id ?? '–');
                            $typeName = data_get($rating->data, 'base_context.insurance_type.name') ?: 'Typ #' . ($rating->insurance_type_id ?? '–');
                            $subtypeName = data_get($rating->data, 'base_context.insurance_subtype.name') ?: 'Untertyp #' . ($rating->insurance_subtype_id ?? '–');
                            $displayTime = $rating->executed_at ?: $rating->scheduled_for;
                            $isRetracted = $rating->isRetracted();
                            $isDue = !$isRetracted && !$rating->executed_at && $rating->scheduled_for && $rating->scheduled_for->lte(now());
                            $timeLabel = $isRetracted ? 'Zurückgerufen' : ($rating->executed_at ? 'Ausgeführt' : ($isDue ? 'Fällig' : 'Geplant'));
                            $score = $rating->rating_score !== null ? (float) $rating->rating_score : null;
                            $targetScoreProfile = data_get($rating->data, 'planning.target_score_profile');
                            $syntheticUser = $rating->syntheticUser;
                            $baseUserId = $syntheticUser?->base_user_id ?: $rating->base_user_id;
                            $canDelete = $rating->canBeDeletedFromPlan() && !$baseUserId;
                            $canReplan = $rating->isReplannableAt(now());
                            $isSelectedForReplan = isset($selectedRatingLookup[(string) $rating->id]);
                            $stateLabels = ['Ausgefuehrt' => 'Ausgeführt', 'Faellig' => 'Fällig', 'Laeuft' => 'Läuft', 'Zurueckgerufen' => 'Zurückgerufen'];
                        @endphp
                        <tr wire:key="planned-claim-rating-{{ $rating->id }}"
                            wire:click="openRatingModal({{ $rating->id }})"
                            x-on:keydown.enter.self="$wire.openRatingModal({{ $rating->id }})"
                            x-on:keydown.space.self.prevent="$wire.openRatingModal({{ $rating->id }})"
                            tabindex="0" aria-label="Bewertung #{{ $rating->id }} anzeigen"
                            @class(['cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500', 'bg-blue-50/60' => $isSelectedForReplan])>
                            <td class="text-center align-top">
                                @if($canReplan)
                                    <label class="inline-flex h-10 w-10 cursor-pointer items-center justify-center" x-on:click.stop>
                                        <input type="checkbox" value="{{ $rating->id }}" wire:model.live="selectedRatingIds" x-on:keydown.stop aria-label="Bewertung #{{ $rating->id }} für Neuplanung auswählen" class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-500">
                                    </label>
                                @else
                                    <span class="text-slate-300" title="Nicht neu planbar">–<span class="sr-only">Nicht neu planbar</span></span>
                                @endif
                            </td>
                            <td class="min-w-[250px] align-top">
                                @include('livewire.admin.partials.rating-user-identity', ['rating' => $rating])
                            </td>
                            <td class="align-top">
                                <button type="button" wire:click.stop="openRatingModal({{ $rating->id }})" class="py-1 font-semibold tabular-nums text-slate-900 hover:text-blue-700">#{{ $rating->id }}</button>
                                <p class="mt-1 text-xs font-medium text-slate-700">{{ $insuranceName }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $typeName }} · {{ $subtypeName }}</p>
                                @if($rating->base_claim_rating_id)<p class="mt-1 text-xs tabular-nums text-slate-400">Base #{{ $rating->base_claim_rating_id }}</p>@endif
                            </td>
                            <td class="whitespace-nowrap align-top">
                                <p class="font-medium tabular-nums text-slate-800">{{ optional($displayTime)->format('d.m.Y') ?? '–' }}</p>
                                <p class="mt-1 text-xs tabular-nums text-slate-500">{{ optional($displayTime)->format('H:i') }} · {{ $timeLabel }}</p>
                            </td>
                            <td class="align-top">
                                @if($score !== null)
                                    <span @class(['inline-flex rounded-md px-2 py-1 text-sm font-semibold tabular-nums', 'bg-emerald-50 text-emerald-800' => $score >= 0.7, 'bg-amber-50 text-amber-800' => $score >= 0.4 && $score < 0.7, 'bg-rose-50 text-rose-800' => $score < 0.4])>{{ number_format($score, 2, ',', '.') }}</span>
                                @elseif(is_array($targetScoreProfile))
                                    <p class="font-medium tabular-nums text-slate-700">{{ number_format((float) ($targetScoreProfile['target_score'] ?? 0), 2, ',', '.') }}</p>
                                    <p class="mt-1 text-xs text-slate-400">Zielscore</p>
                                @else
                                    <span class="text-slate-400">Offen</span>
                                @endif
                                @if($isPrepared)<p class="mt-2 text-xs text-slate-500">AI vorbereitet</p>@endif
                            </td>
                            <td class="min-w-[180px] align-top">
                                <span @class(['text-xs font-medium', 'text-rose-700' => $rating->status === 'failed', 'text-slate-700' => $rating->status !== 'failed'])>{{ $isRetracted ? 'Zurückgerufen' : $rating->status_label }}</span>
                                <div class="mt-2">@include('livewire.admin.partials.claim-rating-sync-state')</div>
                                @if($rating->execution_attempts > 0)<p class="mt-2 text-xs tabular-nums text-slate-400">{{ $rating->execution_attempts }} Ausführungsversuche</p>@endif
                                @if($rating->last_execution_error)<p class="mt-2 max-w-xs text-xs leading-5 text-rose-700">{{ $rating->last_execution_error }}</p>@endif
                            </td>
                            <td class="align-top">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if($isRetracted)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-xs font-medium text-slate-700">
                                            <i class="fal fa-ban"></i>
                                            Dauerhaft gesperrt
                                        </span>
                                    @elseif($rating->status === \App\Models\ClaimRating::STATUS_PROCESSING)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                                            <i class="fal fa-spinner fa-spin"></i>
                                            AI läuft
                                        </span>
                                    @elseif($rating->executed_at)
                                        <button
                                            type="button"
                                            wire:click.stop="undoExecution({{ $rating->id }})"
                                            wire:confirm="Diese Ausfuehrung wirklich rueckgaengig machen und den synthetischen Base-Datensatz entfernen?"
                                            wire:loading.attr="disabled"
                                            wire:target="undoExecution({{ $rating->id }})"
                                            class="rating-button text-rose-700"
                                        >
                                            <i class="fal fa-undo" wire:loading.remove wire:target="undoExecution({{ $rating->id }})"></i>
                                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="undoExecution({{ $rating->id }})"></i>
                                            <span wire:loading.remove wire:target="undoExecution({{ $rating->id }})">Rückgängig</span>
                                            <span wire:loading wire:target="undoExecution({{ $rating->id }})">Entfernt...</span>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click.stop="prepareWithAi({{ $rating->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="prepareWithAi({{ $rating->id }})"
                                            class="rating-button"
                                        >
                                            <i class="fal fa-magic" wire:loading.remove wire:target="prepareWithAi({{ $rating->id }})"></i>
                                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="prepareWithAi({{ $rating->id }})"></i>
                                            <span wire:loading.remove wire:target="prepareWithAi({{ $rating->id }})">
                                                {{ $isPrepared ? 'AI neu' : 'AI' }}
                                            </span>
                                            <span wire:loading wire:target="prepareWithAi({{ $rating->id }})">AI...</span>
                                        </button>

                                        <button
                                            type="button"
                                            wire:click.stop="executeNow({{ $rating->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="executeNow({{ $rating->id }})"
                                            class="rating-button rating-button-primary"
                                        >
                                            <i class="fal fa-play" wire:loading.remove wire:target="executeNow({{ $rating->id }})"></i>
                                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="executeNow({{ $rating->id }})"></i>
                                            <span wire:loading.remove wire:target="executeNow({{ $rating->id }})">Ausführen</span>
                                            <span wire:loading wire:target="executeNow({{ $rating->id }})">Startet...</span>
                                        </button>
                                    @endif

                                    @if($canDelete)
                                        <button
                                            type="button"
                                            wire:click.stop="deleteRating({{ $rating->id }})"
                                            wire:confirm="Diese noch nicht ausgefuehrte Bewertung wirklich loeschen? Ein verwaister lokaler Testnutzer wird ebenfalls entfernt."
                                            wire:loading.attr="disabled"
                                            wire:target="deleteRating({{ $rating->id }})"
                                            class="rating-button text-rose-700"
                                        >
                                            <i class="fal fa-trash" wire:loading.remove wire:target="deleteRating({{ $rating->id }})"></i>
                                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="deleteRating({{ $rating->id }})"></i>
                                            <span wire:loading.remove wire:target="deleteRating({{ $rating->id }})">Löschen</span>
                                            <span wire:loading wire:target="deleteRating({{ $rating->id }})">Löscht…</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center">
                                <p class="font-semibold text-slate-700">Keine geplanten Bewertungen gefunden</p>
                                <p class="mt-2 text-sm text-slate-500">Passe die Suche oder Filter an, um weitere Bewertungen zu sehen.</p>
                                <button type="button" wire:click="clearFilters" class="rating-button mt-4">Filter zurücksetzen</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="rating-list-footer">
        <label class="rating-page-size">Pro Seite <select wire:model.live="perPage" aria-label="Einträge pro Seite">@foreach([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></label>
        <div class="min-w-0 flex-1">{{ $ratings->links() }}</div>
    </div>
    @include('livewire.admin.partials.claim-rating-detail-modal', ['selectedRating' => $selectedRating])
</div>
