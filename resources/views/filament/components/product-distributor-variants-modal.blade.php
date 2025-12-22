@php
    use App\Models\ProductVariantPrice;
    
    // Récupérer toutes les variantes de prix pour ce produit et ce distributeur
    $variantPrices = ProductVariantPrice::where('product_id', $product->id)
        ->where('distributor_id', $distributor->id)
        ->with([
            'colorVariant.primaryColor.parent',
            'sizeVariant.size',
            'priceTiers' => function($query) {
                $query->orderBy('quantity_min', 'asc');
            }
        ])
        ->get();
    
    // Organiser les données de manière hiérarchique
    $simpleVariants = []; // Produit seul ou produit + taille (sans couleur)
    $colorVariants = []; // Produit + couleur ou produit + couleur + taille
    
    foreach ($variantPrices as $price) {
        if ($price->colorVariant) {
            // Variante avec couleur
            $colorSku = $price->colorVariant->sku;
            $colorName = $price->colorVariant->primaryColor->full_name ?? $price->colorVariant->primaryColor->name ?? 'Inconnu';
            
            if (!isset($colorVariants[$colorSku])) {
                $colorVariants[$colorSku] = [
                    'colorVariant' => $price->colorVariant,
                    'colorName' => $colorName,
                    'simple' => null, // Prix pour la couleur seule (sans taille)
                    'sizes' => [], // Prix pour chaque taille
                ];
            }
            
            if ($price->sizeVariant) {
                // Produit + couleur + taille
                $colorVariants[$colorSku]['sizes'][] = [
                    'sizeVariant' => $price->sizeVariant,
                    'price' => $price,
                ];
            } else {
                // Produit + couleur (sans taille)
                $colorVariants[$colorSku]['simple'] = $price;
            }
        } else {
            // Variante sans couleur
            if ($price->sizeVariant) {
                // Produit + taille
                $simpleVariants[] = [
                    'sizeVariant' => $price->sizeVariant,
                    'price' => $price,
                ];
            } else {
                // Produit seul
                $simpleVariants[] = [
                    'sizeVariant' => null,
                    'price' => $price,
                ];
            }
        }
    }
@endphp

