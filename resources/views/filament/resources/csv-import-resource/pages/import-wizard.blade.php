<x-filament-panels::page>
    {{-- Indicateur d'étapes --}}
    <div class="mb-6">
        <nav aria-label="Progress">
            <ol role="list" class="flex items-center justify-center">
                @foreach([1 => 'Configuration', 2 => 'Mapping', 3 => 'Valeurs manquantes', 4 => 'Validation'] as $step => $label)
                    <li class="relative {{ $step < 4 ? 'pr-8 sm:pr-20' : '' }}">
                        @if($step < 4)
                            {{-- Ligne de connexion --}}
                            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                <div class="h-0.5 w-full {{ $this->currentStep > $step ? 'bg-primary-600' : 'bg-gray-200' }}"></div>
                            </div>
                        @endif
                        
                        <div class="relative flex items-center justify-center">
                            @if($this->currentStep > $step)
                                {{-- Étape complétée --}}
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-600">
                                    <x-heroicon-s-check class="h-6 w-6 text-white" />
                                </span>
                            @elseif($this->currentStep === $step)
                                {{-- Étape actuelle --}}
                                <span class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-primary-600 bg-white">
                                    <span class="text-primary-600 font-semibold">{{ $step }}</span>
                                </span>
                            @else
                                {{-- Étape future --}}
                                <span class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-gray-300 bg-white">
                                    <span class="text-gray-500">{{ $step }}</span>
                                </span>
                            @endif
                        </div>
                        
                        <span class="absolute -bottom-6 left-1/2 -translate-x-1/2 whitespace-nowrap text-xs {{ $this->currentStep >= $step ? 'text-primary-600 font-medium' : 'text-gray-500' }}">
                            {{ $label }}
                        </span>
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>
    
    {{-- Espacement pour les labels --}}
    <div class="h-6"></div>
    
    {{-- Formulaire --}}
    <form wire:submit.prevent="executeImport">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
