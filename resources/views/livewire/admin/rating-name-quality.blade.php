<div class="rating-name-tool" x-data
    x-on:click.outside="if ($wire.nameQualityOpen) $wire.toggleNameQuality()"
    x-on:keydown.escape.stop="if ($wire.nameQualityOpen) { $wire.toggleNameQuality(); $refs.trigger.focus() }">
    <button type="button" wire:click="toggleNameQuality" wire:loading.attr="disabled" wire:target="toggleNameQuality"
        x-ref="trigger" class="rating-button" aria-expanded="{{ $nameQualityOpen ? 'true' : 'false' }}" aria-controls="rating-name-panel-{{ $this->getId() }}">
        <i class="fal fa-user-check" aria-hidden="true"></i>Namen
        @if($filterNameQuality !== '')<span class="rating-active-dot" aria-label="Namensfilter aktiv"></span>@endif
        <i class="fal fa-angle-down" aria-hidden="true"></i>
    </button>
    @if($nameQualityOpen)
        <section id="rating-name-panel-{{ $this->getId() }}" class="rating-popover rating-name-panel" aria-label="Benutzernamen prüfen">
            <div class="rating-popover-heading"><div><strong>Benutzernamen prüfen</strong><p class="rating-meta mt-1">Ähnliche Namen finden und direkt in der Bewertung bearbeiten.</p></div><button type="button" wire:click="toggleNameQuality" class="rating-row-action" aria-label="Namensprüfung schließen"><i class="fal fa-times" aria-hidden="true"></i></button></div>
            <label class="rating-field">Namen eingrenzen
                <select wire:model.live="filterNameQuality">
                    <option value="">Alle Namen</option>
                    @foreach($nameQualityOptions as $value => $label)
                        @if($value !== '')<option value="{{ $value }}">{{ $label }}</option>@endif
                    @endforeach
                </select>
            </label>
            @if($nameQualityReport)
                <div class="rating-quality-summary">
                    @foreach(['duplicate_ids' => 'exakt gleich', 'core_ids' => 'gleiche Basis', 'similar_ids' => 'ähnlich geschrieben'] as $key => $label)
                        <div><strong>{{ count($nameQualityReport[$key]) }}</strong><span>{{ $label }}</span></div>
                    @endforeach
                </div>
                <p class="rating-meta leading-relaxed">{{ number_format($nameQualityReport['total'], 0, ',', '.') }} lokale Benutzer geprüft · {{ $nameQualityReport['unknown'] }} Basen nicht zugeordnet.</p>
                <p class="rating-meta mt-1 leading-relaxed">Ähnliche Schreibweisen: letzte 1.000 Benutzer. Wortfamilien: letzte 200. Hinweise sind keine bestätigten Dubletten.</p>
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2"><span class="rating-meta">Stand {{ \Carbon\Carbon::parse($nameQualityReport['generated_at'])->format('d.m. H:i') }}</span><button type="button" wire:click="refreshQuality" wire:loading.attr="disabled" class="rating-text-button"><i class="fal fa-sync-alt" aria-hidden="true"></i> Neu prüfen</button></div>
            @endif
            <details class="rating-preview-details" wire:ignore.self>
                <summary>Generator-Vorschau <span class="rating-meta">1.000 Namen vergleichen</span></summary>
                <div class="space-y-3 pt-3" @if($previewRunning) wire:poll.1500ms="advancePreview" @endif>
                    <p class="rating-meta leading-relaxed">Vergleicht den bisherigen und neuen Generator mit denselben Testpersonen. Es werden keine Benutzer angelegt oder Namen reserviert.</p>
                    <div class="flex gap-2">
                        <button type="button" wire:click="startPreview" wire:loading.attr="disabled" @disabled($previewRunning) class="rating-button">Vorschau starten</button>
                        @if($previewRunning)<button type="button" wire:click="cancelPreview" class="rating-text-button">Anhalten</button>@endif
                    </div>
                    @error('preview')<p role="alert" class="text-sm text-rose-700">{{ $message }}</p>@enderror
                    @if($preview)
                        <div role="status" aria-live="polite">
                            <p class="rating-meta">{{ $previewRunning ? 'Vorschau läuft' : 'Vorschau angehalten oder abgeschlossen' }} · {{ $preview['generated'] ?? 0 }} von 1.000 Namen</p>
                            <progress class="mt-2 h-1.5 w-full accent-blue-600" max="1000" value="{{ ($preview['generated'] ?? 0) + ($preview['failed'] ?? 0) }}" aria-label="Fortschritt der Vorschau"></progress>
                        </div>
                        @if(isset($preview['distinct_cores']))
                            <table class="rating-preview-table">
                                <caption class="sr-only">Vergleich der unterschiedlichen Wortbasen</caption>
                                <thead><tr><th>Generator</th><th>Namen</th><th>Wortbasen</th></tr></thead>
                                <tbody><tr><td>Bisher</td><td>{{ $preview['legacy_generated'] ?? 0 }}</td><td>{{ $preview['legacy_distinct_cores'] ?? 0 }}</td></tr><tr><td>Neu</td><td>{{ $preview['generated'] }}</td><td>{{ $preview['distinct_cores'] }}</td></tr></tbody>
                            </table>
                            <p class="rating-meta">{{ $preview['base_repeats'] }} Basiswiederholungen · {{ $preview['failed'] }} Fehlversuche · {{ $preview['elapsed'] }} s. Zufallsstichprobe.</p>
                            <div class="flex flex-wrap gap-1">@foreach(array_slice($preview['samples'] ?? [], 0, 8) as $sample)<span class="rating-sample">{{ $sample }}</span>@endforeach</div>
                        @endif
                    @endif
                </div>
            </details>
        </section>
    @endif
</div>
