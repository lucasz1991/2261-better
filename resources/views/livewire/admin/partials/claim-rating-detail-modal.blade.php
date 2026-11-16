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
                @if (session()->has('success'))
                    <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
                @endif
                @if (session()->has('error'))
                    <div role="alert" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                        <ul class="list-inside list-disc">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                    </div>
                @endif
                @if($this->canEditSelectedRating())
                    @php
                        $isPublishedEdit = (bool) ($selectedRating->executed_at || $selectedRating->base_claim_rating_id);
                        $affectedCount = $syntheticUser?->claimRatings()->count() ?? 0;
                        $visibility = $editBaseSnapshot['privacy_name_visibility'] ?? data_get($syntheticUser?->data, 'privacy_settings.ratings.name_visibility', 'none');
                        $guestNameVisible = in_array($visibility, ['all', 'guest'], true);
                    @endphp
                    <form wire:submit="saveRatingDetailEdits" class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                        <h3 class="text-base font-semibold text-emerald-950">{{ $isPublishedEdit ? 'Veröffentlichte Bewertung bearbeiten' : 'Geplante Bewertung bearbeiten' }}</h3>
                        <p class="mt-1 text-sm text-emerald-800">Anzeigename und Benutzername werden getrennt gespeichert. Änderungen am Benutzer gelten für {{ $affectedCount }} zugehörige Bewertung(en).</p>
                        @if ($editBaseError)
                            <div role="alert" class="mt-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">{{ $editBaseError }}</div>
                        @endif
                        <fieldset class="mt-4 space-y-4" @disabled(! \Illuminate\Support\Facades\Gate::allows('synthetic_users.edit'))>
                            <legend class="text-sm font-semibold text-slate-900">Benutzer</legend>
                            <div class="grid gap-3 md:grid-cols-2">
                                @foreach (['editUserName' => 'Anzeigename', 'editUserUsername' => 'Benutzername', 'editUserFirstName' => 'Vorname', 'editUserLastName' => 'Nachname'] as $binding => $label)
                                    <label for="rating-{{ $binding }}" class="block text-sm font-medium text-slate-800">{{ $label }}
                                        <input id="rating-{{ $binding }}" type="text" wire:model="{{ $binding }}" maxlength="{{ $binding === 'editUserUsername' ? 42 : ($binding === 'editUserName' ? 191 : 100) }}" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm disabled:bg-slate-100">
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" wire:click="suggestRatingUsernames" wire:loading.attr="disabled" wire:target="suggestRatingUsernames" class="rounded-md border border-emerald-700 bg-white px-3 py-2 text-sm font-semibold text-emerald-800 disabled:opacity-50">Andere Benutzernamen vorschlagen</button>
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="editApplySuggestionToName" class="rounded border-slate-300">Vorschlag auch als Anzeigenamen übernehmen</label>
                                <span wire:loading wire:target="suggestRatingUsernames" class="text-sm text-slate-500">Vorschläge werden geprüft …</span>
                            </div>
                            @if ($editUsernameSuggestions)
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($editUsernameSuggestions as $index => $suggestion)
                                        <button type="button" wire:click="useRatingUsernameSuggestion({{ $index }})" class="rounded-full border border-emerald-300 bg-white px-3 py-1.5 text-sm text-emerald-900">{{ $suggestion['username'] }}</button>
                                    @endforeach
                                </div>
                                <p class="text-xs text-slate-500">Vorschläge sind noch nicht reserviert und werden beim Speichern erneut geprüft.</p>
                            @endif
                        </fieldset>
                        <div class="mt-4 rounded-md border border-slate-200 bg-white p-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Vorschau für nicht angemeldete Besucher</p>
                            <p class="mt-1 font-semibold text-slate-900" @if($guestNameVisible) x-text="$wire.editUserName" @endif>{{ $guestNameVisible ? $editUserName : 'Anonym' }}</p>
                            <p class="mt-1 text-xs text-slate-500">Die vorhandene Sichtbarkeit bleibt erhalten. Benutzername: {{ $editUserUsername }}</p>
                        </div>
                        <fieldset class="mt-5 space-y-3">
                            <legend class="text-sm font-semibold text-slate-900">Bewertung</legend>
                            <label for="rating-edit-summary" class="block text-sm font-medium text-slate-800">Öffentlicher Bewertungstext
                                <textarea id="rating-edit-summary" wire:model="editSummary" rows="4" maxlength="10000" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
                            </label>
                            <label for="rating-edit-comment" class="block text-sm font-medium text-slate-800">Kommentar zur Regulierung
                                <textarea id="rating-edit-comment" wire:model="editRegulationComment" rows="3" maxlength="10000" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"></textarea>
                            </label>
                            <div class="grid gap-3 md:grid-cols-2">
                                <label for="rating-edit-score" class="block text-sm font-medium text-slate-800">Score (0 bis 1)
                                    <input id="rating-edit-score" type="number" min="0" max="1" step="0.01" wire:model="editRatingScore" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                                </label>
                                @if (! $isPublishedEdit)
                                    <label for="rating-edit-scheduled-for" class="block text-sm font-medium text-slate-800">Geplante Ausführung
                                        <input id="rating-edit-scheduled-for" type="datetime-local" wire:model="editScheduledFor" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                                    </label>
                                @endif
                            </div>
                            <label class="flex items-start gap-2 text-sm text-slate-700"><input type="checkbox" wire:model="editContentConfirmed" class="mt-0.5 rounded border-slate-300">Bei einer Inhaltsänderung: Ich habe geprüft, dass Bewertungstext, Kommentar und Score zusammenpassen. Es wird keine automatische AI-Neubewertung ausgelöst.</label>
                        </fieldset>
                        <label for="rating-edit-reason" class="mt-4 block text-sm font-medium text-slate-800">Änderungsgrund
                            <input id="rating-edit-reason" type="text" wire:model="editReason" maxlength="1000" placeholder="Welche Angaben werden korrigiert?" class="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm">
                        </label>
                        <div class="mt-4 flex items-center justify-between gap-3">
                            <p class="text-xs text-slate-600">Bestehende IDs, Veröffentlichung und Sichtbarkeit bleiben erhalten.</p>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveRatingDetailEdits" @disabled($editBaseError !== '') class="shrink-0 rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">
                                <span wire:loading.remove wire:target="saveRatingDetailEdits">Änderungen speichern</span><span wire:loading wire:target="saveRatingDetailEdits">Speichert …</span>
                            </button>
                        </div>
                    </form>
                @else
                    <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">Dieser Datensatz ist derzeit schreibgeschützt oder Sie haben keine Bearbeitungsberechtigung.</div>
                @endif

                @if ($this->ratingEditHistory())
                    <details open class="rounded-lg border border-slate-200 bg-white">
                        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-900">Änderungsverlauf dieses Benutzers</summary>
                        <div class="space-y-3 border-t border-slate-100 p-4">
                            @foreach ($this->ratingEditHistory() as $revision)
                                <article wire:key="rating-edit-revision-{{ $revision->id }}" class="rounded-md border border-slate-200 p-3 text-sm">
                                    <div class="flex flex-wrap justify-between gap-2"><strong>{{ $revision->status_label }}</strong><span class="text-xs text-slate-500">{{ $revision->created_at->format('d.m.Y H:i') }} · Bearbeiter #{{ $revision->actor_id ?? 'System' }} · Bewertung #{{ $revision->claim_rating_id }}</span></div>
                                    <p class="mt-1 text-slate-700">{{ $revision->reason }}</p>
                                    <dl class="mt-2 space-y-1">
                                        @foreach (['user' => 'Benutzer', 'rating' => 'Bewertung'] as $group => $groupLabel)
                                            @foreach (($revision->changes[$group] ?? []) as $field => $change)
                                                <div class="grid gap-1 md:grid-cols-3"><dt class="font-medium">{{ $groupLabel }}: {{ $field }}</dt><dd class="break-words text-slate-500">{{ $change['before'] ?? '—' }}</dd><dd class="break-words text-slate-900">{{ $change['after'] ?? '—' }}</dd></div>
                                            @endforeach
                                        @endforeach
                                    </dl>
                                    @if ($revision->last_error)<p class="mt-2 text-amber-800">{{ $revision->last_error }}</p>@endif
                                    @if (in_array($revision->status, ['pending', 'failed'], true))
                                        @can('ratings.edit_published')<button type="button" wire:click="retryRatingEdit({{ $revision->id }})" wire:loading.attr="disabled" class="mt-2 rounded-md border border-slate-300 px-3 py-1.5 font-medium">Erneut übertragen</button>@endcan
                                    @endif
                                    @if (in_array($revision->status, ['synced', 'local'], true) && (int) $revision->claim_rating_id === (int) $selectedRating->id)
                                        @can('ratings.edit')<button type="button" wire:click="prepareRatingEditRestore({{ $revision->id }})" class="mt-2 rounded-md border border-slate-300 px-3 py-1.5 font-medium">Vorherwerte ins Formular laden</button>@endcan
                                    @endif
                                    @if ($revision->status === 'conflict' && (int) $revision->claim_rating_id === (int) $selectedRating->id && $editBaseSnapshot)
                                        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3">
                                            <p class="font-semibold text-amber-900">Aktuelle Basewerte mit den gewünschten Änderungen oben vergleichen</p>
                                            <p class="mt-1 text-xs text-amber-900">Bei einem belegten Benutzernamen können Sie oben einen freien Zielwert eingeben und normal speichern. Eine noch nicht angewendete Änderung kann auch verworfen werden.</p>
                                            @foreach (['user' => 'user', 'rating' => 'rating'] as $group => $snapshotGroup)
                                                @foreach (($revision->changes[$group] ?? []) as $field => $change)
                                                    <p class="mt-1 break-words text-xs">{{ $field }} aktuell in Base: {{ data_get($editBaseSnapshot, $snapshotGroup.'.'.$field, '—') }}</p>
                                                @endforeach
                                            @endforeach
                                            <label class="mt-2 flex items-start gap-2 text-sm"><input type="checkbox" wire:model="editConflictConfirmed" class="mt-0.5 rounded border-slate-300">Ich habe die aktuellen Werte geprüft und bestätige die gewählte Konfliktaktion.</label>
                                            @can('ratings.edit_published')<button type="button" wire:click="resolveRatingEditConflict({{ $revision->id }})" wire:loading.attr="disabled" class="mt-2 rounded-md border border-amber-800 px-3 py-1.5 font-medium text-amber-950">Konflikt mit Änderungsgrund oben auflösen</button>@endcan
                                            @can('ratings.edit_published')<button type="button" wire:click="discardRatingEditConflict({{ $revision->id }})" wire:loading.attr="disabled" class="mt-2 rounded-md border border-slate-400 px-3 py-1.5 font-medium">Unübertragene Änderung verwerfen</button>@endcan
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </details>
                @endif

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
