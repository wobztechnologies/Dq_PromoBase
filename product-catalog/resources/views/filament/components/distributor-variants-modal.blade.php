@php
    use App\Models\ProductVariantPrice;
    
    // Récupérer toutes les variantes de prix pour ce distributeur
    $variantPrices = ProductVariantPrice::where('distributor_id', $distributor->id)
        ->with([
            'product',
            'colorVariant.primaryColor.parent',
            'sizeVariant.size',
            'priceTiers' => function($query) {
                $query->orderBy('quantity_min', 'asc');
            }
        ])
        ->get()
        ->groupBy('product_id');
    
    // Organiser les données de manière hiérarchique
    $hierarchicalData = [];
    foreach ($variantPrices as $productId => $prices) {
        $product = $prices->first()->product;
        $productData = [
            'product' => $product,
            'simple_variants' => [], // Produit seul ou produit + taille (sans couleur)
            'color_variants' => [], // Produit + couleur ou produit + couleur + taille
        ];
        
        foreach ($prices as $price) {
            if ($price->colorVariant) {
                // Variante avec couleur
                $colorSku = $price->colorVariant->sku;
                $colorName = $price->colorVariant->primaryColor->full_name ?? $price->colorVariant->primaryColor->name ?? 'Inconnu';
                
                if (!isset($productData['color_variants'][$colorSku])) {
                    $productData['color_variants'][$colorSku] = [
                        'colorVariant' => $price->colorVariant,
                        'colorName' => $colorName,
                        'simple' => null, // Prix pour la couleur seule (sans taille)
                        'sizes' => [], // Prix pour chaque taille
                    ];
                }
                
                if ($price->sizeVariant) {
                    // Produit + couleur + taille
                    $productData['color_variants'][$colorSku]['sizes'][] = [
                        'sizeVariant' => $price->sizeVariant,
                        'price' => $price,
                    ];
                } else {
                    // Produit + couleur (sans taille)
                    $productData['color_variants'][$colorSku]['simple'] = $price;
                }
            } else {
                // Variante sans couleur
                if ($price->sizeVariant) {
                    // Produit + taille
                    $productData['simple_variants'][] = [
                        'sizeVariant' => $price->sizeVariant,
                        'price' => $price,
                    ];
                } else {
                    // Produit seul
                    $productData['simple_variants'][] = [
                        'sizeVariant' => null,
                        'price' => $price,
                    ];
                }
            }
        }
        
        $hierarchicalData[] = $productData;
    }
@endphp

