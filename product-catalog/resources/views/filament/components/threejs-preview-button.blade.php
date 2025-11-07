@php
    $modalId = str_replace('-', '_', $productId ?? 'default');
@endphp

<div class="mt-4" data-threejs-modal-id="{{ $modalId }}" data-threejs-model-url="{{ $modelUrl ?? '' }}">
    <button 
        type="button"
        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150"
    >
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        </svg>
        Aperçu du modèle 3D
    </button>
    <div id="threejs-modal-{{ $modalId }}" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" data-threejs-close-modal="{{ $modalId }}"></div>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Aperçu du modèle 3D</h3>
                        <button type="button" data-threejs-close-modal="{{ $modalId }}" class="text-gray-400 hover:text-gray-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div id="threejs-container-{{ $modalId }}" class="w-full h-96 bg-gray-100 rounded-lg relative">
                        <div id="threejs-loading-{{ $modalId }}" class="absolute inset-0 flex items-center justify-center z-10 bg-gray-100 rounded-lg">
                            <div class="text-center">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                                <p class="mt-2 text-sm text-gray-600">Chargement du modèle...</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 text-sm text-gray-500">
                        <p>Utilisez la souris pour faire pivoter, la molette pour zoomer, et maintenez Shift + clic pour déplacer.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
