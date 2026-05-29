<div class="space-y-5">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-r from-white to-slate-50 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-5">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-800">Nachrichten & E-Mails</h1>
                <p class="mt-1 text-sm text-slate-600">{{ $mails->total() }} Eintraege im Versandprotokoll</p>
            </div>
            @if(Auth::user()->isAdmin())
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Super Admin</p>
                <p class="font-semibold">{{ config('mail.super_admin') ?: 'nicht gesetzt' }}</p>
            </div>
            @endif
        </div>
    </div>

    @if (session()->has('message'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="hidden grid-cols-12 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-600 md:grid">
            <div class="col-span-1">
                <button wire:click="sortByField('id')" class="inline-flex items-center gap-1">
                    ID
                    @if ($sortBy === 'id')
                        <span><i class="fa fa-chevron-up {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" aria-hidden="true"></i></span>
                    @endif
                </button>
            </div>
            <div class="col-span-2">
                <button wire:click="sortByField('created_at')" class="inline-flex items-center gap-1">
                    Datum
                    @if ($sortBy === 'created_at')
                        <span><i class="fa fa-chevron-up {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" aria-hidden="true"></i></span>
                    @endif
                </button>
            </div>
            <div class="col-span-2">Versandart</div>
            <div class="col-span-2">Empfaenger</div>
            <div class="col-span-2">
                <button wire:click="sortByField('status')" class="inline-flex items-center gap-1">
                    Status
                    @if ($sortBy === 'status')
                        <span><i class="fa fa-chevron-up {{ $sortDirection === 'asc' ? 'rotate-180' : '' }}" aria-hidden="true"></i></span>
                    @endif
                </button>
            </div>
            <div class="col-span-3">Aktionen</div>
        </div>

        @forelse ($mails as $mail)
            @php
                $type = strtolower((string) ($mail->type ?? ''));
                $isMessageOnly = $type === 'message';
                $isMailOnly = $type === 'mail';
                $typeLabel = $isMessageOnly ? 'Nachricht' : ($isMailOnly ? 'E-Mail' : 'Nachricht + E-Mail');
                $typeClass = $isMessageOnly
                    ? 'bg-amber-100 text-amber-700 border-amber-200'
                    : ($isMailOnly ? 'bg-sky-100 text-sky-700 border-sky-200' : 'bg-violet-100 text-violet-700 border-violet-200');
                $uniqueRecipients = collect($mail->recipients ?? [])
                    ->filter(fn ($recipient) => is_array($recipient))
                    ->unique(fn ($recipient) => ((int) ($recipient['user_id'] ?? 0)) . '|' . strtolower((string) ($recipient['email'] ?? '')))
                    ->values();
                $linkRaw = (string) ($mail->content['link'] ?? '');
            @endphp

            <div x-data="{ open: false }" @click.away="open = false" class="border-t border-slate-100 first:border-t-0">
                <div class="px-4 py-3 hover:bg-slate-50">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-center">
                        <button @click="open = !open" class="text-left text-sm font-semibold text-slate-800 md:col-span-1">
                            #{{ $mail->id }}
                        </button>

                        <button @click="open = !open" class="text-left text-sm text-slate-600 md:col-span-2">
                            {{ $mail->created_at->format('d.m.Y H:i') }}
                        </button>

                        <button @click="open = !open" class="text-left md:col-span-2">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold {{ $typeClass }}">
                                {{ $typeLabel }}
                            </span>
                        </button>

                        <button @click="open = !open" class="text-left text-sm text-slate-600 md:col-span-2">
                            {{ $uniqueRecipients->count() }} Empfaenger
                        </button>

                        <button @click="open = !open" class="text-left md:col-span-2">
                            @if ($mail->status)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Gesendet</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">Offen</span>
                            @endif
                        </button>

                        <div class="flex flex-wrap gap-2 md:col-span-3 md:justify-end">
                            <button
                                wire:click.stop="resendMail({{ $mail->id }})"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"
                            >
                                Erneut senden
                            </button>
                            @if(Auth::user()->isAdmin())
                            <button
                                wire:click.stop="sendMessageToSuperAdmin({{ $mail->id }})"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700"
                            >
                                SuperAdmin Test
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div x-show="open" x-collapse x-cloak class="border-t border-slate-100 bg-slate-50 px-4 py-4">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Betreff</p>
                            <p class="mt-1 text-sm font-medium text-slate-800">{{ $mail->content['subject'] ?? '-' }}</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Versandart</p>
                            <p class="mt-1 text-sm font-medium text-slate-800">{{ $typeLabel }}</p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Link</p>
                            @if ($linkRaw !== '')
                                @if (str_contains($linkRaw, '<'))
                                    <div class="prose prose-sm mt-1 max-w-none text-slate-700">{!! $linkRaw !!}</div>
                                @elseif (filter_var($linkRaw, FILTER_VALIDATE_URL))
                                    <a href="{{ $linkRaw }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-block truncate text-sm text-blue-600 underline">{{ $linkRaw }}</a>
                                @else
                                    <p class="mt-1 text-sm text-slate-700">{{ $linkRaw }}</p>
                                @endif
                            @else
                                <p class="mt-1 text-sm text-slate-500">Kein Link</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">Nachricht</p>
                        <div class="prose prose-sm mt-2 max-w-none text-slate-700">
                            {!! (string) ($mail->content['body'] ?? '') !!}
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4">
                        <h4 class="text-sm font-semibold text-slate-800">Empfaenger</h4>
                        <ul class="mt-3 max-h-44 space-y-2 overflow-y-auto">
                            @foreach ($uniqueRecipients as $recipient)
                                @php
                                    $recipientUserId = (int) ($recipient['user_id'] ?? 0);
                                    $recipientUser = $recipientUserId > 0 ? ($recipientUsers[$recipientUserId] ?? null) : null;
                                @endphp
                                <li class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                    <div class="min-w-0 pr-3">
                                        @if ($recipientUser)
                                            <x-user.public-info :user="$recipientUser" :size="8" />
                                        @else
                                            <span class="truncate text-slate-700">{{ $recipient['email'] ?? 'unbekannt' }}</span>
                                        @endif
                                    </div>
                                    @if (!empty($recipient['status']))
                                        <span class="text-xs font-semibold text-emerald-600">Gesendet</span>
                                    @else
                                        <span class="text-xs font-semibold text-rose-600">Nicht gesendet</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-4 py-8 text-center text-sm text-slate-500">Keine Mails vorhanden.</div>
        @endforelse
    </div>

    <div>
        {{ $mails->links() }}
    </div>
</div>
