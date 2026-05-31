@php
    $executionRate = $totalRatings > 0 ? round(($executedRatings / $totalRatings) * 100) : 0;
    $baseSyncRate = $totalRatings > 0 ? round(($linkedBaseRatings / $totalRatings) * 100) : 0;
    $errorRate = $totalRatings > 0 ? round(($failedRatings / $totalRatings) * 100) : 0;
    $averageScoreLabel = $averageScore !== null ? number_format($averageScore, 2, ',', '.') : '-';
@endphp

<div class="space-y-6">
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div class="inline-flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700">
                        <i class="fal fa-chart-line"></i>
                        Admin Dashboard
                    </div>
                    <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">Bewertungs-Steuerung</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                        Status der geplanten Demo-Bewertungen, Base-Zuordnung, AI-Vorbereitung und Rueckruf-Faehigkeit.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.planned-reviews') }}" class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                        <i class="fal fa-calendar-alt"></i>
                        Planung
                    </a>
                    <a href="{{ route('admin.config') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <i class="fal fa-cog"></i>
                        Einstellungen
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach($statusCards as $card)
                    @php
                        $tone = $card['tone'] ?? 'slate';
                        $toneClass = match ($tone) {
                            'blue' => 'border-blue-200 bg-blue-50 text-blue-700',
                            'violet' => 'border-violet-200 bg-violet-50 text-violet-700',
                            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            'rose' => 'border-rose-200 bg-rose-50 text-rose-700',
                            default => 'border-slate-200 bg-slate-50 text-slate-700',
                        };
                    @endphp
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $card['label'] }}</p>
                                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format((int) $card['value'], 0, ',', '.') }}</p>
                            </div>
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border {{ $toneClass }}">
                                <i class="fal {{ $card['icon'] }}"></i>
                            </div>
                        </div>
                        <p class="mt-3 min-h-9 text-xs leading-5 text-slate-500">{{ $card['detail'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-slate-950">Systemstatus</h2>
                    <p class="mt-1 text-sm text-slate-500">Konfiguration und Analyse</p>
                </div>
                <a href="{{ route('admin.config') }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    Pruefen
                </a>
            </div>

            <div class="mt-4 space-y-3">
                @foreach($configChecks as $check)
                    <div class="flex items-start gap-3 rounded-md border border-slate-200 bg-slate-50 px-3 py-3">
                        <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md {{ $check['ok'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            <i class="fal {{ $check['ok'] ? 'fa-check' : 'fa-exclamation' }} text-xs"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <p class="text-sm font-semibold text-slate-900">{{ $check['label'] }}</p>
                                <span class="rounded-md bg-white px-2 py-0.5 text-xs text-slate-600">{{ $check['value'] }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $check['detail'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-600">Ausfuehrungsquote</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $executionRate }}%</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    {{ number_format($executedRatings, 0, ',', '.') }} / {{ number_format($totalRatings, 0, ',', '.') }}
                </div>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, max(0, $executionRate)) }}%"></div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-600">Base-Sync</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $baseSyncRate }}%</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    {{ number_format($linkedBaseRatings, 0, ',', '.') }} Bewertungen<br>
                    {{ number_format($linkedBaseUsers, 0, ',', '.') }} Benutzer
                </div>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-blue-500" style="width: {{ min(100, max(0, $baseSyncRate)) }}%"></div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-slate-600">Score / Fehler</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $averageScoreLabel }}</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    {{ number_format($failedRatings, 0, ',', '.') }} Fehler<br>
                    {{ $errorRate }}% Fehlerrate
                </div>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $failedRatings > 0 ? 'bg-rose-500' : 'bg-slate-300' }}" style="width: {{ min(100, max(0, $errorRate)) }}%"></div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="rounded-lg border border-slate-200 bg-white">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">Letzte Aktivitaet</h2>
                    <p class="mt-1 text-sm text-slate-500">Aktualisierte Bewertungen aus Planung und Ausfuehrung</p>
                </div>
                <a href="{{ route('admin.reviews') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Alle Bewertungen
                    <i class="fal fa-arrow-right text-xs"></i>
                </a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($recentRatings as $rating)
                    <a href="{{ route('admin.reviews') }}?search={{ $rating['id'] }}" class="grid gap-3 px-5 py-4 hover:bg-slate-50 lg:grid-cols-[minmax(0,1fr)_150px_120px] lg:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-950">#{{ $rating['id'] }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">{{ $rating['execution_state'] }}</span>
                                @if($rating['has_error'])
                                    <span class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700">Fehler</span>
                                @endif
                            </div>
                            <p class="mt-1 truncate text-sm text-slate-600">
                                {{ $rating['insurance_name'] ?: 'Versicherung offen' }}
                                @if($rating['type_name'] || $rating['subtype_name'])
                                    <span class="text-slate-400">/</span>
                                    {{ $rating['type_name'] ?: '-' }} · {{ $rating['subtype_name'] ?: '-' }}
                                @endif
                            </p>
                            <p class="mt-1 truncate text-xs text-slate-500">
                                {{ $rating['user_name'] ?: 'Ohne lokalen Demo-Benutzer' }}
                                @if($rating['base_claim_rating_id'])
                                    · Base #{{ $rating['base_claim_rating_id'] }}
                                @endif
                            </p>
                        </div>

                        <div class="text-sm text-slate-600">
                            <div class="font-medium text-slate-900">{{ $rating['executed_at'] ?: ($rating['scheduled_for'] ?: '-') }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $rating['executed_at'] ? 'Ausgefuehrt' : 'Geplant' }}</div>
                        </div>

                        <div class="text-left lg:text-right">
                            <div class="text-sm font-semibold text-slate-900">{{ $rating['score'] ?: '-' }}</div>
                            <div class="mt-1 text-xs text-slate-500">Score</div>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500">
                        Noch keine Aktivitaet vorhanden.
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Naechste Ausfuehrungen</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ number_format($upcomingRatings, 0, ',', '.') }} kommende Eintraege</p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($upcomingList as $rating)
                        <a href="{{ route('admin.planned-reviews') }}?search={{ $rating['id'] }}" class="block px-5 py-4 hover:bg-slate-50">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-950">#{{ $rating['id'] }}</p>
                                    <p class="mt-1 truncate text-sm text-slate-600">{{ $rating['insurance_name'] ?: 'Versicherung offen' }}</p>
                                </div>
                                <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">{{ $rating['scheduled_for'] ?: '-' }}</span>
                            </div>
                            <p class="mt-2 truncate text-xs text-slate-500">{{ $rating['type_name'] ?: '-' }} · {{ $rating['subtype_name'] ?: '-' }}</p>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-sm text-slate-500">Keine kommenden Ausfuehrungen.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-rose-200 bg-rose-50 p-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white text-rose-700">
                        <i class="fal fa-rotate-left"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-semibold text-rose-950">Base-Testdaten zurueckrufen</h2>
                        <p class="mt-1 text-sm leading-6 text-rose-800">
                            Entfernt nur eindeutig markierte 2261-better Demo-Datensaetze aus der RegulierungsCheck-Datenbank.
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="retractAllSyntheticBaseData"
                    wire:confirm="Alle synthetischen 2261-better Bewertungen und Testnutzer aus der Base-Datenbank entfernen?"
                    wire:loading.attr="disabled"
                    wire:target="retractAllSyntheticBaseData"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-rose-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <i class="fal fa-rotate-left" wire:loading.remove wire:target="retractAllSyntheticBaseData"></i>
                    <i class="fal fa-spinner fa-spin" wire:loading wire:target="retractAllSyntheticBaseData"></i>
                    <span wire:loading.remove wire:target="retractAllSyntheticBaseData">Alles zurueckrufen</span>
                    <span wire:loading wire:target="retractAllSyntheticBaseData">Entfernt...</span>
                </button>
            </section>
        </aside>
    </div>
</div>
