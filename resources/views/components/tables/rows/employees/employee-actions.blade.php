<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button type="button" class="text-center px-4 py-2 text-xl font-semibold bg-white hover:bg-gray-100 rounded-lg border border-gray-200">
            &#x22EE;
        </button>
    </x-slot>

    <x-slot name="content">
        {{-- Details: normale Navigation --}}
        <x-dropdown-link wire:click.prevent="openEdit({{ $item->id }})" :can="'employees.create'">
            <i class="far fa-pen mr-2"></i>
            Bearbeiten
        </x-dropdown-link>

        @if ($item->status)
            <x-dropdown-link wire:click.prevent="deactivateUser({{ $item->id }})" :can="'employees.create'" class="hover:bg-yellow-100">
                <i class="far fa-pause-circle mr-2"></i>
                Deaktivieren
            </x-dropdown-link>
        @else
            <x-dropdown-link wire:click.prevent="activateUser({{ $item->id }})" :can="'employees.create'" class="hover:bg-green-100">
                <i class="far fa-play-circle mr-2"></i>
                Aktivieren
            </x-dropdown-link>
        @endif

    </x-slot>
</x-dropdown>
