
<div id="threejs-modal-{{ $id }}" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeThreeJSModal{{ $id }}()"></div>

        <!-- Modal panel -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Aperçu du modèle 3D
                    </h3>
                    <button type="button" onclick="closeThreeJSModal{{ $id }}()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="threejs-container-{{ $id }}" class="w-full h-96 bg-gray-100 rounded-lg relative">
                    <div id="threejs-loading-{{ $id }}" class="absolute inset-0 flex items-center justify-center z-10 bg-gray-100 rounded-lg">
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
