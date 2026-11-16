<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Bewertungen</h1>
            <p class="mt-1 text-sm text-slate-600">Ausgeführte Bewertungen mit Benutzer, Sichtbarkeit und Übertragungsstand. Eine verknüpfte Base-ID allein bestätigt keine aktuelle öffentliche Anzeige.</p>
        </div>

        <button
            type="button"
            wire:click="clearFilters"
            class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Filter zuruecksetzen
        </button>
    </div>

    @include('livewire.admin.partials.claim-rating-filters')

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
                        <th class="px-4 py-3">
                            <div class="flex gap-2"><button type="button" wire:click="sortBy('name')" class="font-semibold">Name</button><span aria-hidden="true">/</span><button type="button" wire:click="sortBy('username')" class="font-semibold">Benutzername</button></div>
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
                            <button type="button" wire:click="sortBy('executed_at')" class="font-semibold">Ausgefuehrt</button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($ratings as $rating)
                        @php
                            $syntheticUser = $rating->syntheticUser;
                            $baseUserId = $syntheticUser?->base_user_id ?: $rating->base_user_id;
                            $profile = data_get($rating->data, 'planning.synthetic_user_profile', []);
                            $displayName = $syntheticUser?->name ?: data_get($profile, 'name', 'Kein Benutzer');
                            $username = $syntheticUser?->username ?: data_get($profile, 'username');
                        @endphp
                        <tr
                            wire:key="claim-rating-{{ $rating->id }}"
                            wire:click="openRatingModal({{ $rating->id }})"
                            x-on:keydown.enter="$wire.openRatingModal({{ $rating->id }})"
                            x-on:keydown.space.prevent="$wire.openRatingModal({{ $rating->id }})"
                            tabindex="0"
                            role="button"
                            aria-label="Bewertung #{{ $rating->id }} anzeigen"
                            class="cursor-pointer hover:bg-slate-50 focus:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                        >
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">#{{ $rating->id }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                {{ $rating->base_claim_rating_id ? '#' . $rating->base_claim_rating_id : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                @if($syntheticUser || $baseUserId)
                                    <div class="space-y-1">
                                        <div class="font-semibold text-slate-900">{{ $displayName }}</div>
                                        @if($username)<div class="text-sm text-blue-700">{{ '@'.$username }}</div>@endif
                                        @if($syntheticUser)
                                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700">
                                                Lokal #{{ $syntheticUser->id }}
                                            </span>
                                        @endif
                                        @if($baseUserId)
                                            <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">
                                                Base #{{ $baseUserId }}
                                            </span>
                                        @endif
                                    </div>
                                @else
                                    <div class="font-medium text-slate-700">{{ $displayName }}</div>
                                    @if($username)<div class="text-xs text-blue-700">{{ '@'.$username }}</div>@endif
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                <div>{{ data_get($rating->data, 'base_context.insurance.name') ?: 'Versicherung: ' . ($rating->insurance_id ?? '-') }}</div>
                                <div class="text-xs text-slate-500">
                                    {{ data_get($rating->data, 'base_context.insurance_type.name') ?: 'Typ: ' . ($rating->insurance_type_id ?? '-') }}
                                    /
                                    {{ data_get($rating->data, 'base_context.insurance_subtype.name') ?: 'Untertyp: ' . ($rating->insurance_subtype_id ?? '-') }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                {{ $rating->rating_score ?? '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-700">
                                    {{ $rating->status_label }}
                                </span>
                                <div class="mt-2">@include('livewire.admin.partials.claim-rating-sync-state')</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if($rating->is_public)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Ja</span>
                                @else
                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-medium text-slate-600">Nein</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                {{ optional($rating->executed_at)->format('d.m.Y H:i') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                Keine Bewertungen für diese Filter gefunden. Suche oder Filter zurücksetzen.
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

    @include('livewire.admin.partials.claim-rating-detail-modal', ['selectedRating' => $selectedRating])
</div>
