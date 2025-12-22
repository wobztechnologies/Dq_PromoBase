<!-- Modal pour afficher les grilles de prix -->
<div id="price-tiers-modal-{{ $productId }}" 
     class="hidden fixed inset-0 z-[9999] overflow-y-auto" 
     style="display: none;" 
     aria-labelledby="price-tiers-modal-title-{{ $productId }}" 
     role="dialog" 
     aria-modal="true"
     x-data="{
         show: false,
         loading: false,
         tiers: [],
         variantSku: '',
         close() {
             this.show = false;
             document.getElementById('price-tiers-modal-{{ $productId }}').style.display = 'none';
             document.getElementById('price-tiers-modal-{{ $productId }}').classList.add('hidden');
             document.body.style.overflow = '';
         },
         async open(variantPriceId) {
             console.log('Opening modal for variant price:', variantPriceId);
             this.show = true;
             this.loading = true;
             document.getElementById('price-tiers-modal-{{ $productId }}').style.display = 'flex';
             document.getElementById('price-tiers-modal-{{ $productId }}').classList.remove('hidden');
             document.body.style.overflow = 'hidden';
             
             try {
                 const response = await fetch(`/admin/product-variant-prices/${variantPriceId}/price-tiers`);
                 if (!response.ok) throw new Error('Network response was not ok');
                 const data = await response.json();
                 console.log('Price tiers data:', data);
                 this.tiers = data.tiers || [];
                 this.variantSku = data.variant_sku || 'N/A';
             } catch (error) {
                 console.error('Erreur:', error);
                 alert('Erreur lors du chargement des grilles de prix.');
             } finally {
                 this.loading = false;
             }
         }
     }"
     @keydown.escape.window="close()">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close()"></div>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="price-tiers-modal-title-{{ $productId }}">
                        <span x-text="'Grilles de prix - ' + variantSku"></span>
                    </h3>
                    <button type="button" class="text-gray-400 hover:text-gray-500" @click="close()">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="price-tiers-modal-content-{{ $productId }}" class="mt-4">
                    <template x-if="loading">
                        <div class="text-center py-4">
                            <p class="text-gray-500 dark:text-gray-400">Chargement...</p>
                        </div>
                    </template>
                    <template x-if="!loading && tiers.length === 0">
                        <p class="text-gray-500 dark:text-gray-400 text-center py-4">Aucune grille de prix configurée.</p>
                    </template>
                    <template x-if="!loading && tiers.length > 0">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantité min</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantité max</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prix unitaire</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <template x-for="tier in tiers" :key="tier.id">
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" x-text="tier.quantity_min"></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100" x-text="tier.quantity_max ?? '∞'"></td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="parseFloat(tier.unit_price).toFixed(2).replace('.', ',') + ' ' + tier.currency"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonction globale pour ouvrir la modal
window.openPriceTiersModalForProduct_{{ str_replace('-', '', $productId) }} = function(variantPriceId) {
    console.log('Opening modal for variant price:', variantPriceId, 'Product ID:', '{{ $productId }}');
    const modal = document.getElementById('price-tiers-modal-{{ $productId }}');
    
    if (!modal) {
        console.error('Modal element not found:', 'price-tiers-modal-{{ $productId }}');
        alert('Erreur : modal non trouvée');
        return;
    }
    
    // Attendre que Alpine.js soit initialisé
    let attempts = 0;
    function tryOpen() {
        attempts++;
        if (modal.__x && modal.__x.$data && typeof modal.__x.$data.open === 'function') {
            console.log('Alpine.js data found, opening modal');
            modal.__x.$data.open(variantPriceId);
        } else if (attempts < 20) {
            console.log('Alpine.js not ready, retrying... (attempt ' + attempts + ')');
            setTimeout(tryOpen, 100);
        } else {
            console.error('Alpine.js failed to initialize after 20 attempts');
            alert('Erreur : impossible d\'ouvrir la modal. Alpine.js non initialisé.');
        }
    }
    
    tryOpen();
};

console.log('Price tiers modal function created: openPriceTiersModalForProduct_{{ str_replace('-', '', $productId) }}');
</script>
