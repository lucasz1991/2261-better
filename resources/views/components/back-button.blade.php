@props([
    'href' => null,
    'label' => 'Zurück',
])

@php
    $baseClasses = 'text-gray-900 bg-white hover:bg-gray-200 focus:ring-gray-100 border-gray-300 px-2 py-1 text-sm transition-all duration-100 inline-flex items-center justify-center text-center border rounded-lg shadow';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $baseClasses]) }}>
        <i class="far fa-arrow-left mr-2"></i>
        <span>{{ trim((string) $slot) !== '' ? $slot : $label }}</span>
    </a>
@else
    <button type="button" onclick="window.history.back()" {{ $attributes->merge(['class' => $baseClasses]) }}>
        <i class="far fa-arrow-left mr-2"></i>
        <span>{{ trim((string) $slot) !== '' ? $slot : $label }}</span>
    </button>
@endif
