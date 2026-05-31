<div class="space-y-6" wire:loading.class="cursor-wait">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Einstellungen</h1>
            <p class="mt-1 text-sm text-slate-600">Bewertungsverteilung, Formular-Ausfuellung, OpenRouter und RegulierungsCheck-Datenbank.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="resetDistribution" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Verteilung zuruecksetzen
            </button>
            <button type="button" wire:click="save" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                Speichern
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            <div class="font-medium">Bitte pruefen:</div>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div x-data="{ activeTab: 'rating' }" class="space-y-6">
        <div class="overflow-x-auto border-b border-slate-200">
            <nav class="flex min-w-max gap-2 pb-3" aria-label="Tabs">
                <button
                    type="button"
                    @click="activeTab = 'rating'"
                    :class="activeTab === 'rating' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    class="rounded-md border px-3 py-2 text-sm font-medium transition-colors"
                >
                    Bewertungs-Einstellungen
                </button>
                <button
                    type="button"
                    @click="activeTab = 'form_fill'"
                    :class="activeTab === 'form_fill' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    class="rounded-md border px-3 py-2 text-sm font-medium transition-colors"
                >
                    Formular-Ausfuellung
                </button>
                <button
                    type="button"
                    @click="activeTab = 'openrouter'"
                    :class="activeTab === 'openrouter' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    class="rounded-md border px-3 py-2 text-sm font-medium transition-colors"
                >
                    OpenRouter API
                </button>
                <button
                    type="button"
                    @click="activeTab = 'database'"
                    :class="activeTab === 'database' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                    class="rounded-md border px-3 py-2 text-sm font-medium transition-colors"
                >
                    RegulierungsCheck-Datenbank
                </button>
            </nav>
        </div>

        <div x-show.important="activeTab === 'rating'" x-data="{ ratingSection: 'overview' }" class="grid gap-5 lg:grid-cols-[240px_minmax(0,1fr)]">
            <aside class="lg:sticky lg:top-4 lg:self-start">
                <nav class="space-y-2 rounded-lg border border-slate-200 bg-white p-2" aria-label="Bewertungsbereiche">
                    <button
                        type="button"
                        @click="ratingSection = 'overview'"
                        :class="ratingSection === 'overview' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium transition-colors"
                    >
                        <span>Uebersicht</span>
                        <span class="text-xs opacity-75">{{ $dailyTarget }}/Tag</span>
                    </button>
                    <button
                        type="button"
                        @click="ratingSection = 'analysis'"
                        :class="ratingSection === 'analysis' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium transition-colors"
                    >
                        <span>Analyse</span>
                        <span class="text-xs opacity-75">{{ $lastAnalysis ? 'bereit' : 'leer' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="ratingSection = 'hours'"
                        :class="ratingSection === 'hours' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium transition-colors"
                    >
                        <span>Uhrzeiten</span>
                        <span class="text-xs opacity-75">24h</span>
                    </button>
                    <button
                        type="button"
                        @click="ratingSection = 'scores'"
                        :class="ratingSection === 'scores' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium transition-colors"
                    >
                        <span>Scorings</span>
                        <span class="text-xs opacity-75">{{ number_format($this->scoreWeightTotal, 0, ',', '.') }}</span>
                    </button>
                    <button
                        type="button"
                        @click="ratingSection = 'types'"
                        :class="ratingSection === 'types' ? 'bg-slate-900 text-white' : 'text-slate-700 hover:bg-slate-100'"
                        class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm font-medium transition-colors"
                    >
                        <span>Arten</span>
                        <span class="text-xs opacity-75">{{ count($catalog) }}</span>
                    </button>
                </nav>

                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    <div class="font-medium">Interner Modus</div>
                    <p class="mt-1">Diese Werte steuern nur gespeicherte interne Testdaten. Eine Veroeffentlichung ist hier nicht angebunden.</p>
                </div>
            </aside>

            <main class="min-w-0 space-y-5">
                <section x-show.important="ratingSection === 'overview'" class="space-y-5">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Tagesziel</div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $dailyTarget }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Art-Gewichte</div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($this->typeWeightTotal, 1, ',', '.') }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Unterart-Gewichte</div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($this->subtypeWeightTotal, 1, ',', '.') }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Staerkste Zeiten</div>
                            <div class="mt-2 text-sm font-semibold leading-6 text-slate-900">{{ $this->peakHours }}</div>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Score-Mix</div>
                            <div class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($this->scoreWeightTotal, 1, ',', '.') }}</div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px] lg:items-end">
                            <div>
                                <label for="daily-target" class="block text-sm font-medium text-slate-700">Anzahl taeglich</label>
                                <input
                                    id="daily-target"
                                    type="number"
                                    min="0"
                                    max="500"
                                    step="1"
                                    wire:model.defer="dailyTarget"
                                    class="mt-2 block w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                            </div>

                            <button type="button" wire:click="save" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                                Bewertungswerte speichern
                            </button>
                        </div>
                    </div>

                    @if($lastAnalysis)
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <div class="font-semibold text-emerald-900">Letzte Analyse</div>
                                    <p class="mt-1 text-sm text-emerald-700">{{ $this->analysisStats }}</p>
                                </div>
                                <button type="button" wire:click="applyAnalysisResults" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                                    Erneut anwenden
                                </button>
                            </div>
                        </div>
                    @endif
                </section>

                <section x-cloak x-show.important="ratingSection === 'analysis'" style="display: none;" class="space-y-5">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-blue-950">Reale Bewertungsverteilung analysieren</h2>
                                <div class="mt-3 grid gap-2 text-sm text-blue-800 md:grid-cols-2">
                                    <div class="rounded-md bg-white/70 px-3 py-2">Haeufigkeit pro Art und Unterart</div>
                                    <div class="rounded-md bg-white/70 px-3 py-2">Durchschnittlicher Bewertungsscore</div>
                                    <div class="rounded-md bg-white/70 px-3 py-2">Konsistenz der Scores</div>
                                    <div class="rounded-md bg-white/70 px-3 py-2">Typische Uhrzeiten bisheriger Bewertungen</div>
                                    <div class="rounded-md bg-white/70 px-3 py-2">Score-Verteilung von schlecht bis sehr gut</div>
                                </div>
                            </div>

                            <button
                                type="button"
                                wire:click="runAnalysis"
                                wire:loading.attr="disabled"
                                wire:target="runAnalysis"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="runAnalysis">Analyse starten und setzen</span>
                                <span wire:loading wire:target="runAnalysis">Analysiere...</span>
                            </button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</div>
                                <div class="mt-2 font-semibold text-slate-900">{{ $lastAnalysis ? 'Analyse vorhanden' : 'Keine Analyse' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Bewertungen</div>
                                <div class="mt-2 font-semibold text-slate-900">{{ $lastAnalysis['total_ratings'] ?? 0 }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Zeitpunkt</div>
                                <div class="mt-2 font-semibold text-slate-900">{{ $lastAnalysis ? ($this->analysisStats ?? '-') : '-' }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section x-cloak x-show.important="ratingSection === 'hours'" style="display: none;" class="space-y-5">
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="font-semibold text-slate-900">Uhrzeit-Verteilung</h2>
                                <p class="mt-1 text-sm text-slate-500">Balken und Werte steuern die Gewichtung pro Stunde.</p>
                            </div>
                            <div class="text-sm text-slate-600">
                                Summe: <span class="font-semibold text-slate-900">{{ number_format($this->hourWeightTotal, 1, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex min-w-[960px] items-end gap-2">
                            @foreach($hourWeights as $hour => $weight)
                                @php($hourStats = $lastAnalysis['hour_stats'][$hour] ?? $lastAnalysis['hour_stats'][(string) $hour] ?? null)
                                <div x-data="{ weight: @js((float) $weight) }" class="flex w-9 flex-col items-center gap-2">
                                    <div class="flex h-40 w-full items-end rounded-md bg-slate-100 p-1">
                                        <div
                                            class="w-full rounded-sm bg-blue-500 transition-all"
                                            :style="'height: ' + Math.max(4, Math.min(100, Number(weight) || 0)) + '%'"
                                        ></div>
                                    </div>

                                    <input
                                        type="range"
                                        min="0"
                                        max="100"
                                        step="1"
                                        x-model.number="weight"
                                        wire:model.live.debounce.250ms="hourWeights.{{ $hour }}"
                                        class="h-20 w-6 accent-blue-600"
                                        style="writing-mode: vertical-lr; direction: rtl;"
                                        aria-label="Gewicht {{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }} Uhr"
                                    >

                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        x-model.number="weight"
                                        wire:model.live.debounce.300ms="hourWeights.{{ $hour }}"
                                        class="w-14 rounded-md border border-slate-300 px-1.5 py-1 text-center text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    >

                                    <div class="text-[11px] font-medium text-slate-700">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}</div>

                                    @if($hourStats)
                                        <div class="text-center text-[10px] leading-tight text-slate-500">
                                            {{ $hourStats['count'] ?? 0 }}<br>
                                            {{ number_format((float) ($hourStats['percent'] ?? 0), 1, ',', '.') }}%
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section x-cloak x-show.important="ratingSection === 'scores'" style="display: none;" class="space-y-5">
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="font-semibold text-slate-900">Score-Verteilung</h2>
                                <p class="mt-1 text-sm text-slate-500">Diese Gewichte steuern, ob geplante interne Bewertungen eher schlecht, mittel oder gut ausfallen sollen.</p>
                            </div>
                            <div class="text-sm text-slate-600">
                                Summe: <span class="font-semibold text-slate-900">{{ number_format($this->scoreWeightTotal, 1, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    @php($scoreStats = is_array($lastAnalysis['score_stats']['buckets'] ?? null) ? $lastAnalysis['score_stats']['buckets'] : [])

                    <div class="grid gap-3 xl:grid-cols-5">
                        @foreach($formattedScoreBuckets as $bucketData)
                            <div class="rounded-lg border border-slate-200 bg-white p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-semibold text-slate-900">{{ $bucketData['label'] }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            {{ number_format((float) $bucketData['min'], 2, ',', '.') }} bis {{ number_format((float) $bucketData['max'], 2, ',', '.') }}
                                        </div>
                                    </div>
                                    <span class="rounded-full border px-2 py-1 text-xs font-medium {{ $bucketData['tone'] }}">
                                        {{ number_format($bucketData['percent'], 1, ',', '.') }}%
                                    </span>
                                </div>

                                <div class="mt-4">
                                    <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                                        <span>Echt: {{ $bucketData['count'] }}</span>
                                        <span>Gewicht</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-blue-600" style="width: {{ min(100, max(0, $bucketData['percent'])) }}%"></div>
                                    </div>
                                </div>

                                <input
                                    type="number"
                                    min="0"
                                    step="0.1"
                                    wire:model.defer="scoreWeights.{{ $bucketData['key'] }}"
                                    class="mt-4 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    aria-label="Score-Gewicht {{ $bucketData['label'] }}"
                                >
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                        <div class="font-semibold">Einbindung in Planung und Ausfuehrung</div>
                        <p class="mt-1">Beim Planen wird pro Bewertung ein Ziel-Score-Profil gezogen und im Datensatz gespeichert. Die AI erhaelt dieses Profil beim Vorbereiten oder Ausfuehren als Kontext.</p>
                    </div>
                </section>

                <section x-cloak x-show.important="ratingSection === 'types'" style="display: none;" class="space-y-5">
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="font-semibold text-slate-900">Verteilung nach Art und Unterart</h2>
                                <p class="mt-1 text-sm text-slate-500">Gewichte fuer bestehende Arten und Unterarten.</p>
                            </div>
                            <div class="text-sm text-slate-600">
                                Gesamt: <span class="font-semibold text-slate-900">{{ number_format($this->typeWeightTotal + $this->subtypeWeightTotal, 1, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 bg-white">
                        @foreach($catalog as $typeId => $type)
                            <div class="p-4">
                                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_160px] md:items-center">
                                    <div>
                                        <div class="font-medium text-slate-900">#{{ $typeId }} {{ $type['name'] }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ count($type['subtypes']) }} Unterarten</div>
                                    </div>

                                    <div>
                                        <label for="type-weight-{{ $typeId }}" class="block text-xs font-medium uppercase tracking-wide text-slate-500">Gewicht Art</label>
                                        <input
                                            id="type-weight-{{ $typeId }}"
                                            type="number"
                                            min="0"
                                            step="0.1"
                                            wire:model.defer="typeWeights.{{ $typeId }}"
                                            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                    </div>
                                </div>

                                @if(count($type['subtypes']) > 0)
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-sm font-medium text-blue-700">Unterarten anzeigen</summary>
                                        <div class="mt-3 grid gap-3 md:grid-cols-2">
                                            @foreach($type['subtypes'] as $subtypeId => $subtypeName)
                                                <label for="subtype-weight-{{ $typeId }}-{{ $subtypeId }}" class="grid gap-2 rounded-md border border-slate-100 bg-slate-50 p-3">
                                                    <span class="text-sm font-medium text-slate-800">#{{ $subtypeId }} {{ $subtypeName }}</span>
                                                    <input
                                                        id="subtype-weight-{{ $typeId }}-{{ $subtypeId }}"
                                                        type="number"
                                                        min="0"
                                                        step="0.1"
                                                        wire:model.defer="subtypeWeights.{{ $typeId }}.{{ $subtypeId }}"
                                                        class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    >
                                                </label>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            </main>
        </div>

        <div x-cloak x-show.important="activeTab === 'form_fill'" style="display: none;" class="space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900">Formular-Ausfuellung</h2>
                        <p class="mt-1 text-sm text-slate-500">Steuerwerte fuer spaetere interne Formular-Durchlaeufe.</p>
                    </div>
                    <button type="button" wire:click="saveFormFillSettings" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                        Formular speichern
                    </button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <label for="form-fill-enabled" class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4">
                    <input id="form-fill-enabled" type="checkbox" wire:model.defer="formFillEnabled" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Automatik aktiv</span>
                        <span class="mt-1 block text-xs text-slate-500">Aktiviert nur die internen Einstellungen.</span>
                    </span>
                </label>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="form-fill-mode" class="block text-sm font-medium text-slate-700">Betriebsmodus</label>
                    <select id="form-fill-mode" wire:model.defer="formFillMode" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="internal_review">Intern mit Pruefung</option>
                        <option value="draft_only">Nur Entwurf speichern</option>
                        <option value="disabled">Aus</option>
                    </select>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="form-fill-batch-size" class="block text-sm font-medium text-slate-700">Bewertungen pro Durchlauf</label>
                    <input id="form-fill-batch-size" type="number" min="1" max="100" step="1" wire:model.defer="formFillBatchSize" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <label for="form-fill-pause-min" class="block text-sm font-medium text-slate-700">Pause min. Sekunden</label>
                        <input id="form-fill-pause-min" type="number" min="0" max="86400" step="1" wire:model.defer="formFillPauseMinSeconds" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="rounded-lg border border-slate-200 bg-white p-4">
                        <label for="form-fill-pause-max" class="block text-sm font-medium text-slate-700">Pause max. Sekunden</label>
                        <input id="form-fill-pause-max" type="number" min="0" max="86400" step="1" wire:model.defer="formFillPauseMaxSeconds" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 lg:col-span-2">
                    <label for="form-fill-source-path" class="block text-sm font-medium text-slate-700">Startpfad Formular</label>
                    <input id="form-fill-source-path" type="text" wire:model.defer="formFillSourcePath" class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="/bewertung/start">
                </div>

                <label for="form-fill-stop-before-submit" class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4">
                    <input id="form-fill-stop-before-submit" type="checkbox" wire:model.defer="formFillStopBeforeSubmit" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Vor finalem Absenden stoppen</span>
                        <span class="mt-1 block text-xs text-slate-500">Erzeugte Daten bleiben pruefbar.</span>
                    </span>
                </label>

                <label for="form-fill-synthetic-person" class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4">
                    <input id="form-fill-synthetic-person" type="checkbox" wire:model.defer="formFillUseSyntheticPersonData" class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-900">Synthetische Personendaten verwenden</span>
                        <span class="mt-1 block text-xs text-slate-500">Keine echten Kontakt- oder Kundendaten.</span>
                    </span>
                </label>
            </div>
        </div>

        <div x-cloak x-show.important="activeTab === 'openrouter'" style="display: none;" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-4 md:col-span-2">
                    <label for="openrouter-api-url" class="block text-sm font-medium text-slate-700">API URL</label>
                    <input id="openrouter-api-url" type="url" wire:model.defer="openrouterApiUrl" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="https://openrouter.ai/api/v1/chat/completions">
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="openrouter-api-key" class="block text-sm font-medium text-slate-700">API Key</label>
                    <input id="openrouter-api-key" type="password" wire:model.defer="openrouterApiKey" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="sk-or-...">
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="openrouter-model" class="block text-sm font-medium text-slate-700">Modell</label>
                    <input id="openrouter-model" type="text" wire:model.defer="openrouterModel" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="openrouter/auto">
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 md:col-span-2">
                    <label for="openrouter-referer" class="block text-sm font-medium text-slate-700">Referer URL</label>
                    <input id="openrouter-referer" type="url" wire:model.defer="openrouterRefererUrl" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="http://localhost">
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="saveOpenRouterSettings" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                    OpenRouter speichern
                </button>
            </div>
        </div>

        <div x-cloak x-show.important="activeTab === 'database'" style="display: none;" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="db-host" class="block text-sm font-medium text-slate-700">Host</label>
                    <input id="db-host" type="text" wire:model.defer="dbHost" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="127.0.0.1">
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="db-port" class="block text-sm font-medium text-slate-700">Port</label>
                    <input id="db-port" type="number" wire:model.defer="dbPort" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="3306" min="1" max="65535">
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="db-database" class="block text-sm font-medium text-slate-700">Datenbankname</label>
                    <input id="db-database" type="text" wire:model.defer="dbDatabase" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="regulierungs-check">
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <label for="db-username" class="block text-sm font-medium text-slate-700">Benutzername</label>
                    <input id="db-username" type="text" wire:model.defer="dbUsername" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="root">
                </div>

                <div class="rounded-lg border border-slate-200 bg-white p-4 md:col-span-2">
                    <label for="db-password" class="block text-sm font-medium text-slate-700">Passwort</label>
                    <input id="db-password" type="password" wire:model.defer="dbPassword" class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="testDatabaseConnection" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Verbindung testen
                </button>
                <button type="button" wire:click="saveDatabaseSettings" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
                    Datenbank speichern
                </button>
            </div>
        </div>
    </div>
</div>