<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-4">
        <h3 class="font-semibold text-lg mb-2">📦 {{ $product->sku }} - {{ $product->name }}</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">Distributeur: <span class="font-medium">{{ $distributor->name }}</span></p>
    </div>

    @if(count($simpleVariants) > 0 || count($colorVariants) > 0)
        <!-- Variantes simples (produit seul ou produit + taille) -->
        @if(count($simpleVariants) > 0)
            <div class="space-y-2 mb-4">
                <h4 class="font-medium text-base mb-2">Variantes simples</h4>
                @foreach($simpleVariants as $simple)
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded border">
                        <div class="flex-1">
                            @if($simple['sizeVariant'])
                                <span class="text-sm font-medium">Taille: {{ $simple['sizeVariant']->size->name ?? '-' }} ({{ $simple['sizeVariant']->sku }})</span>
                            @else
                                <span class="text-sm font-medium">Produit seul</span>
                            @endif
                            @if($simple['price']->sku_distributor)
                                <span class="text-xs text-gray-500 ml-2">SKU Distributeur: {{ $simple['price']->sku_distributor }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-4">
                            @if($simple['price']->sku_distributor)
                                <span class="text-xs text-gray-600 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                    {{ $simple['price']->sku_distributor }}
                                </span>
                            @endif
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
                                                    onclick="openPriceModal{{ $product->id }}{{ $distributor->id }}('{{ $simple['price']->id }}')"
                                                    class="text-blue-600 hover:text-blue-800 cursor-pointer transition-colors"
                                                    title="Voir les grilles de prix"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
                                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <!-- Variantes avec couleur -->
        @if(count($colorVariants) > 0)
            <div class="space-y-3">
                <h4 class="font-medium text-base mb-2">Variantes avec couleur</h4>
                @foreach($colorVariants as $colorSku => $colorData)
                    <div class="border-l-2 border-blue-400 pl-3">
                        <!-- Couleur -->
                        <div class="font-medium text-base mb-2">
                            🎨 {{ $colorSku }} - {{ $colorData['colorName'] }}
                        </div>
                        
                        <!-- Prix pour la couleur seule (sans taille) -->
                        @if($colorData['simple'])
                            <div class="ml-4 mb-2 flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded border">
                                <span class="text-sm">
                                    Couleur seule
                                    @if($colorData['simple']->sku_distributor)
                                        <span class="text-xs text-gray-500 ml-2">SKU Distributeur: {{ $colorData['simple']->sku_distributor }}</span>
                                    @endif
                                </span>
                                <div class="flex items-center gap-4">
                                    @if($colorData['simple']->sku_distributor)
                                        <span class="text-xs text-gray-600 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                            {{ $colorData['simple']->sku_distributor }}
                                        </span>
                                    @endif
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
                                                        onclick="openPriceModal{{ $product->id }}{{ $distributor->id }}('{{ $colorData['simple']->id }}')"
                                                        class="text-blue-600 hover:text-blue-800 cursor-pointer transition-colors"
                                                        title="Voir les grilles de prix"
                                                    >
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </button>
                                                @endif
                                </div>
                            </div>
                        @endif
                        
                        <!-- Tailles pour cette couleur -->
                        @if(count($colorData['sizes']) > 0)
                            <div class="ml-4 space-y-1">
                                @foreach($colorData['sizes'] as $sizeData)
                                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded border">
                                        <span class="text-sm">
                                            Taille: <span class="font-medium">{{ $sizeData['sizeVariant']->size->name ?? '-' }}</span> ({{ $sizeData['sizeVariant']->sku }})
                                            @if($sizeData['price']->sku_distributor)
                                                <span class="text-xs text-gray-500 ml-2">SKU Distributeur: {{ $sizeData['price']->sku_distributor }}</span>
                                            @endif
                                        </span>
                                        <div class="flex items-center gap-4">
                                            @if($sizeData['price']->sku_distributor)
                                                <span class="text-xs text-gray-600 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                                    {{ $sizeData['price']->sku_distributor }}
                                                </span>
                                            @endif
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
                                                    onclick="openPriceModal{{ $product->id }}{{ $distributor->id }}('{{ $sizeData['price']->id }}')"
                                                    class="text-blue-600 hover:text-blue-800 cursor-pointer transition-colors"
                                                    title="Voir les grilles de prix"
                                                >
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>
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
    @else
        <div class="text-center text-gray-500 p-8">
            <p>Aucune variante de prix configurée pour ce produit chez ce distributeur.</p>
        </div>
    @endif
</div>

<!-- Modal pour afficher les grilles de prix -->
<div id="price-modal-{{ $product->id }}-{{ $distributor->id }}" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-labelledby="price-modal-title-{{ $product->id }}-{{ $distributor->id }}" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closePriceModal()"></div>
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="price-modal-title-{{ $product->id }}-{{ $distributor->id }}">Grilles de prix</h3>
                    <button type="button" onclick="closePriceModal{{ $product->id }}{{ $distributor->id }}()" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div id="price-modal-content-{{ $product->id }}-{{ $distributor->id }}" class="mt-4">
                    <!-- Le contenu sera chargé dynamiquement -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openPriceModal{{ $product->id }}{{ $distributor->id }}(variantPriceId) {
    fetch(`/admin/product-variant-prices/${variantPriceId}/price-tiers`)
        .then(response => response.json())
        .then(data => {
            const modal = document.getElementById('price-modal-{{ $product->id }}-{{ $distributor->id }}');
            const modalContent = document.getElementById('price-modal-content-{{ $product->id }}-{{ $distributor->id }}');
            const modalTitle = document.getElementById('price-modal-title-{{ $product->id }}-{{ $distributor->id }}');
            
            if (modal && modalContent && modalTitle) {
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

function closePriceModal{{ $product->id }}{{ $distributor->id }}() {
    const modal = document.getElementById('price-modal-{{ $product->id }}-{{ $distributor->id }}');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePriceModal{{ $product->id }}{{ $distributor->id }}();
    }
});
</script>
