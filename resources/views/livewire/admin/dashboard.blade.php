@php
    $executionRate = $totalRatings > 0 ? round(($executedRatings / $totalRatings) * 100) : 0;
    $baseSyncRate = $totalRatings > 0 ? round(($linkedBaseRatings / $totalRatings) * 100) : 0;
    $errorRate = $totalRatings > 0 ? round(($failedRatings / $totalRatings) * 100) : 0;
    $averageScoreLabel = $averageScore !== null ? number_format($averageScore, 2, ',', '.') : '-';
    $nextTargetIso = $nextScheduledAtIso;
@endphp

<div class="space-y-5" wire:poll.60s="loadStats">
    @if (session('success'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="grid gap-0 xl:grid-cols-[minmax(0,1fr)_420px]">
            <div class="p-5 lg:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                <i class="fal fa-gauge-high"></i>
                                Admin Dashboard
                            </span>
                            @if($dueRatings > 0)
                                <span class="inline-flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    <i class="fal fa-bolt"></i>
                                    {{ number_format($dueRatings, 0, ',', '.') }} faellig
                                </span>
                            @endif
                        </div>

                        <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">Bewertungs-Steuerung</h1>
                        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">
                            Ueberblick ueber Planung, AI-Vorbereitung, Base-Sync, Ausfuehrungen und Rueckruf der eindeutig markierten Testdaten.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
                            wire:click="retractAllSyntheticBaseData"
                            wire:confirm="Alle synthetischen 2261-better Bewertungen und Testnutzer aus der Base-Datenbank entfernen?"
                            wire:loading.attr="disabled"
                            wire:target="retractAllSyntheticBaseData"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-rose-700 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <i class="fal fa-rotate-left" wire:loading.remove wire:target="retractAllSyntheticBaseData"></i>
                            <i class="fal fa-spinner fa-spin" wire:loading wire:target="retractAllSyntheticBaseData"></i>
                            <span wire:loading.remove wire:target="retractAllSyntheticBaseData">Alle zurueckrufen</span>
                            <span wire:loading wire:target="retractAllSyntheticBaseData">Rueckruf laeuft...</span>
                        </button>

                        <a href="{{ route('admin.planned-reviews') }}" class="inline-flex items-center gap-2 rounded-md bg-slate-950 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            <i class="fal fa-calendar-alt"></i>
                            Planung
                        </a>
                        <a href="{{ route('admin.config') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <i class="fal fa-cog"></i>
                            Einstellungen
                        </a>
                    </div>
                </div>

                <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
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
                        <div class="rounded-md border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] font-semibold uppercase text-slate-500">{{ $card['label'] }}</p>
                                    <p class="mt-1 text-xl font-semibold leading-6 text-slate-950">{{ number_format((int) $card['value'], 0, ',', '.') }}</p>
                                </div>
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border text-xs {{ $toneClass }}">
                                    <i class="fal {{ $card['icon'] }}"></i>
                                </div>
                            </div>
                            <p class="mt-1 truncate text-[11px] leading-4 text-slate-500">{{ $card['detail'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <aside class="border-t border-slate-200 bg-slate-950 p-5 text-white xl:border-l xl:border-t-0 lg:p-6">
                <div
                    x-data="{
                        target: @js($nextTargetIso),
                        label: 'Keine Planung',
                        state: 'idle',
                        tick() {
                            if (!this.target) {
                                this.label = 'Keine Planung';
                                this.state = 'idle';
                                return;
                            }

                            const remaining = new Date(this.target).getTime() - Date.now();

                            if (remaining <= 0) {
                                this.label = 'Jetzt faellig';
                                this.state = 'due';
                                return;
                            }

                            const seconds = Math.floor(remaining / 1000);
                            const days = Math.floor(seconds / 86400);
                            const hours = Math.floor((seconds % 86400) / 3600);
                            const minutes = Math.floor((seconds % 3600) / 60);
                            const secs = seconds % 60;
                            const pad = (value) => String(value).padStart(2, '0');

                            this.label = days > 0
                                ? `${days}d ${pad(hours)}:${pad(minutes)}:${pad(secs)}`
                                : `${pad(hours)}:${pad(minutes)}:${pad(secs)}`;
                            this.state = 'running';
                        }
                    }"
                    x-init="tick(); setInterval(() => tick(), 1000)"
                    class="flex h-full flex-col justify-between gap-6"
                >
                    <div>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase text-slate-400">Naechste Bewertung</p>
                                <h2 class="mt-3 text-4xl font-semibold tracking-tight" x-text="label"></h2>
                            </div>
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-md bg-white/10 text-blue-200">
                                <i class="fal fa-stopwatch"></i>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-md border border-white/10 bg-white/5 p-3">
                                <p class="text-xs text-slate-400">Termin</p>
                                <p class="mt-1 text-sm font-semibold">{{ $nextScheduledFor ?: 'Nicht geplant' }}</p>
                            </div>
                            <div class="rounded-md border border-white/10 bg-white/5 p-3">
                                <p class="text-xs text-slate-400">Warteschlange</p>
                                <p class="mt-1 text-sm font-semibold">{{ number_format($upcomingRatings, 0, ',', '.') }} offen</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-md border border-white/10 bg-white/5 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold">Base-Sync</span>
                            <span class="text-sm text-slate-300">{{ $baseSyncRate }}%</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-white/10">
                            <div class="h-full rounded-full bg-blue-400" style="width: {{ min(100, max(0, $baseSyncRate)) }}%"></div>
                        </div>
                        <p class="mt-3 text-xs leading-5 text-slate-400">
                            {{ number_format($linkedBaseRatings, 0, ',', '.') }} Bewertungen und {{ number_format($linkedBaseUsers, 0, ',', '.') }} Testnutzer sind aktuell mit der Base verknuepft.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <section class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-600">Ausfuehrungsquote</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $executionRate }}%</p>
                </div>
                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    {{ number_format($executedRatings, 0, ',', '.') }} / {{ number_format($totalRatings, 0, ',', '.') }}
                </span>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-emerald-500" style="width: {{ min(100, max(0, $executionRate)) }}%"></div>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-600">Durchschnittsscore</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $averageScoreLabel }}</p>
                </div>
                <span class="rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">AI Eval</span>
            </div>
            <p class="mt-4 text-xs leading-5 text-slate-500">Berechnet aus allen lokal erzeugten Bewertungen mit vorhandenem Score.</p>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-slate-600">Fehlerrate</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-950">{{ $errorRate }}%</p>
                </div>
                <span class="rounded-md {{ $failedRatings > 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-600' }} px-2.5 py-1 text-xs font-semibold">
                    {{ number_format($failedRatings, 0, ',', '.') }} Fehler
                </span>
            </div>
            <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full {{ $failedRatings > 0 ? 'bg-rose-500' : 'bg-slate-300' }}" style="width: {{ min(100, max(0, $errorRate)) }}%"></div>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
        <section class="rounded-lg border border-slate-200 bg-white">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-950">Letzte Aktivitaet</h2>
                    <p class="mt-1 text-sm text-slate-500">Aktualisierte Bewertungen aus Planung und Ausfuehrung</p>
                </div>
                <a href="{{ route('admin.reviews') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Alle Bewertungen
                    <i class="fal fa-arrow-right text-xs"></i>
                </a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($recentRatings as $rating)
                    <a href="{{ route('admin.reviews') }}?search={{ $rating['id'] }}" class="grid gap-3 px-5 py-4 hover:bg-slate-50 lg:grid-cols-[minmax(0,1fr)_160px_110px] lg:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-slate-950">#{{ $rating['id'] }}</span>
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $rating['execution_state'] }}</span>
                                @if($rating['has_error'])
                                    <span class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">Fehler</span>
                                @endif
                            </div>
                            <p class="mt-1 truncate text-sm text-slate-700">
                                {{ $rating['insurance_name'] ?: 'Versicherung offen' }}
                            </p>
                            <p class="mt-1 truncate text-xs text-slate-500">
                                {{ $rating['type_name'] ?: '-' }} / {{ $rating['subtype_name'] ?: '-' }}
                            </p>
                            <p class="mt-1 truncate text-xs text-slate-500">
                                {{ $rating['user_name'] ?: 'Ohne lokalen Demo-Benutzer' }}
                                @if($rating['base_claim_rating_id'])
                                    · Base #{{ $rating['base_claim_rating_id'] }}
                                @endif
                            </p>
                        </div>

                        <div class="text-sm text-slate-600">
                            <div class="font-semibold text-slate-900">{{ $rating['executed_at'] ?: ($rating['scheduled_for'] ?: '-') }}</div>
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
                                    <p class="mt-1 truncate text-sm text-slate-700">{{ $rating['insurance_name'] ?: 'Versicherung offen' }}</p>
                                </div>
                                <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $rating['scheduled_for'] ?: '-' }}</span>
                            </div>
                            <p class="mt-2 truncate text-xs text-slate-500">{{ $rating['type_name'] ?: '-' }} / {{ $rating['subtype_name'] ?: '-' }}</p>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-sm text-slate-500">Keine kommenden Ausfuehrungen.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-950">Systemstatus</h2>
                    <p class="mt-1 text-sm text-slate-500">Konfiguration und Analyse</p>
                </div>

                <div class="space-y-3 p-5">
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
        </aside>
    </div>
</div>
