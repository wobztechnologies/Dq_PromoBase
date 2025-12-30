@php
    $data = $this->getViewData();
    $product = $data['product'];
    $distributors = $data['distributors'];
    $variants = $data['variants'];
@endphp

<div class="fi-ta">
    <div class="space-y-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold mb-2">Stock & Prix - {{ $product->sku }} - {{ $product->name }}</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Vue d'ensemble des stocks et prix pour toutes les variantes de ce produit par distributeur.
            </p>
        </div>

        @if(count($variants) > 0 && count($distributors) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider sticky left-0 bg-gray-50 dark:bg-gray-700 z-10">
                                    Variante
                                </th>
                                @foreach($distributors as $distributor)
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider border-l-2 border-gray-300 dark:border-gray-600">
                                        <div class="flex flex-col items-center">
                                            <span>{{ $distributor->name }}</span>
                                            <div class="grid grid-cols-2 gap-1 mt-1 text-[10px]">
                                                <span class="text-gray-400">Stock</span>
                                                <span class="text-gray-400">Prix</span>
                                            </div>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($variants as $variant)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 whitespace-nowrap sticky left-0 bg-white dark:bg-gray-800 z-10 border-r-2 border-gray-200 dark:border-gray-700">
                                        <div class="text-sm">
                                            @if($variant['colorVariant'])
                                                <div class="font-medium text-blue-600 dark:text-blue-400">
                                                    🎨 {{ $variant['colorVariant']->sku }}
                                                </div>
                                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                                    {{ $variant['colorVariant']->primaryColor->full_name ?? $variant['colorVariant']->primaryColor->name ?? 'Inconnu' }}
                                                </div>
                                            @else
                                                <div class="font-medium">📦 Produit seul</div>
                                            @endif
                                            @if($variant['sizeVariant'])
                                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                    📏 Taille: {{ $variant['sizeVariant']->size->name }} ({{ $variant['sizeVariant']->sku }})
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    @foreach($distributors as $distributor)
                                        @php
                                            $price = $variant['prices'][$distributor->id] ?? null;
                                        @endphp
                                        <td class="px-4 py-3 border-l-2 border-gray-200 dark:border-gray-700">
                                            @if($price)
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div class="text-center">
                                                        <div class="text-sm font-semibold {{ $price->stock > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                            {{ $price->stock }}
                                                        </div>
                                                    </div>
                                                    <div class="text-center">
                                                        @php
                                                            $basePrice = $price->getPriceForQuantity(1);
                                                            $currency = $price->priceTiers->first()->currency ?? 'EUR';
                                                        @endphp
                                                        @if($basePrice !== null)
                                                            <div class="text-sm font-semibold">
                                                                {{ number_format($basePrice, 2, ',', ' ') }} {{ $currency }}
                                                            </div>
                                                            @if($price->priceTiers->count() > 1)
                                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                    ({{ $price->priceTiers->count() }} paliers)
                                                                </div>
                                                            @endif
                                                        @else
                                                            <div class="text-sm text-gray-400">N/A</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @else
                                                <div class="text-center text-gray-400 dark:text-gray-500 text-sm">-</div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center text-gray-500">
                <p>Aucune variante de prix configurée pour ce produit.</p>
            </div>
        @endif
    </div>
</div>
