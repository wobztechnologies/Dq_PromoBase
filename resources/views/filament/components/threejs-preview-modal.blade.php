@php
    $modalId = str_replace('-', '_', $modalId ?? 'default');
    $modelUrl = $modelUrl ?? null;
@endphp

@if(!$modelUrl)
    <div class="p-4 text-red-600">
        <p>Erreur : URL du modèle 3D non disponible.</p>
    </div>
@else
<div class="w-full" 
     data-threejs-modal-id="{{ $modalId }}" 
     data-threejs-model-url="{{ $modelUrl }}">
    <div id="threejs-container-{{ $modalId }}" class="w-full h-96 bg-gray-100 rounded-lg relative">
        <div id="threejs-loading-{{ $modalId }}" class="absolute inset-0 flex items-center justify-center z-10 bg-gray-100 rounded-lg">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
                <p class="mt-2 text-sm text-gray-600">Chargement du modèle...</p>
            </div>
        </div>
    </div>
</div>
@endif
