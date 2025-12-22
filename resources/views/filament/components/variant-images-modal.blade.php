@php
    $imagesCollection = $images ?? collect();
    $imageCount = $imagesCollection->count();
@endphp

@if($imageCount > 0)
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4">
    @foreach($imagesCollection as $image)
        @php
            try {
                $imageUrl = $image->signed_url ?? null;
            } catch (\Exception $e) {
                $imageUrl = null;
            }
        @endphp
        @if($imageUrl)
        <div class="relative group">
            <img 
                src="{{ $imageUrl }}" 
                alt="Image variante" 
                class="w-full h-48 object-cover rounded-lg shadow-md cursor-pointer hover:shadow-lg transition-shadow"
                onclick="openImageModal('{{ addslashes($imageUrl) }}')"
                onerror="this.style.display='none'"
            />
        </div>
        @endif
    @endforeach
</div>
@else
<div class="p-4 text-center text-gray-500">
    <p>Aucune image associée à cette variante.</p>
    <p class="text-xs mt-2">Nombre d'images: {{ $imageCount }}</p>
</div>
@endif

<!-- Modal pour afficher l'image en grand -->
<div id="variant-image-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeImageModal()"></div>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Image de la variante</h3>
                    <button type="button" onclick="closeImageModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex justify-center items-center p-4">
                    <img id="variant-modal-img" src="" alt="Image de la variante" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-lg" loading="lazy" />
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openImageModal(imageUrl) {
    const modal = document.getElementById('variant-image-modal');
    const modalImg = document.getElementById('variant-modal-img');
    if (modal && modalImg) {
        modalImg.src = imageUrl;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeImageModal() {
    const modal = document.getElementById('variant-image-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        const modalImg = document.getElementById('variant-modal-img');
        if (modalImg) {
            modalImg.src = '';
        }
    }
}

// Fermer avec la touche Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeImageModal();
    }
});
</script>

