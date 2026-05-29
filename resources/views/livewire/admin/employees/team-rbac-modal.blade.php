<x-dialog-modal wire:model="showModal" maxWidth="4xl">
    <x-slot name="title">
        Teams und Rechte
    </x-slot>

    <x-slot name="content">
        <div class="space-y-4">
            <div class="text-sm text-gray-600">
                Zentrale Rechtevergabe pro Team mit genau einem Rechteset je Team.
            </div>

            <div class="grid md:grid-cols-[220px_1fr] gap-4">
                <div class="border border-gray-200 rounded-lg p-2 max-h-[440px] overflow-y-auto bg-gray-50">
                    @forelse($teams as $team)
                        <button
                            type="button"
                            wire:click="setTeam({{ $team->id }})"
                            class="w-full text-left px-3 py-2 rounded-md mb-1 text-sm transition {{ (int)$selectedTeamId === (int)$team->id ? 'bg-sky-600 text-white' : 'bg-white hover:bg-sky-50 text-gray-700 border border-gray-200' }}"
                        >
                            {{ $team->name }}
                        </button>
                    @empty
                        <div class="text-xs text-gray-500 p-2">Keine Teams vorhanden.</div>
                    @endforelse
                </div>

                <div class="border border-gray-200 rounded-lg p-3 bg-white">
                    @if($selectedTeamId)
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-sm font-medium text-gray-800">
                                {{ $teams->where('id', $selectedTeamId)->first()->name }}
                            </div>
                            <div>
                                <x-ui.buttons.button-basic wire:click="setSelectedTeamToTrue" :size="'sm'" title="alle Rechte aktivieren">
                                    <i class="far fa-check-circle mr-2"></i>
                                    Alle aktivieren
                                </x-ui.buttons.button-basic>
                                <x-ui.buttons.button-basic wire:click="setSelectedTeamToFalse" :size="'sm'" title="alle Rechte entfernen">
                                    <i class="far fa-remove mr-2"></i>
                                    Alle entfernen
                                </x-ui.buttons.button-basic>
                            </div>
                        </div>

                        <div class="font-semibold text-gray-800 mb-2">Berechtigungen</div>
                        <div class="border border-gray-100 rounded-lg p-3 mb-3  max-h-[440px] overflow-y-auto scroll-container">
                            <div class="grid grid-cols-1 gap-3">
                                @foreach($permissionGroups as $groupLabel => $permissions)
                                    <div class="border border-gray-100 rounded-lg p-2">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ $groupLabel }}</div>
                                        <div class="space-y-1">
                                            @foreach($permissions as $permissionItem)
                                                @php
                                                    $permission = $permissionItem['key'];
                                                    $permissionLabel = $permissionItem['label'] ?? $permission;
                                                    $permissionKey = $this->permissionKey($permission);
                                                @endphp
                                                <x-ui.forms.toggle-button
                                                    :id="'perm-'.$selectedTeamId.'-'.str_replace('.', '-', $permission)"
                                                    :model="'matrix.'.$selectedTeamId.'.'.$permissionKey"
                                                    :label="$permissionLabel"
                                                />
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="text-sm text-gray-500">Bitte zuerst ein Team auswaehlen.</div>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-ui.buttons.button-basic wire:click="close" class="mr-2">
            <i class="far fa-times mr-2"></i>
            Schliessen
        </x-ui.buttons.button-basic>
        <x-ui.buttons.button-basic wire:click="save" wire:loading.attr="disabled">
            <i class="fal fa-save mr-2" wire:loading.remove wire:target="save"></i>
            <i class="fal fa-spinner fa-spin mr-2 text-blue-500" wire:loading wire:target="save"></i>
            Speichern
        </x-ui.buttons.button-basic>
    </x-slot>
</x-dialog-modal>
