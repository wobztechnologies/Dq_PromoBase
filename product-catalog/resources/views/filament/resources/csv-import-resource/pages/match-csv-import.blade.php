<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Matching des valeurs</h2>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Pour chaque valeur non trouvée dans la base de données, vous pouvez soit la mapper à une valeur existante, soit créer une nouvelle entité.
            </p>
            
            @if(empty(array_filter($this->unmappedValues)))
                <div class="text-center py-8">
                    <div class="mb-4">
                        <svg class="mx-auto h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Toutes les valeurs sont mappées !</p>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">Vous pouvez maintenant traiter l'import.</p>
                    <x-filament::button
                        wire:click="completeMatching"
                        color="success"
                        size="lg"
                    >
                        Terminer le matching et continuer
                    </x-filament::button>
                </div>
            @else
                @foreach($this->unmappedValues as $mappingType => $values)
                    @if(!empty($values))
                        <div class="mb-6 border-b border-gray-200 dark:border-gray-700 pb-6 last:border-b-0">
                            <h3 class="text-lg font-medium mb-4 capitalize flex items-center gap-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                    {{ str_replace('_', ' ', $mappingType) }}
                                </span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">({{ count($values) }} valeur(s))</span>
                            </h3>
                            
                            <div class="space-y-3">
                                @foreach($values as $value)
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <div class="flex-1">
                                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $value }}</span>
                                        </div>
                                        
                                        @php
                                            $suggestions = $this->getSuggestions($mappingType, $value);
                                        @endphp
                                        
                                        @if(!empty($suggestions))
                                            <div class="flex-1 mx-4">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Suggestions:</p>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($suggestions as $suggestion)
                                                        <x-filament::button
                                                            size="xs"
                                                            color="gray"
                                                            wire:click="mapValue('{{ $mappingType }}', '{{ addslashes($value) }}', '{{ $suggestion['id'] }}')"
                                                            wire:loading.attr="disabled"
                                                        >
                                                            {{ $suggestion['name'] }} ({{ round($suggestion['similarity'] * 100) }}%)
                                                        </x-filament::button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        
                                        <div class="flex gap-2">
                                            <x-filament::button
                                                size="sm"
                                                wire:click="$dispatch('open-modal', { id: 'map-modal-{{ $mappingType }}-{{ md5($value) }}' })"
                                                wire:loading.attr="disabled"
                                            >
                                                Mapper
                                            </x-filament::button>
                                            
                                            <x-filament::button
                                                size="sm"
                                                color="success"
                                                wire:click="$dispatch('open-modal', { id: 'create-modal-{{ $mappingType }}-{{ md5($value) }}' })"
                                                wire:loading.attr="disabled"
                                            >
                                                Créer
                                            </x-filament::button>
                                        </div>
                                    </div>
                                    
                                    {{-- Modal pour mapper --}}
                                    @php
                                        $mapModalId = 'map-modal-' . $mappingType . '-' . md5($value);
                                    @endphp
                                    <x-filament::modal id="{{ $mapModalId }}" width="md">
                                        <x-slot name="heading">
                                            Mapper "{{ $value }}"
                                        </x-slot>
                                        
                                        <x-slot name="description">
                                            Sélectionnez une valeur existante à mapper.
                                        </x-slot>
                                        
                                        <div class="space-y-4">
                                            @livewire('csv-import.map-value-form', [
                                                'mappingType' => $mappingType,
                                                'sourceValue' => $value,
                                                'importId' => $this->record->id
                                            ], key('map-' . $mappingType . '-' . md5($value)))
                                        </div>
                                    </x-filament::modal>
                                    
                                    {{-- Modal pour créer --}}
                                    @php
                                        $createModalId = 'create-modal-' . $mappingType . '-' . md5($value);
                                    @endphp
                                    <x-filament::modal id="{{ $createModalId }}" width="md">
                                        <x-slot name="heading">
                                            Créer "{{ $value }}"
                                        </x-slot>
                                        
                                        <x-slot name="description">
                                            Créez une nouvelle entité pour cette valeur.
                                        </x-slot>
                                        
                                        <div class="space-y-4">
                                            @livewire('csv-import.create-entity-form', [
                                                'mappingType' => $mappingType,
                                                'sourceValue' => $value,
                                                'importId' => $this->record->id
                                            ], key('create-' . $mappingType . '-' . md5($value)))
                                        </div>
                                    </x-filament::modal>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
                
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <x-filament::button
                        wire:click="completeMatching"
                        color="success"
                        size="lg"
                        class="w-full"
                    >
                        Terminer le matching et continuer
                    </x-filament::button>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
