<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900">Admin-Uebersicht</h1>
        <p class="mt-1 text-sm text-slate-600">Getrennter Adminbereich fuer interne Verwaltung.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Bewertungen gesamt</p>
            <p class="mt-2 text-2xl font-semibold">{{ $totalRatings }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-sm text-slate-500">Mit Base-ID</p>
            <p class="mt-2 text-2xl font-semibold">{{ $linkedBaseRatings }}</p>
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
