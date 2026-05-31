@php
    $level = $level ?? 0;
    $isArray = is_array($value);
@endphp

@if($isArray)
    <div class="{{ $level === 0 ? 'space-y-2' : 'mt-2 space-y-2 border-l border-slate-200 pl-3' }}">
        @forelse($value as $key => $entry)
            <div class="rounded-md border border-slate-200 bg-white px-3 py-2">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ is_numeric($key) ? '#' . ((int) $key + 1) : str_replace('_', ' ', (string) $key) }}
                </div>

                @if(is_array($entry))
                    @include('livewire.admin.partials.claim-rating-value-list', ['value' => $entry, 'level' => $level + 1])
                @else
                    @php
                        $displayValue = match (true) {
                            is_bool($entry) => $entry ? 'Ja' : 'Nein',
                            $entry === null => '-',
                            is_scalar($entry) => (string) $entry,
                            default => json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        };
                    @endphp
                    <div class="mt-1 whitespace-pre-wrap break-words text-sm text-slate-800">{{ $displayValue }}</div>
                @endif
            </div>
        @empty
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">
                Keine Daten gespeichert.
            </div>
        @endforelse
    </div>
@else
    @php
        $displayValue = match (true) {
            is_bool($value) => $value ? 'Ja' : 'Nein',
            $value === null => '-',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        };
    @endphp
    <div class="whitespace-pre-wrap break-words text-sm text-slate-800">{{ $displayValue }}</div>
@endif
