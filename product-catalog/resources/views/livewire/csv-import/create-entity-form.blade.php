<div>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="flex justify-end gap-2 mt-6">
            <x-filament::button
                type="button"
                color="gray"
                wire:click="$dispatch('close-modal', { id: 'create-modal-' . $mappingType . '-' . md5($sourceValue) })"
            >
                Annuler
            </x-filament::button>
            
            <x-filament::button
                type="submit"
                color="success"
            >
                Créer
            </x-filament::button>
        </div>
    </form>
</div>
