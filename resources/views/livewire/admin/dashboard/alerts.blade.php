<div class="space-y-2" @if($autoRefresh) wire:poll.120s="$refresh" @endif>
    @forelse($alerts as $alert)
        <div class="rounded-xl border border-gray-200 bg-gradient-to-b from-gray-50 to-white p-3">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border {{ $alert['icon_bg'] }}">
                    <i class="fal {{ $alert['icon'] }} {{ $alert['icon_color'] }}"></i>
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-gray-900">{{ $alert['title'] }}</p>
                        <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] {{ $alert['badge_classes'] }}">
                            {{ strtoupper($alert['type']) }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-gray-700">{{ $alert['message'] }}</p>

                    <div class="mt-2">
                        <a href="{{ $alert['action_url'] }}"
                           class="inline-flex items-center gap-1 text-xs font-medium text-sky-700 hover:text-sky-800">
                            {{ $alert['action_label'] }}
                            <i class="fal fa-arrow-right text-[11px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-emerald-200 bg-white">
                    <i class="fal fa-check-circle text-emerald-700"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-emerald-900">Keine kritischen Hinweise gefunden</p>
                    <p class="text-sm text-emerald-800">Im aktuellen Zeitraum wurden keine handlungsrelevanten Warnungen erkannt.</p>
                </div>
            </div>
        </div>
    @endforelse
</div>


