<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Admin-Uebersicht</h1>
        <p class="mt-1 text-sm text-slate-600">Getrennter Adminbereich fuer interne Verwaltung.</p>
    </div>

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

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Bewertungen gesamt</p>
            <p class="mt-2 text-2xl font-semibold">{{ $totalRatings }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Mit Base-ID</p>
            <p class="mt-2 text-2xl font-semibold">{{ $linkedBaseRatings }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Mit Base-User</p>
            <p class="mt-2 text-2xl font-semibold">{{ $linkedBaseUsers }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Sichtbar markiert</p>
            <p class="mt-2 text-2xl font-semibold">{{ $publicRatings }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Ausstehend</p>
            <p class="mt-2 text-2xl font-semibold">{{ $pendingRatings }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-rose-200 bg-rose-50 p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="font-semibold text-rose-950">Alle synthetischen Base-Testdaten zurueckrufen</h2>
                <p class="mt-1 text-sm leading-6 text-rose-800">
                    Entfernt nur von 2261-better markierte synthetische Bewertungen und verwaiste Testnutzer aus der RegulierungsCheck-Datenbank.
                </p>
            </div>

            <button
                type="button"
                wire:click="retractAllSyntheticBaseData"
                wire:confirm="Alle synthetischen 2261-better Bewertungen und Testnutzer aus der Base-Datenbank entfernen?"
                wire:loading.attr="disabled"
                wire:target="retractAllSyntheticBaseData"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-rose-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-rose-600 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <i class="fal fa-rotate-left" wire:loading.remove wire:target="retractAllSyntheticBaseData"></i>
                <i class="fal fa-spinner fa-spin" wire:loading wire:target="retractAllSyntheticBaseData"></i>
                <span wire:loading.remove wire:target="retractAllSyntheticBaseData">Alles zurueckrufen</span>
                <span wire:loading wire:target="retractAllSyntheticBaseData">Entfernt...</span>
            </button>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <a href="{{ route('admin.config') }}" class="rounded-xl border border-slate-200 bg-white p-4 hover:border-slate-400">
            <h2 class="font-semibold">Einstellungen</h2>
            <p class="mt-1 text-sm text-slate-600">Admin-Einstellungen verwalten.</p>
        </a>
        <a href="{{ route('admin.employees') }}" class="rounded-xl border border-slate-200 bg-white p-4 hover:border-slate-400">
            <h2 class="font-semibold">Mitarbeiter</h2>
            <p class="mt-1 text-sm text-slate-600">Staff- und Admin-Konten verwalten.</p>
        </a>
        <a href="{{ route('admin.reviews') }}" class="rounded-xl border border-slate-200 bg-white p-4 hover:border-slate-400">
            <h2 class="font-semibold">Bewertungen</h2>
            <p class="mt-1 text-sm text-slate-600">Gespeicherte Bewertungsdaten aus dem Adminpanel pruefen.</p>
        </a>
    </div>
</div>
