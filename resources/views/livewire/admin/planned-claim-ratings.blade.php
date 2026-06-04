<div class="w-full min-w-0 max-w-none space-y-4">
    @php
        $nextScheduledFor = $stats['next_scheduled_for'] ?? null;
        $nextScheduledLabel = $nextScheduledFor ? $nextScheduledFor->format('d.m.Y H:i') : 'Kein Lauf geplant';
    @endphp

    @php
        $kpis = [
            ['label' => 'Gesamt', 'value' => $stats['total'], 'icon' => 'fa-layer-group', 'tone' => 'bg-slate-100 text-slate-700'],
            ['label' => 'Faellig', 'value' => $stats['due'], 'icon' => 'fa-hourglass-end', 'tone' => 'bg-amber-50 text-amber-700'],
            ['label' => 'Anstehend', 'value' => $stats['upcoming'], 'icon' => 'fa-calendar-day', 'tone' => 'bg-blue-50 text-blue-700'],
            ['label' => 'Ausgefuehrt', 'value' => $stats['executed'], 'icon' => 'fa-database', 'tone' => 'bg-emerald-50 text-emerald-700'],
            ['label' => 'Fehler', 'value' => $stats['failed'], 'icon' => 'fa-triangle-exclamation', 'tone' => 'bg-rose-50 text-rose-700'],
        ];
    @endphp

    <section class="w-full min-w-0 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                    <i class="fal fa-calendar-check"></i>
                </span>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-semibold tracking-tight text-slate-950">Geplante Bewertungen</h1>
                        <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                            <i class="fal fa-table-list"></i>
                            {{ $ratings->total() }} Eintraege
                        </span>
                    </div>
                    <p class="mt-0.5 truncate text-sm text-slate-500">Zeitplan, AI-Vorbereitung, Score und Base-Ausfuehrung.</p>
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <div class="inline-flex min-w-0 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-white text-blue-700">
                        <i class="fal fa-clock"></i>
                    </span>
                    <div class="min-w-0">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Naechster Lauf</div>
                        <div class="truncate text-sm font-semibold text-slate-950">{{ $nextScheduledLabel }}</div>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="clearFilters"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    <i class="fal fa-filter-circle-xmark"></i>
                    Filter zuruecksetzen
                </button>
            </div>
        </div>

        <div class="mt-3 grid min-w-0 grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-5">
            @foreach($kpis as $kpi)
                <div class="min-w-0 rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $kpi['label'] }}</div>
                            <div class="mt-1 text-xl font-semibold leading-none text-slate-950">{{ $kpi['value'] }}</div>
                        </div>
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md {{ $kpi['tone'] }}">
                            <i class="fal {{ $kpi['icon'] }}"></i>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            <i class="fal fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            <i class="fal fa-exclamation-circle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <section class="w-full min-w-0 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 bg-slate-50/80 px-3 py-3 sm:px-4">
            <div class="grid min-w-0 gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(300px,460px)] lg:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-slate-950">Bewertungslaeufe</h2>
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600">
                            <i class="fal fa-table-list"></i>
                            {{ $ratings->total() }} Eintraege
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500">Zeile anklicken, um Antworten, Konfiguration und Benutzerdaten zu oeffnen.</p>
                </div>

                <div class="grid min-w-0 gap-2 sm:grid-cols-[minmax(0,1fr)_170px]">
                    <div>
                        <label for="planned-rating-search" class="sr-only">Suche</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fal fa-search"></i>
                            </span>
                            <input
                                id="planned-rating-search"
                                type="search"
                                wire:model.live.debounce.300ms="search"
                                placeholder="Suche nach ID, Base, Anbieter, Fehler..."
                                class="block w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="execution-filter" class="sr-only">Statusfilter</label>
                        <select
                            id="execution-filter"
                            wire:model.live="executionFilter"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            @foreach($filters as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1120px] divide-y divide-slate-200 text-sm">
                <thead class="bg-white text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('id')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                Lauf <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('scheduled_for')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                Termin <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">Kontext</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('rating_score')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                AI / Score <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('base_claim_rating_id')" class="inline-flex items-center gap-1 font-semibold hover:text-slate-900">
                                Base <i class="fal fa-sort text-[10px]"></i>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-right">Aktionen</th>
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
                                'Zurueckgerufen' => 'border-slate-300 bg-slate-100 text-slate-700',
                                default => 'border-slate-200 bg-slate-50 text-slate-700',
                            };
                            $stateIcon = match ($state) {
                                'Ausgefuehrt' => 'fa-check',
                                'Fehler' => 'fa-triangle-exclamation',
                                'Faellig' => 'fa-hourglass-end',
                                'Laeuft' => 'fa-spinner fa-spin',
                                'AI vorbereitet' => 'fa-wand-magic-sparkles',
                                'Zurueckgerufen' => 'fa-rotate-left',
                                default => 'fa-calendar',
                            };
                            $isPrepared = filled(data_get($rating->data, 'ai_generation.generated_at'));
                            $preparedAt = data_get($rating->data, 'ai_generation.generated_at');
                            $insuranceName = data_get($rating->data, 'base_context.insurance.name') ?: 'Versicherung #' . ($rating->insurance_id ?? '-');
                            $typeName = data_get($rating->data, 'base_context.insurance_type.name') ?: 'Typ #' . ($rating->insurance_type_id ?? '-');
                            $subtypeName = data_get($rating->data, 'base_context.insurance_subtype.name') ?: 'Untertyp #' . ($rating->insurance_subtype_id ?? '-');
                            $displayTime = $rating->executed_at ?: $rating->scheduled_for;
                            $timeLabel = $rating->executed_at ? 'Ausgefuehrt' : 'Geplant';
                            $isDue = ! $rating->executed_at && $rating->scheduled_for && $rating->scheduled_for->lte(now());
                            $timeTone = $rating->executed_at
                                ? 'bg-emerald-50 text-emerald-700'
                                : ($isDue ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700');
                            $score = $rating->rating_score !== null ? (float) $rating->rating_score : null;
                            $scoreTone = $score === null
                                ? 'bg-slate-100 text-slate-600'
                                : ($score >= 0.7 ? 'bg-emerald-50 text-emerald-700' : ($score >= 0.4 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'));
                            $targetScoreProfile = data_get($rating->data, 'planning.target_score_profile');
                            $syntheticUser = $rating->syntheticUser;
                            $baseUserId = $syntheticUser?->base_user_id ?: $rating->base_user_id;
                            $userLabel = $syntheticUser?->display_name ?: data_get($rating->user_data, 'display_name');
                        @endphp

                        <tr
                            wire:key="planned-claim-rating-{{ $rating->id }}"
                            wire:click="openRatingModal({{ $rating->id }})"
                            x-on:keydown.enter="$wire.openRatingModal({{ $rating->id }})"
                            x-on:keydown.space.prevent="$wire.openRatingModal({{ $rating->id }})"
                            tabindex="0"
                            role="button"
                            aria-label="Bewertung #{{ $rating->id }} anzeigen"
                            class="group cursor-pointer transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                        >
                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-md bg-slate-100 text-xs font-semibold text-slate-800">
                                        #{{ $rating->id }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-xs font-medium text-slate-500">Status</div>
                                        <div class="mt-1 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $stateClasses }}">
                                            <i class="fal {{ $stateIcon }}"></i>
                                            {{ $state }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 align-top">
                                <div class="inline-flex items-center gap-2 rounded-lg px-2.5 py-2 {{ $timeTone }}">
                                    <i class="fal {{ $rating->executed_at ? 'fa-check-circle' : 'fa-clock' }}"></i>
                                    <div>
                                        <div class="text-sm font-semibold">{{ optional($displayTime)->format('d.m.Y H:i') ?? '-' }}</div>
                                        <div class="text-[11px] font-medium">{{ $isDue ? 'Faellig' : $timeLabel }}</div>
                                    </div>
                                </div>
                            </td>

                            <td class="min-w-[310px] px-4 py-4 align-top">
                                <div class="font-semibold text-slate-950">{{ $insuranceName }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $typeName }}</span>
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">{{ $subtypeName }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span>Versicherung-ID {{ $rating->insurance_id ?? '-' }}</span>
                                    @if($userLabel)
                                        <span class="inline-flex items-center gap-1">
                                            <i class="fal fa-user"></i>
                                            {{ $userLabel }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="min-w-[190px] px-4 py-4 align-top">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md {{ $scoreTone }}">
                                        <i class="fal {{ $score === null ? 'fa-bullseye-arrow' : 'fa-chart-line' }}"></i>
                                    </span>
                                    <div class="min-w-0">
                                        @if($score !== null)
                                            <div class="text-base font-semibold leading-none text-slate-950">{{ number_format($score, 2, ',', '.') }}</div>
                                            <div class="mt-1 text-xs text-slate-500">Gesamtscore</div>
                                        @elseif(is_array($targetScoreProfile))
                                            <div class="truncate text-sm font-semibold text-slate-950">{{ $targetScoreProfile['label'] ?? 'Zielscore' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">Ziel {{ number_format((float) ($targetScoreProfile['target_score'] ?? 0), 2, ',', '.') }}</div>
                                        @else
                                            <div class="text-sm font-semibold text-slate-700">Noch offen</div>
                                            <div class="mt-1 text-xs text-slate-500">AI nicht vorbereitet</div>
                                        @endif
                                    </div>
                                </div>

                                @if($isPrepared)
                                    <div class="mt-2 inline-flex items-center gap-1 rounded-md border border-cyan-200 bg-cyan-50 px-2 py-1 text-xs font-medium text-cyan-700">
                                        <i class="fal fa-wand-magic-sparkles"></i>
                                        AI vorbereitet
                                    </div>
                                @elseif($preparedAt)
                                    <div class="mt-2 text-xs text-slate-500">{{ $preparedAt }}</div>
                                @endif
                            </td>

                            <td class="min-w-[210px] px-4 py-4 align-top">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700">
                                        <i class="fal fa-rotate"></i>
                                        {{ $rating->execution_attempts ?? 0 }} Versuche
                                    </span>
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                        {{ $rating->status_label }}
                                    </span>
                                </div>

                                @if($rating->last_execution_error)
                                    <div class="mt-2 max-w-[260px] rounded-md border border-rose-200 bg-rose-50 px-2.5 py-2 text-xs leading-5 text-rose-700">
                                        {{ $rating->last_execution_error }}
                                    </div>
                                @else
                                    <div class="mt-2 text-xs text-slate-400">Keine Fehlermeldung</div>
                                @endif
                            </td>

                            <td class="min-w-[210px] px-4 py-4 align-top">
                                <div class="flex flex-col gap-1.5">
                                    @if($rating->base_claim_rating_id)
                                        <span class="inline-flex w-fit items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                            <i class="fal fa-database"></i>
                                            Bewertung #{{ $rating->base_claim_rating_id }}
                                        </span>
                                    @else
                                        <span class="inline-flex w-fit items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-500">
                                            <i class="fal fa-database"></i>
                                            Bewertung offen
                                        </span>
                                    @endif

                                    @if($baseUserId)
                                        <span class="inline-flex w-fit items-center gap-1 rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                                            <i class="fal fa-user"></i>
                                            Base-User #{{ $baseUserId }}
                                        </span>
                                    @elseif($syntheticUser)
                                        <span class="inline-flex w-fit items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700">
                                            <i class="fal fa-user"></i>
                                            Lokal #{{ $syntheticUser->id }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">Kein Benutzer vorbereitet</span>
                                    @endif
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-4 text-right align-top">
                                @if($rating->status === \App\Models\ClaimRating::STATUS_PROCESSING)
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700">
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
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-rose-300 bg-white px-3 py-2 text-xs font-medium text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <i class="fal fa-rotate-left" wire:loading.remove wire:target="undoExecution({{ $rating->id }})"></i>
                                        <i class="fal fa-spinner fa-spin" wire:loading wire:target="undoExecution({{ $rating->id }})"></i>
                                        <span wire:loading.remove wire:target="undoExecution({{ $rating->id }})">Rueckgaengig</span>
                                        <span wire:loading wire:target="undoExecution({{ $rating->id }})">Entfernt...</span>
                                    </button>
                                @else
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click.stop="prepareWithAi({{ $rating->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="prepareWithAi({{ $rating->id }})"
                                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <i class="fal fa-wand-magic-sparkles" wire:loading.remove wire:target="prepareWithAi({{ $rating->id }})"></i>
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
                                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <i class="fal fa-play" wire:loading.remove wire:target="executeNow({{ $rating->id }})"></i>
                                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="executeNow({{ $rating->id }})"></i>
                                            <span wire:loading.remove wire:target="executeNow({{ $rating->id }})">Ausfuehren</span>
                                            <span wire:loading wire:target="executeNow({{ $rating->id }})">Startet...</span>
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-14 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center gap-2 text-sm text-slate-500">
                                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
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
