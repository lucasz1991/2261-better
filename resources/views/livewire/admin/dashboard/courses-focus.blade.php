<div class="space-y-3" @if($autoRefresh) wire:poll.120s="$refresh" @endif>
    <div class="rounded-xl border border-gray-200 bg-gradient-to-b from-gray-50 to-white">
        <div class="overflow-y-auto max-h-80 divide-y divide-gray-100 scroll-container">
            @forelse($courses as $course)
                <a href="{{ $course['url'] }}" class="block px-3 py-2.5 hover:bg-gray-50 transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $course['title'] }}</p>
                            <p class="text-xs text-gray-600 truncate">Dozent: {{ $course['tutor_name'] }}</p>
                        </div>

                        <span class="shrink-0 inline-flex items-center rounded-full border px-2 py-0.5 text-[11px]
                            {{ $course['is_running'] ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-sky-200 bg-sky-50 text-sky-700' }}">
                            {{ $course['status_label'] }}
                        </span>
                    </div>

                    <div class="mt-1.5 text-[11px] text-gray-500">
                        Start: {{ $course['start'] ?? '-' }} - Ende: {{ $course['end'] ?? 'offen' }}
                    </div>
                </a>
            @empty
                <div class="px-4 py-5 text-sm text-gray-600">
                    Keine laufenden oder kommenden Kurse gefunden.
                </div>
            @endforelse
        </div>
    </div>

    <div class="flex justify-end">
        <a href="{{ route('admin.courses.index') }}"
           class="inline-flex items-center gap-1 text-xs font-medium text-sky-700 hover:text-sky-800">
            Zur Kursübersicht
            <i class="fal fa-arrow-right text-[11px]"></i>
        </a>
    </div>
</div>