<div class="space-y-4">
    @if(count($hierarchicalData) > 0)
        @foreach($hierarchicalData as $productData)
            <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
                <!-- Produit -->
                <div class="font-semibold text-lg mb-3">
                    📦 {{ $productData['product']->sku }} - {{ $productData['product']->name }}
                </div>
                
                <!-- Variantes simples (produit seul ou produit + taille) -->
                @if(count($productData['simple_variants']) > 0)
                    <div class="ml-4 space-y-2 mb-3">
                        @foreach($productData['simple_variants'] as $simple)
                            <div class="flex items-center justify-between p-2 bg-white dark:bg-gray-700 rounded border">
                                <div class="flex-1">
                                    @if($simple['sizeVariant'])
                                        <span class="text-sm font-medium">Taille: {{ $simple['sizeVariant']->size->name ?? '-' }} ({{ $simple['sizeVariant']->sku }})</span>
                                    @else
                                        <span class="text-sm font-medium">Produit seul</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="text-sm">
                                        Stock: <span class="font-semibold {{ $simple['price']->stock > 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $simple['price']->stock }}
                                        </span>
                                    </span>
                                    <span class="text-sm">
                                        Prix: <span class="font-semibold">
                                            @php
                                                $basePrice = $simple['price']->getPriceForQuantity(1);
                                                $currency = $simple['price']->priceTiers->first()->currency ?? 'EUR';
                                            @endphp
                                            @if($basePrice !== null)
                                                {{ number_format($basePrice, 2, ',', ' ') }} {{ $currency }}
                                            @else
                                                <span class="text-gray-400">N/A</span>
                                            @endif
                                        </span>
                                    </span>
                                            @if($simple['price']->priceTiers->count() > 0)
                                                <button 
                                                    type="button"
                                                    onclick="openPriceModal('{{ $simple['price']->id }}')"
                                                    class="text-blue-600 hover:text-blue-800 cursor-pointer transition-colors"
                                                    title="Voir les grilles de prix"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            @else
                                                <span class="text-gray-400 cursor-not-allowed" title="Aucune grille de prix">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </span>
                                            @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                
                <!-- Variantes avec couleur -->
                @if(count($productData['color_variants']) > 0)
                    <div class="ml-4 space-y-3">
                        @foreach($productData['color_variants'] as $colorSku => $colorData)
                            <div class="border-l-2 border-blue-400 pl-3">
                                <!-- Couleur -->
                                <div class="font-medium text-base mb-2">
                                    🎨 {{ $colorSku }} - {{ $colorData['colorName'] }}
                                </div>
                                
                                <!-- Prix pour la couleur seule (sans taille) -->
                                @if($colorData['simple'])
                                    <div class="ml-4 mb-2 flex items-center justify-between p-2 bg-white dark:bg-gray-700 rounded border">
                                        <span class="text-sm">Couleur seule</span>
                                        <div class="flex items-center gap-4">
                                            <span class="text-sm">
                                                Stock: <span class="font-semibold {{ $colorData['simple']->stock > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $colorData['simple']->stock }}
                                                </span>
                                            </span>
                                            <span class="text-sm">
                                                Prix: <span class="font-semibold">
                                                    @php
                                                        $basePrice = $colorData['simple']->getPriceForQuantity(1);
                                                        $currency = $colorData['simple']->priceTiers->first()->currency ?? 'EUR';
                                                    @endphp
                                                    @if($basePrice !== null)
                                                        {{ number_format($basePrice, 2, ',', ' ') }} {{ $currency }}
                                                    @else
                                                        <span class="text-gray-400">N/A</span>
                                                    @endif
                                                </span>
                                            </span>
                                            @if($colorData['simple']->priceTiers->count() > 0)
                                                <button 
                                                    type="button"
                                                    onclick="openPriceModal('{{ $colorData['simple']->id }}')"
                                                    class="text-blue-600 hover:text-blue-800 cursor-pointer transition-colors"
                                                    title="Voir les grilles de prix"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            @else
                                                <span class="text-gray-400 cursor-not-allowed" title="Aucune grille de prix">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                                
                                <!-- Tailles pour cette couleur -->
                                @if(count($colorData['sizes']) > 0)
                                    <div class="ml-4 space-y-1">
                                        @foreach($colorData['sizes'] as $sizeData)
                                            <div class="flex items-center justify-between p-2 bg-white dark:bg-gray-700 rounded border">
                                                <span class="text-sm">
                                                    Taille: <span class="font-medium">{{ $sizeData['sizeVariant']->size->name ?? '-' }}</span> ({{ $sizeData['sizeVariant']->sku }})
                                                </span>
                                                <div class="flex items-center gap-4">
                                                    <span class="text-sm">
                                                        Stock: <span class="font-semibold {{ $sizeData['price']->stock > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                            {{ $sizeData['price']->stock }}
                                                        </span>
                                                    </span>
                                                    <span class="text-sm">
                                                        Prix: <span class="font-semibold">
                                                            @php
                                                                $basePrice = $sizeData['price']->getPriceForQuantity(1);
                                                                $currency = $sizeData['price']->priceTiers->first()->currency ?? 'EUR';
                                                            @endphp
                                                            @if($basePrice !== null)
                                                                {{ number_format($basePrice, 2, ',', ' ') }} {{ $currency }}
                                                            @else
                                                                <span class="text-gray-400">N/A</span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                    @if($sizeData['price']->priceTiers->count() > 0)
                                                        <button 
                                                            type="button"
                                                            onclick="openPriceModal('{{ $sizeData['price']->id }}')"
                                                            class="text-blue-600 hover:text-blue-800 cursor-pointer transition-colors"
                                                            title="Voir les grilles de prix"
                                                        >
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </button>
                                                    @else
                                                        <span class="text-gray-400 cursor-not-allowed" title="Aucune grille de prix">
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="text-center text-gray-500 p-8">
            <p>Aucune variante de prix configurée pour ce distributeur.</p>
        </div>
    @endif
</div>

<!-- Modal pour afficher les grilles de prix -->
<div id="price-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="price-modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closePriceModal()"></div>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="price-modal-title">Grilles de prix</h3>
                    <button type="button" onclick="closePriceModal()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="price-modal-content" class="mt-4">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openPriceModal(variantPriceId) {
    // Charger les données des prix via une requête AJAX
    fetch(`/admin/product-variant-prices/${variantPriceId}/price-tiers`)
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('price-modal');
            const modalContent = document.getElementById('price-modal-content');
            const modalTitle = document.getElementById('price-modal-title');
            
            if (modal && modalContent) {
                modalTitle.textContent = 'Grilles de prix - ' + (data.variant_sku || 'N/A');
                
                if (data.tiers && data.tiers.length > 0) {
                    let html = '<div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                    html += '<thead class="bg-gray-50 dark:bg-gray-700"><tr>';
                    html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantité min</th>';
                    html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Quantité max</th>';
                    html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prix unitaire</th>';
                    html += '</tr></thead><tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">';
                    
                    data.tiers.forEach(tier => {
                        html += '<tr>';
                        html += '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">' + tier.quantity_min + '</td>';
                        html += '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">' + (tier.quantity_max ?? '∞') + '</td>';
                        html += '<td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">' + parseFloat(tier.unit_price).toFixed(2).replace('.', ',') + ' ' + tier.currency + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    modalContent.innerHTML = html;
                } else {
                    modalContent.innerHTML = '<p class="text-gray-500 dark:text-gray-400 text-center py-4">Aucune grille de prix configurée.</p>';
                }
                
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des prix:', error);
            alert('Erreur lors du chargement des grilles de prix.');
        });
}

function closePriceModal() {
    const modal = document.getElementById('price-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// Fermer avec la touche Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePriceModal();
    }
});
</script>
