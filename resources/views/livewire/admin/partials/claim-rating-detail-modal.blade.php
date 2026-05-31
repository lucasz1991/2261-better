<x-dialog-modal wire:model="showRatingModal" maxWidth="4xl">
    <x-slot name="title">
        {{ $selectedRating ? 'Bewertung #' . $selectedRating->id : 'Bewertung' }}
    </x-slot>

    <x-slot name="content">
        @if($selectedRating)
            <div class="max-h-[70vh] space-y-5 overflow-y-auto pr-1 text-slate-700">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ $selectedRating->status_label }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Score</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ $selectedRating->rating_score ?? '-' }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Ziel-Score</div>
                        <div class="mt-1 font-semibold text-slate-900">
                            {{ data_get($selectedRating->data, 'planning.target_score_profile.label', '-') }}
                        </div>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Geplant</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ optional($selectedRating->scheduled_for)->format('d.m.Y H:i') ?? '-' }}</div>
                    </div>
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Ausgefuehrt</div>
                        <div class="mt-1 font-semibold text-slate-900">{{ optional($selectedRating->executed_at)->format('d.m.Y H:i') ?? '-' }}</div>
                    </div>
                </div>

                <div class="rounded-md border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-900">Zuordnung</h3>
                    <dl class="mt-3 grid gap-3 text-sm md:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Base-ID</dt>
                            <dd class="mt-1 text-slate-900">{{ $selectedRating->base_claim_rating_id ? '#' . $selectedRating->base_claim_rating_id : '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Versicherung</dt>
                            <dd class="mt-1 text-slate-900">{{ $selectedRating->insurance_id ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Art</dt>
                            <dd class="mt-1 text-slate-900">{{ $selectedRating->insurance_type_id ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Unterart</dt>
                            <dd class="mt-1 text-slate-900">{{ $selectedRating->insurance_subtype_id ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900">Antworten</h3>
                        <span class="text-xs text-slate-500">{{ count((array) $selectedRating->answers) }} Felder</span>
                    </div>

                    <div class="mt-3">
                        @include('livewire.admin.partials.claim-rating-value-list', ['value' => $selectedRating->answers ?? [], 'level' => 0])
                    </div>
                </div>

                @if(is_array($selectedRating->attachments) && ! empty($selectedRating->attachments['scorings']))
                    <div class="rounded-md border border-slate-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-slate-900">AI-Auswertung</h3>
                        <div class="mt-3">
                            @include('livewire.admin.partials.claim-rating-value-list', ['value' => $selectedRating->attachments['scorings'], 'level' => 0])
                        </div>
                    </div>
                @endif

                @if(is_array($selectedRating->data) && ! empty($selectedRating->data['ai_generation']))
                    <div class="rounded-md border border-slate-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-slate-900">AI-Generierung</h3>
                        <div class="mt-3">
                            @include('livewire.admin.partials.claim-rating-value-list', ['value' => $selectedRating->data['ai_generation'], 'level' => 0])
                        </div>
                    </div>
                @endif

                @if($selectedRating->last_execution_error)
                    <div class="rounded-md border border-rose-200 bg-rose-50 p-4">
                        <h3 class="text-sm font-semibold text-rose-900">Letzter Fehler</h3>
                        <div class="mt-2 whitespace-pre-wrap break-words text-sm text-rose-800">{{ $selectedRating->last_execution_error }}</div>
                    </div>
                @endif
            </div>
        @else
            <div class="text-sm text-slate-500">Keine Bewertung ausgewaehlt.</div>
        @endif
    </x-slot>

    <x-slot name="footer">
        <button
            type="button"
            wire:click="closeRatingModal"
            class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            Schliessen
        </button>
    </x-slot>
</x-dialog-modal>
