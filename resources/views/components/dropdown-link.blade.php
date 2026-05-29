@props(['can' => true])

@php
if (is_string($can) && $can !== '') {
    $isAllowed = auth()->check() && auth()->user()->can($can);
} else {
    $isAllowed = (bool) $can;
}

$isDeniedByCan = ! $isAllowed;
$isDisabled = isset($attributes['disabled']) || $isDeniedByCan;

$classes = 'inline-flex items-center w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out';
if ($isDisabled) {
    $classes .= ' opacity-80 cursor-not-allowed hover:bg-transparent';
}

$title = $isDeniedByCan
    ? 'Keine Berechtigung fuer diese Aktion'
    : $attributes->get('title');

$attributesWithoutTitle = $attributes->except('title');

$sanitizedAttributes = $attributesWithoutTitle->filter(function ($value, $key) use ($isDisabled) {
    if (! $isDisabled) {
        return true;
    }

    if ($key === 'href') {
        return false;
    }

    foreach (['wire:click', '@click', 'x-on:click', 'onclick'] as $prefix) {
        if ($key === $prefix || str_starts_with($key, $prefix . '.')) {
            return false;
        }
    }

    return true;
});
@endphp

<a
    {{ $sanitizedAttributes->merge(['class' => $classes]) }}
    @if($title) title="{{ $title }}" @endif
    @if($isDisabled) aria-disabled="true" disabled tabindex="-1" x-on:click.prevent.stop @endif
>
    {{ $slot }}
</a>
