<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Formular Script Tests</h1>
            <p class="mt-1 text-sm text-slate-600">Node-Scripts aus <span class="font-mono">resources/node</span> pruefen und gezielt ausfuehren.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="runEnvironmentCheck"
                wire:loading.attr="disabled"
                wire:target="runEnvironmentCheck"
                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Umgebung pruefen
            </button>
            <button
                type="button"
                wire:click="prepareScreenshotRun"
                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                Screenshot vorbereiten
            </button>
            <button
                type="button"
                wire:click="showHelp"
                wire:loading.attr="disabled"
                wire:target="showHelp"
                class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Hilfe anzeigen
            </button>
            <button
                type="button"
                wire:click="runScript"
                wire:loading.attr="disabled"
                wire:target="runScript"
                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="runScript">Script starten</span>
                <span wire:loading wire:target="runScript">Laeuft...</span>
            </button>
        </div>
    </div>

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

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <section class="space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label for="node-binary" class="block text-sm font-medium text-slate-700">Node Binary</label>
                        <input
                            id="node-binary"
                            type="text"
                            wire:model.defer="nodeBinary"
                            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="node"
                        >
                    </div>

                    <div>
                        <label for="npm-binary" class="block text-sm font-medium text-slate-700">NPM Binary</label>
                        <input
                            id="npm-binary"
                            type="text"
                            wire:model.defer="npmBinary"
                            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="npm.cmd"
                        >
                    </div>

                    <div>
                        <label for="timeout-seconds" class="block text-sm font-medium text-slate-700">Timeout Sekunden</label>
                        <input
                            id="timeout-seconds"
                            type="number"
                            min="5"
                            max="300"
                            step="1"
                            wire:model.defer="timeoutSeconds"
                            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                    </div>

                    <div>
                        <label for="script-path" class="block text-sm font-medium text-slate-700">Script</label>
                        <select
                            id="script-path"
                            wire:model.defer="scriptPath"
                            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            @forelse($scripts as $script)
                                <option value="{{ $script['path'] }}">{{ $script['label'] }}</option>
                            @empty
                                <option value="">Kein Script unter resources/node gefunden</option>
                            @endforelse
                        </select>
                    </div>

                    <div class="lg:col-span-2">
                        <label for="target-url" class="block text-sm font-medium text-slate-700">Ziel-URL</label>
                        <input
                            id="target-url"
                            type="url"
                            wire:model.defer="targetUrl"
                            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="http://127.0.0.1:8000"
                        >
                    </div>

                    <label for="append-target-url" class="flex items-start gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 lg:col-span-2">
                        <input
                            id="append-target-url"
                            type="checkbox"
                            wire:model.defer="appendTargetUrl"
                            class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        >
                        <span>
                            <span class="block text-sm font-medium text-slate-900">Ziel-URL als <span class="font-mono">--url</span> uebergeben</span>
                            <span class="mt-1 block text-xs text-slate-500">Fuer den Screenshot-Lauf aktiv lassen. <span class="font-mono">--help</span> erzeugt nur die Hilfe-Ausgabe und kein PNG.</span>
                        </span>
                    </label>

                    <div class="lg:col-span-2">
                        <label for="script-arguments" class="block text-sm font-medium text-slate-700">Argumente</label>
                        <textarea
                            id="script-arguments"
                            rows="3"
                            wire:model.defer="arguments"
                            class="mt-2 block w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Leer lassen fuer Screenshot. --help zeigt nur die Hilfe."
                        ></textarea>
                        <p class="mt-2 text-xs text-slate-500">
                            Fuer eine PNG-Vorschau darf hier kein <span class="font-mono">--help</span> stehen.
                        </p>
                    </div>
                </div>
            </div>

            @if($lastRun)
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <h2 class="font-semibold text-slate-900">Letzter Lauf</h2>
                            <p class="mt-1 text-xs text-slate-500">{{ $lastRun['ran_at'] ?? '-' }} · {{ $lastRun['duration_ms'] ?? 0 }} ms</p>
                        </div>
                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium {{ ($lastRun['ok'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            Exit {{ $lastRun['exit_code'] ?? 'n/a' }}
                        </span>
                    </div>

                    <div class="space-y-4 p-4">
                        <div>
                            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Command</div>
                            <pre class="overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ $lastRun['command'] ?? '' }}</pre>
                        </div>

                        @if(! empty($lastRun['preview_image_url']))
                            <div>
                                <div class="mb-2 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">PNG Vorschau</div>
                                    <a
                                        href="{{ $lastRun['preview_image_url'] }}"
                                        target="_blank"
                                        class="text-xs font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        In neuem Tab oeffnen
                                    </a>
                                </div>

                                <div class="overflow-hidden rounded-md border border-slate-200 bg-slate-100">
                                    <img
                                        src="{{ $lastRun['preview_image_url'] }}"
                                        alt="Erzeugter Screenshot"
                                        class="max-h-[640px] w-full object-contain"
                                    >
                                </div>

                                <div class="mt-2 break-all font-mono text-xs text-slate-500">
                                    {{ $lastRun['preview_image_path'] ?? '' }}
                                </div>
                            </div>
                        @endif

                        <div>
                            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">STDOUT</div>
                            <pre class="max-h-96 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ $lastRun['stdout'] ?: 'Keine Ausgabe.' }}</pre>
                        </div>

                        <div>
                            <div class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">STDERR</div>
                            <pre class="max-h-72 overflow-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ $lastRun['stderr'] ?: 'Keine Ausgabe.' }}</pre>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        <aside class="space-y-5">
            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-slate-900">Umgebung</h2>
                    <button type="button" wire:click="clearOutput" class="text-sm font-medium text-slate-500 hover:text-slate-900">
                        Leeren
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($environmentChecks as $check)
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="text-sm font-medium text-slate-900">{{ $check['label'] }}</div>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ ($check['ok'] ?? false) ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                    {{ ($check['ok'] ?? false) ? 'OK' : 'Fehler' }}
                                </span>
                            </div>
                            @if(! empty($check['output']))
                                <pre class="mt-2 max-h-36 overflow-auto whitespace-pre-wrap text-xs text-slate-600">{{ $check['output'] }}</pre>
                            @else
                                <p class="mt-2 text-xs text-slate-500">{{ $check['message'] ?? '' }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Noch kein Umgebungstest ausgefuehrt.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                <div class="font-medium">Interner Testbereich</div>
                <p class="mt-1">Scripts werden nur lokal auf dem Server ausgefuehrt. Die Ziel-URL und Argumente muessen zum jeweiligen Node-Script passen.</p>
            </div>
        </aside>
    </div>
</div>
