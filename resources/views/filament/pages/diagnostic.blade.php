<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Bouton Refresh --}}
        <div class="flex justify-end">
            <x-filament::button wire:click="refresh" icon="heroicon-o-arrow-path">
                Actualiser
            </x-filament::button>
        </div>

        {{-- Grille des diagnostics --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($diagnostics as $key => $diagnostic)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                    {{-- Header --}}
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            @if($diagnostic['status'] === 'success')
                                <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                            @elseif($diagnostic['status'] === 'warning')
                                <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            @else
                                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            @endif
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $diagnostic['name'] }}
                            </h3>
                        </div>
                        
                        @if($diagnostic['status'] === 'success')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                <x-heroicon-s-check-circle class="w-4 h-4 mr-1" />
                                Opérationnel
                            </span>
                        @elseif($diagnostic['status'] === 'warning')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                <x-heroicon-s-exclamation-triangle class="w-4 h-4 mr-1" />
                                Attention
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                <x-heroicon-s-x-circle class="w-4 h-4 mr-1" />
                                Erreur
                            </span>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="px-6 py-4">
                        {{-- Message principal --}}
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            {{ $diagnostic['message'] }}
                        </p>

                        {{-- Détails --}}
                        @if(!empty($diagnostic['details']))
                            <div class="space-y-2">
                                <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Détails
                                </h4>
                                <dl class="grid grid-cols-2 gap-2 text-sm">
                                    @foreach($diagnostic['details'] as $label => $value)
                                        <dt class="text-gray-500 dark:text-gray-400 capitalize">
                                            {{ str_replace('_', ' ', $label) }}
                                        </dt>
                                        <dd class="text-gray-900 dark:text-white font-mono text-xs">
                                            {{ $value }}
                                        </dd>
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Légende --}}
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Légende</h4>
            <div class="flex flex-wrap gap-6 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                    <span class="text-gray-600 dark:text-gray-400">Service opérationnel</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <span class="text-gray-600 dark:text-gray-400">Configuration manquante ou avertissement</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <span class="text-gray-600 dark:text-gray-400">Erreur de connexion</span>
                </div>
            </div>
        </div>

        {{-- Informations système --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Informations système
                </h3>
            </div>
            <div class="px-6 py-4">
                <dl class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">PHP Version</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ PHP_VERSION }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Laravel Version</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ app()->version() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Environnement</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ app()->environment() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Debug Mode</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ config('app.debug') ? 'Activé' : 'Désactivé' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cache Driver</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ config('cache.default') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Queue Driver</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ config('queue.default') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Session Driver</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ config('session.driver') }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Filesystem</dt>
                        <dd class="text-gray-900 dark:text-white font-mono">{{ config('filesystems.default') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-filament-panels::page>

