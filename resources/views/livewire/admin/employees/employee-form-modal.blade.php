<x-dialog-modal wire:model="showModal" maxWidth="2xl">
    <x-slot name="title">
        {{ $userId ? 'Mitarbeiter bearbeiten' : 'Mitarbeiter anlegen' }}
    </x-slot>

    <x-slot name="content">
        <div class="space-y-4">
            <div class="text-sm text-gray-600">
                Stammdaten und Teamzuordnung fuer Mitarbeiterkonto verwalten.
            </div>

            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                <div class="text-sm font-semibold text-gray-800 mb-3">Basisdaten</div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <x-ui.forms.label value="Name"/>
                        <x-ui.forms.input type="text" wire:model.live.defer="name"/>
                        <x-ui.forms.input-error for="name"/>
                    </div>

                    <div>
                        <x-ui.forms.label value="E-Mail"/>
                        <x-ui.forms.input type="email" wire:model.live.defer="email"/>
                        <x-ui.forms.input-error for="email"/>
                    </div>
                </div>
            </div>

            <div class="border border-gray-200 rounded-lg p-4 bg-white space-y-4">
                <div class="text-sm font-semibold text-gray-800">Team und Sicherheit</div>

                <div class="space-y-1">
                    <x-ui.forms.label value="Team"/>
                    <x-ui.forms.select wire:model.live.defer="primary_team_id" placeholder="-- bitte wählen --">
                        @foreach($teams as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </x-ui.forms.select>
                    <x-ui.forms.input-error for="primary_team_id"/>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <x-ui.forms.label value="{{ $userId ? 'Neues Passwort (optional)' : 'Passwort' }}"/>
                        <x-ui.forms.input type="password" wire:model.live.defer="password" autocomplete="new-password"/>
                        <x-ui.forms.input-error for="password"/>
                    </div>
                    <div>
                        <x-ui.forms.label value="Passwort bestaetigen"/>
                        <x-ui.forms.input type="password" wire:model.live.defer="password_confirmation" autocomplete="new-password"/>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <x-slot name="footer">
        <x-ui.buttons.button-basic wire:click="close" class="mr-2" size="sm">
            <i class="far fa-times mr-2"></i>
            Schliessen
        </x-ui.buttons.button-basic>
        <x-ui.buttons.button-basic wire:click="save" wire:loading.attr="disabled" size="sm">
            <i class="fal fa-save mr-2" wire:loading.remove wire:target="save"></i>
            <i class="fal fa-spinner fa-spin mr-2 text-blue-500" wire:loading wire:target="save"></i>
            Speichern
        </x-ui.buttons.button-basic>
    </x-slot>
</x-dialog-modal>
