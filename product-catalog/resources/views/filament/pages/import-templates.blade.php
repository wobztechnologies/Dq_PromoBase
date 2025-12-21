<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Introduction --}}
        <x-filament::section>
            <x-slot name="heading">
                Modèles d'import CSV
            </x-slot>
            <x-slot name="description">
                Téléchargez les modèles CSV pour chaque type d'import. Ces modèles contiennent les en-têtes requis et un exemple de données pour vous aider à préparer vos fichiers d'import.
            </x-slot>
            
            <div class="prose dark:prose-invert max-w-none">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <strong>Conseils :</strong>
                </p>
                <ul class="text-sm text-gray-600 dark:text-gray-400 list-disc pl-5 space-y-1">
                    <li>Utilisez le séparateur <code>;</code> (point-virgule) ou <code>,</code> (virgule) - le système détecte automatiquement</li>
                    <li>Encodez vos fichiers en UTF-8 pour les caractères spéciaux</li>
                    <li>La première ligne doit contenir les en-têtes exactement comme dans le modèle</li>
                    <li>Les champs optionnels peuvent être laissés vides</li>
                </ul>
            </div>
        </x-filament::section>

        {{-- Grille des modèles --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($this->getImportTypes() as $importType)
                <x-filament::section class="h-full">
                    <div class="flex flex-col h-full">
                        {{-- Header avec icône --}}
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-{{ $importType['color'] }}-100 dark:bg-{{ $importType['color'] }}-900/20">
                                <x-dynamic-component 
                                    :component="$importType['icon']" 
                                    class="w-6 h-6 text-{{ $importType['color'] }}-600 dark:text-{{ $importType['color'] }}-400"
                                />
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $importType['name'] }}
                            </h3>
                        </div>

                        {{-- Description --}}
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 flex-grow">
                            {{ $importType['description'] }}
                        </p>

                        {{-- Champs requis --}}
                        <div class="mb-4">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Colonnes :</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($importType['fields'] as $field)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                                        {{ $field }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        {{-- Bouton de téléchargement --}}
                        <div class="mt-auto pt-3 border-t border-gray-200 dark:border-gray-700">
                            <a 
                                href="{{ route('csv-import.template', ['type' => $importType['type']]) }}{{ $importType['mode'] ? '?mode=' . $importType['mode'] : '' }}"
                                class="inline-flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-semibold rounded-lg transition-colors
                                    bg-primary-600 hover:bg-primary-500 text-white
                                    dark:bg-primary-500 dark:hover:bg-primary-400"
                            >
                                <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                                <span>Télécharger le modèle</span>
                            </a>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>

        {{-- Section d'aide supplémentaire --}}
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                Guide des champs
            </x-slot>
            
            <div class="prose dark:prose-invert max-w-none">
                <h4>Champs communs</h4>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left py-2">Champ</th>
                            <th class="text-left py-2">Description</th>
                            <th class="text-left py-2">Exemple</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="py-2"><code>sku</code></td>
                            <td class="py-2">Code unique du produit</td>
                            <td class="py-2">PROD-001</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name</code></td>
                            <td class="py-2">Nom de l'élément</td>
                            <td class="py-2">T-Shirt Coton Bio</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>category_name</code></td>
                            <td class="py-2">Nom de la catégorie (doit exister)</td>
                            <td class="py-2">Vêtements</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>manufacturer_name</code></td>
                            <td class="py-2">Nom du fabricant (doit exister)</td>
                            <td class="py-2">Kariban</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>color_name</code></td>
                            <td class="py-2">Couleur fabricant (optionnel)</td>
                            <td class="py-2">Ash Heather</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>primary_color_name</code></td>
                            <td class="py-2">Couleur principale (optionnel)</td>
                            <td class="py-2">Gris</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>size_name</code></td>
                            <td class="py-2">Taille du produit</td>
                            <td class="py-2">XL</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>hex_code</code></td>
                            <td class="py-2">Code couleur hexadécimal</td>
                            <td class="py-2">#FF5733</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>image_X_url</code></td>
                            <td class="py-2">URL de l'image (X de 1 à 8)</td>
                            <td class="py-2">https://...</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="mt-6">Traductions (Catégories)</h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                    Les colonnes de traduction sont optionnelles. Si renseignées, elles permettent d'afficher le nom de la catégorie dans la langue de l'utilisateur.
                </p>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left py-2">Champ</th>
                            <th class="text-left py-2">Langue</th>
                            <th class="text-left py-2">Exemple</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="py-2"><code>name_fr</code></td>
                            <td class="py-2">Français</td>
                            <td class="py-2">Chaussures</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name_en</code></td>
                            <td class="py-2">Anglais</td>
                            <td class="py-2">Shoes</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name_de</code></td>
                            <td class="py-2">Allemand</td>
                            <td class="py-2">Schuhe</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name_es</code></td>
                            <td class="py-2">Espagnol</td>
                            <td class="py-2">Zapatos</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name_it</code></td>
                            <td class="py-2">Italien</td>
                            <td class="py-2">Scarpe</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name_nl</code></td>
                            <td class="py-2">Néerlandais</td>
                            <td class="py-2">Schoenen</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name_pt</code></td>
                            <td class="py-2">Portugais</td>
                            <td class="py-2">Sapatos</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>name_pl</code></td>
                            <td class="py-2">Polonais</td>
                            <td class="py-2">Buty</td>
                        </tr>
                    </tbody>
                </table>

                <h4 class="mt-6">Champs couleurs (Pantone)</h4>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="text-left py-2">Champ</th>
                            <th class="text-left py-2">Description</th>
                            <th class="text-left py-2">Exemple</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr>
                            <td class="py-2"><code>color_sku_code</code></td>
                            <td class="py-2">Code SKU de la couleur</td>
                            <td class="py-2">ASH</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>rgb</code></td>
                            <td class="py-2">Valeur RGB</td>
                            <td class="py-2">125,150,199</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>pantone_c</code></td>
                            <td class="py-2">Code Pantone C</td>
                            <td class="py-2">652C</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>pantone_tcx</code></td>
                            <td class="py-2">Code Pantone TCX</td>
                            <td class="py-2">16-4030TCX</td>
                        </tr>
                        <tr>
                            <td class="py-2"><code>pms</code></td>
                            <td class="py-2">Code PMS</td>
                            <td class="py-2">PMS 652</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
