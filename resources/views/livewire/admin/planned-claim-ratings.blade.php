<div class="space-y-6">
    <section class="border-b border-slate-200 pb-5">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
                <div class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <i class="fal fa-calendar-check text-blue-600"></i>
                    Bewertungsplanung
                </div>
                <h1 class="mt-3 text-3xl font-semibold text-slate-950">Geplante Bewertungen</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                    Zeitplan, AI-Vorbereitung, Score und Base-Ausfuehrung der internen Bewertungslaeufe.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="inline-flex items-center gap-3 rounded-md border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-blue-50 text-blue-700">
                        <i class="fal fa-clock"></i>
                    </span>
                    <div>
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Naechster Lauf</div>
                        <div class="mt-0.5 text-sm font-semibold text-slate-950">
                            {{ $stats['next_scheduled_for'] ? $stats['next_scheduled_for']->format('d.m.Y H:i') : 'Kein Lauf geplant' }}
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="clearFilters"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <i class="fal fa-filter-circle-xmark"></i>
                    Filter zuruecksetzen
                </button>
            </div>
        </div>
    </section>

    @if (session('success'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            <i class="fal fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            <i class="fal fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <section class="overflow-x-auto pb-1">
        <div class="grid min-w-[980px] grid-cols-5 gap-3">
            @php
                $kpis = [
                    ['label' => 'Gesamt', 'value' => $stats['total'], 'icon' => 'fa-layer-group', 'box' => 'border-slate-200', 'tone' => 'bg-slate-100 text-slate-700'],
                    ['label' => 'Faellig', 'value' => $stats['due'], 'icon' => 'fa-hourglass-end', 'box' => 'border-amber-200', 'tone' => 'bg-amber-50 text-amber-700'],
                    ['label' => 'Anstehend', 'value' => $stats['upcoming'], 'icon' => 'fa-calendar-day', 'box' => 'border-blue-200', 'tone' => 'bg-blue-50 text-blue-700'],
                    ['label' => 'Ausgefuehrt', 'value' => $stats['executed'], 'icon' => 'fa-database', 'box' => 'border-emerald-200', 'tone' => 'bg-emerald-50 text-emerald-700'],
                    ['label' => 'Fehler', 'value' => $stats['failed'], 'icon' => 'fa-triangle-exclamation', 'box' => 'border-rose-200', 'tone' => 'bg-rose-50 text-rose-700'],
                ];
            @endphp

            @foreach($kpis as $kpi)
                <div class="rounded-md border {{ $kpi['box'] }} bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</div>
                            <div class="mt-3 text-3xl font-semibold text-slate-950">{{ $kpi['value'] }}</div>
                        </div>
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-md {{ $kpi['tone'] }}">
                            <i class="fal {{ $kpi['icon'] }}"></i>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_260px]">
            <div>
                <label for="planned-rating-search" class="mb-1 block text-sm font-medium text-slate-700">Suche</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <i class="fal fa-search"></i>
                    </span>
                    <input
                        id="planned-rating-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="ID, Base-ID, Versicherung, Status oder Fehler"
                        class="block w-full rounded-md border border-slate-300 py-2 pl-10 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>
            </div>

            <div>
                <label for="execution-filter" class="mb-1 block text-sm font-medium text-slate-700">Statusfilter</label>
                <select
                    id="execution-filter"
                    wire:model.live="executionFilter"
                    class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >
                    @foreach($filters as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 bg-slate-50/70 px-4 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-base font-semibold text-slate-950">Bewertungslaeufe</h2>
                <p class="mt-0.5 text-sm text-slate-500">Zeile anklicken, um alle Antworten und AI-Details zu oeffnen.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600">
                <i class="fal fa-table-list"></i>
                {{ $ratings->total() }} Eintraege
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1180px] divide-y divide-slate-200 text-sm">
                <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('id')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                ID <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('scheduled_for')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                Ausfuehrungszeit <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">Versicherung</th>
                        <th class="px-4 py-3">Art / Unterart</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('rating_score')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                AI Score <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">Ausfuehrung</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('base_claim_rating_id')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                Base <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('execution_attempts')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                Versuche <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">Fehler</th>
                        <th class="px-4 py-3">Aktionen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($ratings as $rating)
                        @php
                            $state = $rating->execution_state_label;
                            $stateClasses = match ($state) {
                                'Ausgefuehrt' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'Fehler' => 'border-rose-200 bg-rose-50 text-rose-700',
                                'Faellig' => 'border-amber-200 bg-amber-50 text-amber-700',
                                'Laeuft' => 'border-blue-200 bg-blue-50 text-blue-700',
                                'AI vorbereitet' => 'border-cyan-200 bg-cyan-50 text-cyan-700',
                                default => 'border-slate-200 bg-slate-50 text-slate-700',
                            };
                            $stateIcon = match ($state) {
                                'Ausgefuehrt' => 'fa-check',
                                'Fehler' => 'fa-triangle-exclamation',
                                'Faellig' => 'fa-hourglass-end',
                                'Laeuft' => 'fa-spinner fa-spin',
                                'AI vorbereitet' => 'fa-wand-magic-sparkles',
                                default => 'fa-calendar',
                            };
                            $isPrepared = filled(data_get($rating->data, 'ai_generation.generated_at'));
                            $insuranceName = data_get($rating->data, 'base_context.insurance.name') ?: 'Versicherung #' . ($rating->insurance_id ?? '-');
                            $typeName = data_get($rating->data, 'base_context.insurance_type.name') ?: 'Typ #' . ($rating->insurance_type_id ?? '-');
                            $subtypeName = data_get($rating->data, 'base_context.insurance_subtype.name') ?: 'Untertyp #' . ($rating->insurance_subtype_id ?? '-');
                            $displayTime = $rating->executed_at ?: $rating->scheduled_for;
                            $timeLabel = $rating->executed_at ? 'Ausgefuehrt' : 'Geplant';
                            $score = $rating->rating_score !== null ? (float) $rating->rating_score : null;
                            $targetScoreProfile = data_get($rating->data, 'planning.target_score_profile');
                        @endphp
                        <tr
                            wire:key="planned-claim-rating-{{ $rating->id }}"
                            wire:click="openRatingModal({{ $rating->id }})"
                            x-on:keydown.enter="$wire.openRatingModal({{ $rating->id }})"
                            x-on:keydown.space.prevent="$wire.openRatingModal({{ $rating->id }})"
                            tabindex="0"
                            role="button"
                            aria-label="Bewertung #{{ $rating->id }} anzeigen"
                            class="cursor-pointer transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                        >
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-800">
                                    #{{ $rating->id }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                <div class="font-semibold text-slate-950">{{ optional($displayTime)->format('d.m.Y H:i') ?? '-' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $timeLabel }}</div>
                            </td>
                            <td class="min-w-[220px] px-4 py-4 align-top">
                                <div class="font-semibold text-slate-950">{{ $insuranceName }}</div>
                                <div class="mt-1 text-xs text-slate-500">ID {{ $rating->insurance_id ?? '-' }}</div>
                            </td>
                            <td class="min-w-[260px] px-4 py-4 align-top">
                                <div class="font-medium text-slate-900">{{ $typeName }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $subtypeName }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                @if($score !== null)
                                    <div class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-2.5 py-1.5">
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-md {{ $score >= 0.7 ? 'bg-emerald-50 text-emerald-700' : ($score >= 0.4 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                                            <i class="fal fa-chart-line"></i>
                                        </span>
                                        <div>
                                            <div class="text-sm font-semibold text-slate-950">{{ number_format($score, 2, ',', '.') }}</div>
                                            <div class="text-[11px] font-medium text-slate-500">Gesamtscore</div>
                                        </div>
                                    </div>
                                @elseif($isPrepared)
                                    <span class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                        Score offen
                                    </span>
                                @elseif(is_array($targetScoreProfile))
                                    <div class="inline-flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-blue-800">
                                        <i class="fal fa-bullseye-arrow"></i>
                                        <div>
                                            <div class="text-xs font-semibold">{{ $targetScoreProfile['label'] ?? 'Zielscore' }}</div>
                                            <div class="text-[11px] text-blue-700">{{ number_format((float) ($targetScoreProfile['target_score'] ?? 0), 2, ',', '.') }}</div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Nicht vorbereitet</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $stateClasses }}">
                                    <i class="fal {{ $stateIcon }}"></i>
                                    {{ $state }}
                                </span>
                                <div class="mt-2 text-xs text-slate-500">{{ $rating->status_label }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                @if($rating->base_claim_rating_id)
                                    <div class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                        <i class="fal fa-database"></i>
                                        #{{ $rating->base_claim_rating_id }}
                                    </div>
                                @else
                                    <div class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-500">
                                        <i class="fal fa-database"></i>
                                        Offen
                                    </div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top text-slate-700">
                                <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md bg-slate-100 px-2 text-xs font-semibold text-slate-700">
                                    {{ $rating->execution_attempts ?? 0 }}
                                </span>
                            </td>
                            <td class="max-w-xs px-4 py-4 align-top text-slate-600">
                                @if($rating->last_execution_error)
                                    <span class="line-clamp-2 rounded-md bg-rose-50 px-2 py-1 text-xs text-rose-700">{{ $rating->last_execution_error }}</span>
                                @else
                                    <span class="text-xs text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                @if($rating->status === \App\Models\ClaimRating::STATUS_PROCESSING)
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
                                        <i class="fal fa-spinner fa-spin"></i>
                                        AI laeuft
                                    </span>
                                @elseif($rating->executed_at)
                                    <button
                                        type="button"
                                        wire:click.stop="undoExecution({{ $rating->id }})"
                                        wire:confirm="Diese Ausfuehrung wirklich rueckgaengig machen und den synthetischen Base-Datensatz entfernen?"
                                        wire:loading.attr="disabled"
                                        wire:target="undoExecution({{ $rating->id }})"
                                        class="inline-flex items-center justify-center gap-2 rounded-md border border-rose-300 bg-white px-3 py-2 text-xs font-medium text-rose-700 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <i class="fal fa-rotate-left" wire:loading.remove wire:target="undoExecution({{ $rating->id }})"></i>
                                        <i class="fal fa-spinner fa-spin" wire:loading wire:target="undoExecution({{ $rating->id }})"></i>
                                        <span wire:loading.remove wire:target="undoExecution({{ $rating->id }})">Rueckgaengig</span>
                                        <span wire:loading wire:target="undoExecution({{ $rating->id }})">Entfernt...</span>
                                    </button>
                                @else
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            wire:click.stop="prepareWithAi({{ $rating->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="prepareWithAi({{ $rating->id }})"
                                            class="inline-flex items-center justify-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <i class="fal fa-wand-magic-sparkles" wire:loading.remove wire:target="prepareWithAi({{ $rating->id }})"></i>
                                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="prepareWithAi({{ $rating->id }})"></i>
                                            <span wire:loading.remove wire:target="prepareWithAi({{ $rating->id }})">
                                                {{ $isPrepared ? 'AI neu' : 'AI vorbereiten' }}
                                            </span>
                                            <span wire:loading wire:target="prepareWithAi({{ $rating->id }})">Bereitet vor...</span>
                                        </button>

                                        <button
                                            type="button"
                                            wire:click.stop="executeNow({{ $rating->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="executeNow({{ $rating->id }})"
                                            class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-3 py-2 text-xs font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <i class="fal fa-play" wire:loading.remove wire:target="executeNow({{ $rating->id }})"></i>
                                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="executeNow({{ $rating->id }})"></i>
                                            <span wire:loading.remove wire:target="executeNow({{ $rating->id }})">Ausfuehren</span>
                                            <span wire:loading wire:target="executeNow({{ $rating->id }})">Fuehrt aus...</span>
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-14 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-2 text-sm text-slate-500">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-slate-100 text-slate-500">
                                        <i class="fal fa-calendar-xmark"></i>
                                    </span>
                                    <div class="font-medium text-slate-700">Keine geplanten Bewertungen gefunden</div>
                                    <div>Passe die Filter an oder starte die Planung erneut.</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div>
        {{ $ratings->links() }}
    </div>

    @include('livewire.admin.partials.claim-rating-detail-modal', ['selectedRating' => $selectedRating])
</div>
