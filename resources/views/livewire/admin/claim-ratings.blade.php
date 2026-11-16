<div class="rating-workspace">
    <header class="rating-page-header">
        <div class="flex flex-wrap items-center gap-3">
            <h1>Bewertungen</h1>
            <span class="rating-count">{{ number_format($ratings->total(), 0, ',', '.') }}</span>
        </div>
        <span class="rating-meta">Ausgeführte Bewertungen</span>
    </header>

    @if(session('success'))<div role="status" class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div role="alert" class="rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>@endif

    @include('livewire.admin.partials.claim-rating-filters')

    <div class="rating-shell">
        <div class="rating-table-scroll" tabindex="0" role="region" aria-label="Bewertungsliste, horizontal scrollbar">
            <table class="rating-table">
                <thead>
                    <tr>
                        @foreach(['name' => 'Benutzer', 'id' => 'Bewertung', 'rating_score' => 'Score', 'status' => 'Status', 'executed_at' => 'Ausgeführt'] as $field => $label)
                            <th scope="col" aria-sort="{{ $sortField === $field ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <button type="button" wire:click="sortBy('{{ $field }}')">{{ $label }} <i class="fal fa-sort" aria-hidden="true"></i></button>
                            </th>
                        @endforeach
                        <th scope="col"><span class="sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ratings as $rating)
                        @php
                            $insurance = data_get($rating->data, 'base_context.insurance.name') ?: 'Versicherer #'.($rating->insurance_id ?? '–');
                            $insuranceType = data_get($rating->data, 'base_context.insurance_type.name');
                            $summary = data_get($rating->attachments, 'scorings.ai_overall_comment') ?: data_get($rating->answers, 'regulationComment');
                            $score = $rating->rating_score !== null ? (float) $rating->rating_score : null;
                        @endphp
                        <tr wire:key="claim-rating-{{ $rating->id }}" wire:click="openRatingModal({{ $rating->id }})" class="rating-clickable-row">
                            <td class="rating-person-cell">@include('livewire.admin.partials.rating-user-identity', ['rating' => $rating])</td>
                            <td class="rating-review-cell">
                                <div class="flex items-center gap-2"><span class="font-semibold text-slate-800">{{ $insurance }}</span><span class="rating-meta">#{{ $rating->id }}</span></div>
                                @if($insuranceType)<p class="rating-meta mt-1">{{ $insuranceType }}</p>@endif
                                @if($summary)<p class="rating-excerpt" title="{{ $summary }}">{{ \Illuminate\Support\Str::limit($summary, 120) }}</p>@endif
                                @if($rating->base_claim_rating_id)<p class="rating-meta mt-1">Base #{{ $rating->base_claim_rating_id }}</p>@endif
                            </td>
                            <td><span @class(['rating-score', 'rating-score-good' => $score !== null && $score >= .7, 'rating-score-medium' => $score !== null && $score < .7 && $score >= .4])>{{ $score !== null ? number_format($score, 2, ',', '.') : '–' }}</span></td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span @class(['rating-status', 'rating-status-error' => $rating->status === 'failed'])><span class="rating-status-dot" aria-hidden="true"></span>{{ $rating->status_label }}</span>
                                    <span class="rating-meta" title="Sichtbarkeit laut lokalem Stand; die Base-Verknüpfung allein bestätigt keine öffentliche Anzeige.">{{ $rating->is_public ? 'Öffentlich markiert' : 'Nicht öffentlich' }}</span>
                                </div>
                                <div class="mt-2">@include('livewire.admin.partials.claim-rating-sync-state')</div>
                            </td>
                            <td class="whitespace-nowrap tabular-nums"><div class="font-medium text-slate-700">{{ $rating->executed_at?->format('d.m.Y') ?? '–' }}</div><div class="rating-meta mt-1">{{ $rating->executed_at?->format('H:i') }}</div></td>
                            <td class="text-right"><button type="button" wire:click.stop="openRatingModal({{ $rating->id }})" class="rating-row-action" aria-label="Bewertung #{{ $rating->id }} öffnen" title="Bewertung und Benutzer bearbeiten"><i class="fal fa-arrow-right" aria-hidden="true"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="rating-empty"><i class="fal fa-search" aria-hidden="true"></i><h2>Keine passenden Bewertungen</h2><p>Ändere die Suche oder setze die Filter zurück.</p><button type="button" wire:click="clearFilters" class="rating-button mt-3">Filter zurücksetzen</button></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="rating-list-footer">
            <label class="rating-page-size">Pro Seite <select wire:model.live="perPage" aria-label="Einträge pro Seite">@foreach([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach</select></label>
            <div class="min-w-0 flex-1">{{ $ratings->links() }}</div>
        </div>
    </div>
    @include('livewire.admin.partials.claim-rating-detail-modal', ['selectedRating' => $selectedRating])
</div>
