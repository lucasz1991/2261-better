<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Bewertungen</h1>
            <p class="mt-1 text-sm text-slate-600">Internes Register fuer importierte oder geplante Bewertungsdaten.</p>
        </div>

        <button
            type="button"
            wire:click="clearFilters"
            class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Filter zuruecksetzen
        </button>
    </div>

    <div class="grid gap-3 md:grid-cols-[1fr_240px]">
        <div>
            <label for="rating-search" class="mb-1 block text-sm font-medium text-slate-700">Suche</label>
            <input
                id="rating-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="ID, Base-ID, Versicherungs-ID, Status oder Kommentar"
                class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="rating-status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
            <select
                id="rating-status"
                wire:model.live="status"
                class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
                <option value="">Alle Status</option>
                @foreach($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="overflow-hidden rounded-md border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('id')" class="font-semibold">ID</button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('base_claim_rating_id')" class="font-semibold">Base-ID</button>
                        </th>
                        <th class="px-4 py-3">Versicherung</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('rating_score')" class="font-semibold">Score</button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('status')" class="font-semibold">Status</button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('is_public')" class="font-semibold">Sichtbar</button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('created_at')" class="font-semibold">Angelegt</button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($ratings as $rating)
                        <tr wire:key="claim-rating-{{ $rating->id }}" class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">#{{ $rating->id }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                {{ $rating->base_claim_rating_id ? '#' . $rating->base_claim_rating_id : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                <div>Versicherung: {{ $rating->insurance_id ?? '-' }}</div>
                                <div class="text-xs text-slate-500">
                                    Typ: {{ $rating->insurance_type_id ?? '-' }} / Untertyp: {{ $rating->insurance_subtype_id ?? '-' }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                {{ $rating->rating_score ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700">
                                    {{ $rating->status_label }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if($rating->is_public)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Ja</span>
                                @else
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">Nein</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ optional($rating->created_at)->format('d.m.Y H:i') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                Noch keine Bewertungen gespeichert.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $ratings->links() }}
    </div>
</div>
