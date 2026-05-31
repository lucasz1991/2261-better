<x-dialog-modal wire:model="showRatingModal" maxWidth="4xl">
    <x-slot name="title">
        {{ $selectedRating ? 'Bewertung #' . $selectedRating->id : 'Bewertung' }}
    </x-slot>

    <x-slot name="content">
        @if($selectedRating)
            @php
                $syntheticUser = $selectedRating->syntheticUser;
                $baseUserId = $syntheticUser?->base_user_id ?: $selectedRating->base_user_id;
                $legacyUserProfile = data_get($selectedRating->data, 'planning.synthetic_user_profile');
                $legacyUserProfile = is_array($legacyUserProfile) ? $legacyUserProfile : null;
                $syntheticUserPayload = null;

                if ($syntheticUser) {
                    $syntheticUserPayload = $syntheticUser->toArray();
                    $syntheticUserPayload['privacy_settings'] = $syntheticUser->privacySettings();
                    $syntheticUserPayload['related_claim_rating_ids'] = $syntheticUser->claimRatings()->pluck('id')->all();
                }

                $userPayload = $syntheticUserPayload ?: $legacyUserProfile;
                $ratingData = is_array($selectedRating->data) ? $selectedRating->data : [];
                $attachments = is_array($selectedRating->attachments) ? $selectedRating->attachments : [];
            @endphp

            <div class="max-h-[72vh] space-y-4 overflow-y-auto pr-1 text-slate-700">
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
                        <div class="mt-1 font-semibold text-slate-900">{{ data_get($ratingData, 'planning.target_score_profile.label', '-') }}</div>
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

                <div class="space-y-3">
                    <details open class="group rounded-md border border-slate-200 bg-white">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Zuordnung</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Base, Versicherung, Art und Unterart</p>
                            </div>
                            <i class="fal fa-chevron-down text-slate-400 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-slate-100 px-4 py-4">
                            <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-3">
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Base-ID</dt>
                                    <dd class="mt-1 text-slate-900">{{ $selectedRating->base_claim_rating_id ? '#' . $selectedRating->base_claim_rating_id : '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Base-User-ID</dt>
                                    <dd class="mt-1 text-slate-900">{{ $baseUserId ? '#' . $baseUserId : '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Lokaler Testnutzer</dt>
                                    <dd class="mt-1 text-slate-900">{{ $syntheticUser ? '#' . $syntheticUser->id : '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Versicherung</dt>
                                    <dd class="mt-1 text-slate-900">{{ data_get($ratingData, 'base_context.insurance.name') ?: ($selectedRating->insurance_id ?? '-') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Art</dt>
                                    <dd class="mt-1 text-slate-900">{{ data_get($ratingData, 'base_context.insurance_type.name') ?: ($selectedRating->insurance_type_id ?? '-') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Unterart</dt>
                                    <dd class="mt-1 text-slate-900">{{ data_get($ratingData, 'base_context.insurance_subtype.name') ?: ($selectedRating->insurance_subtype_id ?? '-') }}</dd>
                                </div>
                            </dl>
                        </div>
                    </details>

                    <details {{ $userPayload ? 'open' : '' }} class="group rounded-md border border-blue-200 bg-blue-50/60">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-blue-950">Benutzer</h3>
                                <p class="mt-0.5 text-xs text-blue-700">{{ $userPayload ? 'Lokaler synthetischer Benutzer und gespeicherte Daten' : 'Kein synthetischer Benutzer verknuepft' }}</p>
                            </div>
                            <i class="fal fa-chevron-down text-blue-500 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-blue-100 px-4 py-4">
                            @if($userPayload)
                                <div class="mb-4 grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                                    <div>
                                        <div class="text-xs font-medium uppercase tracking-wide text-blue-700">Name</div>
                                        <div class="mt-1 font-semibold text-blue-950">{{ data_get($userPayload, 'name', '-') }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium uppercase tracking-wide text-blue-700">E-Mail</div>
                                        <div class="mt-1 break-words font-semibold text-blue-950">{{ data_get($userPayload, 'email', '-') }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium uppercase tracking-wide text-blue-700">Lokale ID</div>
                                        <div class="mt-1 font-semibold text-blue-950">{{ data_get($userPayload, 'id') ? '#' . data_get($userPayload, 'id') : '-' }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium uppercase tracking-wide text-blue-700">Base-User-ID</div>
                                        <div class="mt-1 font-semibold text-blue-950">{{ $baseUserId ? '#' . $baseUserId : '-' }}</div>
                                    </div>
                                </div>

                                @include('livewire.admin.partials.claim-rating-value-list', ['value' => $userPayload, 'level' => 0])
                            @else
                                <div class="rounded-md border border-blue-100 bg-white px-3 py-2 text-sm text-blue-800">
                                    Fuer diese Bewertung wurde noch kein synthetischer Benutzer gespeichert.
                                </div>
                            @endif
                        </div>
                    </details>

                    <details class="group rounded-md border border-slate-200 bg-white">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Antworten</h3>
                                <p class="mt-0.5 text-xs text-slate-500">{{ count((array) $selectedRating->answers) }} Felder</p>
                            </div>
                            <i class="fal fa-chevron-down text-slate-400 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-slate-100 px-4 py-4">
                            @include('livewire.admin.partials.claim-rating-value-list', ['value' => $selectedRating->answers ?? [], 'level' => 0])
                        </div>
                    </details>

                    <details class="group rounded-md border border-slate-200 bg-white">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">AI-Auswertung</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Scorings, Tags und Zielscore-Abgleich</p>
                            </div>
                            <i class="fal fa-chevron-down text-slate-400 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-slate-100 px-4 py-4">
                            @include('livewire.admin.partials.claim-rating-value-list', ['value' => $attachments['scorings'] ?? [], 'level' => 0])
                        </div>
                    </details>

                    <details class="group rounded-md border border-slate-200 bg-white">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">AI-Generierung</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Erzeugungsstatus und Kommentar</p>
                            </div>
                            <i class="fal fa-chevron-down text-slate-400 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-slate-100 px-4 py-4">
                            @include('livewire.admin.partials.claim-rating-value-list', ['value' => $ratingData['ai_generation'] ?? [], 'level' => 0])
                        </div>
                    </details>

                    <details class="group rounded-md border border-slate-200 bg-white">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Planung und Base-Kontext</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Planungsdaten, Base-Kontext und Publish-Status</p>
                            </div>
                            <i class="fal fa-chevron-down text-slate-400 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-slate-100 px-4 py-4">
                            @include('livewire.admin.partials.claim-rating-value-list', ['value' => [
                                'planning' => $ratingData['planning'] ?? [],
                                'base_context' => $ratingData['base_context'] ?? [],
                                'base_publish' => $ratingData['base_publish'] ?? [],
                            ], 'level' => 0])
                        </div>
                    </details>

                    <details class="group rounded-md border border-slate-200 bg-white">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Weitere Rohdaten</h3>
                                <p class="mt-0.5 text-xs text-slate-500">Attachments, Tag-IDs, Admin-Review und Data</p>
                            </div>
                            <i class="fal fa-chevron-down text-slate-400 transition group-open:rotate-180"></i>
                        </summary>
                        <div class="border-t border-slate-100 px-4 py-4">
                            @include('livewire.admin.partials.claim-rating-value-list', ['value' => [
                                'attachments' => $attachments,
                                'tag_ids' => $selectedRating->tag_ids ?? [],
                                'admin_review' => $selectedRating->admin_review ?? [],
                                'data' => $ratingData,
                            ], 'level' => 0])
                        </div>
                    </details>

                    @if($selectedRating->last_execution_error)
                        <details open class="group rounded-md border border-rose-200 bg-rose-50">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-rose-900">Letzter Fehler</h3>
                                    <p class="mt-0.5 text-xs text-rose-700">Fehler der letzten Ausfuehrung</p>
                                </div>
                                <i class="fal fa-chevron-down text-rose-500 transition group-open:rotate-180"></i>
                            </summary>
                            <div class="border-t border-rose-100 px-4 py-4">
                                <div class="whitespace-pre-wrap break-words text-sm text-rose-800">{{ $selectedRating->last_execution_error }}</div>
                            </div>
                        </details>
                    @endif
                </div>
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
