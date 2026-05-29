<div class="space-y-6" wire:loading.class="cursor-wait">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Bewertungs-Einstellungen</h1>
            <p class="mt-1 text-sm text-slate-600">Interne Steuerung fuer synthetische Bewertungsdaten.</p>
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

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
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

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h2 class="font-semibold text-slate-900">Verteilung nach Art und Unterart</h2>
                </div>

                <div class="divide-y divide-slate-100">
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
                                    <summary class="cursor-pointer text-sm font-medium text-blue-700">Unterarten</summary>
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
            </div>
        </section>

        <aside class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <h2 class="font-semibold text-slate-900">Aktuelle Gewichtung</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Tagesziel</dt>
                        <dd class="font-medium text-slate-900">{{ $dailyTarget }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Art-Gewichte</dt>
                        <dd class="font-medium text-slate-900">{{ number_format($this->typeWeightTotal, 1, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500">Unterart-Gewichte</dt>
                        <dd class="font-medium text-slate-900">{{ number_format($this->subtypeWeightTotal, 1, ',', '.') }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <div class="font-medium">Interner Modus</div>
                <p class="mt-1">Diese Werte steuern nur gespeicherte interne Testdaten. Eine Veroeffentlichung ist hier nicht angebunden.</p>
            </div>
        </aside>
    </div>
</div>
