<div>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="flex justify-end gap-2 mt-6">
            <x-filament::button
                type="button"
                color="gray"
                wire:click="$dispatch('close-modal', { id: 'map-modal-' . $mappingType . '-' . md5($sourceValue) })"
            >
                Annuler
            </x-filament::button>
            
            <x-filament::button
                type="submit"
            >
                Mapper
            </x-filament::button>
        </div>
    </form>
</div>
