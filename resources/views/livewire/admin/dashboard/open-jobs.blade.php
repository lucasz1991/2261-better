<div class="space-y-3" @if($autoRefresh) wire:poll.120s="$refresh" @endif>
    <div class="rounded-xl border border-gray-200 bg-gradient-to-b from-gray-50 to-white">
        <div class="divide-y divide-gray-100">
            @forelse($jobs as $job)
                <a href="{{ route('admin.tasks.index') }}"
                   class="block px-3 py-2.5 hover:bg-gray-50 transition">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $job['task_type_text'] }}</p>
                            <p class="text-xs text-gray-600 truncate">{{ $job['context_text'] }}</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] border-amber-200 bg-amber-50 text-amber-700">
                            {{ $job['status_text'] }}
                        </span>
                    </div>

                    <div class="mt-1.5 flex items-center justify-between gap-3">
                        <div class="text-[11px] text-gray-500">
                            Prio: {{ $job['priority_text'] ?? '-' }}
                            @if(!empty($job['assigned_admin_name']))
                                - Zuständig: {{ $job['assigned_admin_name'] }}
                            @endif
                        </div>
                        <div class="text-[11px] {{ !empty($job['is_overdue']) ? 'text-red-700 font-semibold' : 'text-gray-500' }}">
                            @if(!empty($job['due_at']))
                                Fällig: {{ $job['due_at'] }}
                            @else
                                Keine Frist
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-4 py-5 text-sm text-gray-600">
                    Keine offenen Jobs vorhanden.
                </div>
            @endforelse
        </div>
    </div>

    <div class="flex justify-end">
        <a href="{{ route('admin.tasks.index') }}"
           class="inline-flex items-center gap-1 text-xs font-medium text-sky-700 hover:text-sky-800">
            Zur Job-Übersicht
            <i class="fal fa-arrow-right text-[11px]"></i>
        </a>
    </div>
</div>


