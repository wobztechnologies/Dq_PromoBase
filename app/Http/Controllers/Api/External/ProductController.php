<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductColorVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/products",
     *     summary="Liste des produits",
     *     description="Retourne la liste des produits avec 2 types: 'general' (produit simple sans variante de couleur) et 'variant' (variante de couleur d'un produit).",
     *     operationId="getProducts",
     *     tags={"Produits"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre de produits par page (max 500)",
     *         @OA\Schema(type="integer", default=100, maximum=500)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type de produit: 'general' (produits simples uniquement), 'variant' (variantes de couleur uniquement), ou vide pour tous",
     *         @OA\Schema(type="string", enum={"general", "variant"})
     *     ),
     *     @OA\Parameter(
     *         name="color",
     *         in="query",
     *         description="Filtrer par UUID de couleur principale",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="manufacturer_color",
     *         in="query",
     *         description="Filtrer par UUID de couleur fabricant",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="manufacturer",
     *         in="query",
     *         description="Filtrer par UUID de fabricant",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filtrer par UUID de catégorie (inclut automatiquement les produits des sous-catégories)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des produits",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="type", type="string", enum={"general", "variant"}, description="Type de produit: 'general' = produit simple, 'variant' = variante de couleur"),
     *                 @OA\Property(property="product_type", type="string", enum={"general", "variant"}, description="Type de produit (identique à 'type')"),
     *                 @OA\Property(property="sku", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="category_name", type="string"),
     *                 @OA\Property(property="category_uuid", type="string"),
     *                 @OA\Property(property="manufacturer", type="string"),
     *                 @OA\Property(property="principal_color", type="string", nullable=true, description="Nom de la couleur principale"),
     *                 @OA\Property(property="principal_color_hex", type="string", nullable=true, description="Code hexadécimal de la couleur principale (ex: #FF0000)"),
     *                 @OA\Property(property="manufacturer_color", type="string", nullable=true, description="Nom de la couleur fabricant"),
     *                 @OA\Property(property="manufacturer_color_hex", type="string", nullable=true, description="Code hexadécimal de la couleur fabricant"),
     *                 @OA\Property(property="images", type="array", @OA\Items(
     *                     @OA\Property(property="url", type="string", description="URL présignée de l'image (valide 24h)"),
     *                     @OA\Property(property="position", type="string", enum={"Front", "Back", "Left", "Right", "Top", "Bottom", "Part Zoom"}, nullable=true, description="Position/vue de l'image"),
     *                     @OA\Property(property="neutral_background", type="boolean", description="True si l'image a un fond neutre (blanc, gris, etc.)"),
     *                     @OA\Property(property="product_only", type="boolean", description="True si l'image montre uniquement le produit (pas de mise en situation)")
     *                 )),
     *                 @OA\Property(property="model_3d", type="object", nullable=true, description="Modèle 3D associé",
     *                     @OA\Property(property="uuid", type="string", description="UUID du modèle 3D"),
     *                     @OA\Property(property="status", type="string", enum={"Requested", "Error", "InReview", "Published"}, description="Statut: Requested=en cours de génération, Error=erreur, InReview=en attente de validation, Published=disponible"),
     *                     @OA\Property(property="url", type="string", nullable=true, description="URL présignée du fichier GLB (valide 24h, null si status!=Published)")
     *                 ),
     *                 @OA\Property(property="variant_uuid", type="string", nullable=true, description="UUID de la variante (null si type=general)"),
     *                 @OA\Property(property="product_uuid", type="string", description="UUID du produit parent")
     *             )),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 100), 500);
        
        // Filtres
        $colorFilter = $request->get('color');
        $manufacturerColorFilter = $request->get('manufacturer_color');
        $manufacturerFilter = $request->get('manufacturer');
        $categoryFilter = $request->get('category');
        $typeFilter = $request->get('type'); // 'general', 'variant', ou null pour tous

        // Si un filtre catégorie est spécifié, récupérer toutes les catégories enfants
        $categoryIds = null;
        if ($categoryFilter) {
            $categoryIds = $this->getCategoryWithChildrenIds($categoryFilter);
        }

        // Collection pour stocker tous les résultats
        $allProducts = collect();

        // 1. Produits simples (sans variantes de couleur) - product_type = "general"
        // Inclus si type=null ou type=general
        if (!$typeFilter || $typeFilter === 'general') {
            $simpleProductsQuery = Product::whereDoesntHave('colorVariants')
                ->with(['category', 'manufacturer', 'primaryColor.parent', 'images', 'defaultModel3D']);
            
            // Appliquer les filtres aux produits simples
            if ($manufacturerFilter) {
                $simpleProductsQuery->where('manufacturer_id', $manufacturerFilter);
            }
            if ($categoryIds) {
                $simpleProductsQuery->whereIn('category_id', $categoryIds);
            }
            if ($colorFilter) {
                $simpleProductsQuery->whereHas('primaryColor', function ($q) use ($colorFilter) {
                    $q->where('parent_id', $colorFilter)->orWhere('id', $colorFilter);
                });
            }
            if ($manufacturerColorFilter) {
                $simpleProductsQuery->where('primary_color_id', $manufacturerColorFilter);
            }

            $simpleProducts = $simpleProductsQuery->get();
            
            foreach ($simpleProducts as $product) {
                $allProducts->push($this->formatSimpleProduct($product, 'general'));
            }
        }

        // 2. Variantes de couleur (chaque variante = 1 produit) - product_type = "variant"
        // Inclus si type=null ou type=variant
        if (!$typeFilter || $typeFilter === 'variant') {
            $variantsQuery = ProductColorVariant::with([
                'product.category',
                'product.manufacturer',
                'product.defaultModel3D',
                'primaryColor.parent',
                'productImages',
                'models3d',
            ]);
            
            // Appliquer les filtres aux variantes
            if ($manufacturerFilter) {
                $variantsQuery->whereHas('product', function ($q) use ($manufacturerFilter) {
                    $q->where('manufacturer_id', $manufacturerFilter);
                });
            }
            if ($categoryIds) {
                $variantsQuery->whereHas('product', function ($q) use ($categoryIds) {
                    $q->whereIn('category_id', $categoryIds);
                });
            }
            if ($colorFilter) {
                $variantsQuery->whereHas('primaryColor', function ($q) use ($colorFilter) {
                    $q->where('parent_id', $colorFilter)->orWhere('id', $colorFilter);
                });
            }
            if ($manufacturerColorFilter) {
                $variantsQuery->where('primary_color_id', $manufacturerColorFilter);
            }

            $variants = $variantsQuery->get();
            
            foreach ($variants as $variant) {
                $allProducts->push($this->formatVariantAsProduct($variant));
            }
        }

        // Pagination manuelle
        $total = $allProducts->count();
        $currentPage = (int) $request->get('page', 1);
        $lastPage = max(1, (int) ceil($total / $perPage));
        
        $paginatedData = $allProducts
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        return response()->json([
            'data' => $paginatedData,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/products/search",
     *     summary="Rechercher des produits",
     *     description="Recherche des produits par SKU ou par nom. Retourne 2 types: 'general' (produit simple) et 'variant' (variante de couleur). La recherche s'effectue sur le SKU et le nom du produit/variante.",
     *     operationId="searchProducts",
     *     tags={"Produits"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=true,
     *         description="Terme de recherche (SKU ou nom du produit)",
     *         @OA\Schema(type="string", minLength=2)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type de produit: 'general' (produits simples uniquement), 'variant' (variantes de couleur uniquement), ou vide pour tous",
     *         @OA\Schema(type="string", enum={"general", "variant"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Nombre de produits par page (max 500)",
     *         @OA\Schema(type="integer", default=100, maximum=500)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Résultats de la recherche",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="type", type="string", enum={"general", "variant"}, description="Type de produit: 'general' = produit simple, 'variant' = variante de couleur"),
     *                 @OA\Property(property="product_type", type="string", enum={"general", "variant"}, description="Type de produit (identique à 'type')"),
     *                 @OA\Property(property="sku", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="category_name", type="string"),
     *                 @OA\Property(property="category_uuid", type="string"),
     *                 @OA\Property(property="manufacturer", type="string"),
     *                 @OA\Property(property="principal_color", type="string", nullable=true, description="Nom de la couleur principale"),
     *                 @OA\Property(property="principal_color_hex", type="string", nullable=true, description="Code hexadécimal de la couleur principale (ex: #FF0000)"),
     *                 @OA\Property(property="manufacturer_color", type="string", nullable=true, description="Nom de la couleur fabricant"),
     *                 @OA\Property(property="manufacturer_color_hex", type="string", nullable=true, description="Code hexadécimal de la couleur fabricant"),
     *                 @OA\Property(property="images", type="array", @OA\Items(
     *                     @OA\Property(property="url", type="string", description="URL présignée de l'image (valide 24h)"),
     *                     @OA\Property(property="position", type="string", enum={"Front", "Back", "Left", "Right", "Top", "Bottom", "Part Zoom"}, nullable=true, description="Position/vue de l'image"),
     *                     @OA\Property(property="neutral_background", type="boolean", description="True si l'image a un fond neutre (blanc, gris, etc.)"),
     *                     @OA\Property(property="product_only", type="boolean", description="True si l'image montre uniquement le produit (pas de mise en situation)")
     *                 )),
     *                 @OA\Property(property="model_3d", type="object", nullable=true, description="Modèle 3D associé",
     *                     @OA\Property(property="uuid", type="string", description="UUID du modèle 3D"),
     *                     @OA\Property(property="status", type="string", enum={"Requested", "Error", "InReview", "Published"}, description="Statut: Requested=en cours de génération, Error=erreur, InReview=en attente de validation, Published=disponible"),
     *                     @OA\Property(property="url", type="string", nullable=true, description="URL présignée du fichier GLB (valide 24h, null si status!=Published)")
     *                 ),
     *                 @OA\Property(property="variant_uuid", type="string", nullable=true, description="UUID de la variante (null si type=general)"),
     *                 @OA\Property(property="product_uuid", type="string", description="UUID du produit parent")
     *             )),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="query", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Paramètre de recherche manquant ou trop court"
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q');
        
        // Validation du paramètre de recherche
        if (!$query || strlen($query) < 2) {
            return response()->json([
                'error' => 'Invalid query',
                'message' => 'Search query must be at least 2 characters long.',
            ], 400);
        }

        $perPage = min((int) $request->get('per_page', 100), 500);
        $searchTerm = '%' . $query . '%';
        $typeFilter = $request->get('type'); // 'general', 'variant', ou null pour tous

        // Collection pour stocker tous les résultats
        $allProducts = collect();

        // 1. Produits simples (sans variantes de couleur) - product_type = "general"
        if (!$typeFilter || $typeFilter === 'general') {
            $simpleProducts = Product::whereDoesntHave('colorVariants')
                ->where(function ($q) use ($searchTerm) {
                    $q->where('sku', 'ILIKE', $searchTerm)
                      ->orWhere('name', 'ILIKE', $searchTerm);
                })
                ->with(['category', 'manufacturer', 'primaryColor.parent', 'images', 'defaultModel3D'])
                ->get();
            
            foreach ($simpleProducts as $product) {
                $allProducts->push($this->formatSimpleProduct($product, 'general'));
            }
        }

        // 2. Variantes de couleur - product_type = "variant"
        if (!$typeFilter || $typeFilter === 'variant') {
            $variants = ProductColorVariant::where(function ($q) use ($searchTerm) {
                    $q->where('sku', 'ILIKE', $searchTerm)
                      ->orWhereHas('product', function ($pq) use ($searchTerm) {
                          $pq->where('sku', 'ILIKE', $searchTerm)
                             ->orWhere('name', 'ILIKE', $searchTerm);
                      });
                })
                ->with([
                    'product.category',
                    'product.manufacturer',
                    'product.defaultModel3D',
                    'primaryColor.parent',
                    'productImages',
                    'models3d',
                ])
                ->get();
            
            foreach ($variants as $variant) {
                $allProducts->push($this->formatVariantAsProduct($variant));
            }
        }

        // Pagination manuelle
        $total = $allProducts->count();
        $currentPage = (int) $request->get('page', 1);
        $lastPage = max(1, (int) ceil($total / $perPage));
        
        $paginatedData = $allProducts
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->values();

        return response()->json([
            'data' => $paginatedData,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'query' => $query,
            ],
        ]);
    }

    /**
     * Formate un produit simple pour la réponse API
     */
    private function formatSimpleProduct(Product $product, string $productType = 'general'): array
    {
        $primaryColor = $product->primaryColor;
        $principalColorName = null;
        $principalColorHex = null;
        $manufacturerColorName = null;
        $manufacturerColorHex = null;

        if ($primaryColor) {
            if ($primaryColor->parent_id) {
                // C'est une couleur fabricant
                $manufacturerColorName = $primaryColor->name;
                $manufacturerColorHex = $primaryColor->hex_code;
                $principalColorName = $primaryColor->parent?->name;
                $principalColorHex = $primaryColor->parent?->hex_code;
            } else {
                // C'est une couleur principale
                $principalColorName = $primaryColor->name;
                $principalColorHex = $primaryColor->hex_code;
            }
        }

        // Images du produit
        $images = $product->images->map(function ($image) {
            return [
                'url' => $this->getPresignedUrl($image->s3_url),
                'position' => $image->position,
                'neutral_background' => (bool) $image->neutral_background,
                'product_only' => (bool) $image->product_only,
            ];
        })->toArray();

        // Modèle 3D par défaut
        $model3d = null;
        if ($product->defaultModel3D) {
            $model3d = [
                'uuid' => $product->defaultModel3D->id,
                'status' => $product->defaultModel3D->status,
                'url' => $product->defaultModel3D->s3_url 
                    ? $this->getPresignedUrl($product->defaultModel3D->s3_url) 
                    : null,
            ];
        }

        return [
            'type' => 'general',
            'product_type' => 'general',
            'sku' => $product->sku,
            'name' => $product->name,
            'category_name' => $product->category?->name,
            'category_uuid' => $product->category_id,
            'manufacturer' => $product->manufacturer?->name,
            'principal_color' => $principalColorName,
            'principal_color_hex' => $principalColorHex,
            'manufacturer_color' => $manufacturerColorName,
            'manufacturer_color_hex' => $manufacturerColorHex,
            'images' => $images,
            'model_3d' => $model3d,
            'variant_uuid' => null,
            'product_uuid' => $product->id,
        ];
    }

    /**
     * Formate une variante de couleur comme un produit pour la réponse API
     */
    private function formatVariantAsProduct(ProductColorVariant $variant): array
    {
        $product = $variant->product;
        $primaryColor = $variant->primaryColor;
        
        $principalColorName = null;
        $principalColorHex = null;
        $manufacturerColorName = null;
        $manufacturerColorHex = null;

        if ($primaryColor) {
            if ($primaryColor->parent_id) {
                // C'est une couleur fabricant
                $manufacturerColorName = $primaryColor->name;
                $manufacturerColorHex = $primaryColor->hex_code;
                $principalColorName = $primaryColor->parent?->name;
                $principalColorHex = $primaryColor->parent?->hex_code;
            } else {
                // C'est une couleur principale
                $principalColorName = $primaryColor->name;
                $principalColorHex = $primaryColor->hex_code;
            }
        }

        // Images liées à cette variante
        $images = $variant->productImages->map(function ($image) {
            return [
                'url' => $this->getPresignedUrl($image->s3_url),
                'position' => $image->position,
                'neutral_background' => (bool) $image->neutral_background,
                'product_only' => (bool) $image->product_only,
            ];
        })->toArray();

        // Modèle 3D de la variante (ou modèle par défaut du produit)
        $model3d = null;
        $variantModel = $variant->models3d->first();
        
        if ($variantModel) {
            $model3d = [
                'uuid' => $variantModel->id,
                'status' => $variantModel->status,
                'url' => $variantModel->s3_url 
                    ? $this->getPresignedUrl($variantModel->s3_url) 
                    : null,
            ];
        } elseif ($product->defaultModel3D) {
            // Fallback sur le modèle par défaut du produit
            $model3d = [
                'uuid' => $product->defaultModel3D->id,
                'status' => $product->defaultModel3D->status,
                'url' => $product->defaultModel3D->s3_url 
                    ? $this->getPresignedUrl($product->defaultModel3D->s3_url) 
                    : null,
            ];
        }

        return [
            'type' => 'variant',
            'product_type' => 'variant',
            'sku' => $variant->sku ?? $product->sku,
            'name' => $product->name,
            'category_name' => $product->category?->name,
            'category_uuid' => $product->category_id,
            'manufacturer' => $product->manufacturer?->name,
            'principal_color' => $principalColorName,
            'principal_color_hex' => $principalColorHex,
            'manufacturer_color' => $manufacturerColorName,
            'manufacturer_color_hex' => $manufacturerColorHex,
            'images' => $images,
            'model_3d' => $model3d,
            'variant_uuid' => $variant->id,
            'product_uuid' => $product->id,
        ];
    }

    /**
     * Génère une URL présignée S3
     */
    private function getPresignedUrl(?string $s3Path): ?string
    {
        if (!$s3Path) {
            return null;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($s3Path, now()->addHours(24));
        } catch (\Exception $e) {
            return Storage::disk('s3')->url($s3Path);
        }
    }

    /**
     * Récupère l'ID de la catégorie et tous les IDs de ses sous-catégories (récursivement)
     * Utilise le champ path (ltree) pour une recherche efficace
     * 
     * @param string $categoryId UUID de la catégorie parente
     * @return array Liste des UUIDs de la catégorie et de toutes ses sous-catégories
     */
    private function getCategoryWithChildrenIds(string $categoryId): array
    {
        // Récupérer la catégorie parente pour obtenir son path
        $parentCategory = Category::find($categoryId);
        
        if (!$parentCategory) {
            return [$categoryId]; // Si la catégorie n'existe pas, retourner juste l'ID
        }

        // Utiliser le path ltree pour trouver toutes les sous-catégories
        // Le path de la catégorie parente est inclus dans le path de toutes ses sous-catégories
        // Exemple: si parent.path = "abc", les enfants ont path = "abc.def", "abc.def.ghi", etc.
        $categoryIds = Category::where('id', $categoryId)
            ->orWhere('path', 'LIKE', $parentCategory->path . '.%')
            ->pluck('id')
            ->toArray();

        return $categoryIds;
    }
}

