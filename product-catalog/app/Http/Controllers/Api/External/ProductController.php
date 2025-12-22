<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
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
     *     description="Retourne la liste des produits. Les variantes de couleur sont traitées comme des produits à part entière.",
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
     *         description="Filtrer par UUID de catégorie",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des produits",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="sku", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="category_name", type="string"),
     *                 @OA\Property(property="category_uuid", type="string"),
     *                 @OA\Property(property="manufacturer", type="string"),
     *                 @OA\Property(property="principal_color", type="string", nullable=true),
     *                 @OA\Property(property="manufacturer_color", type="string", nullable=true),
     *                 @OA\Property(property="images", type="array", @OA\Items(
     *                     @OA\Property(property="url", type="string"),
     *                     @OA\Property(property="position", type="string"),
     *                     @OA\Property(property="neutral_background", type="boolean"),
     *                     @OA\Property(property="product_only", type="boolean")
     *                 )),
     *                 @OA\Property(property="model_3d", type="object", nullable=true,
     *                     @OA\Property(property="uuid", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="url", type="string", nullable=true)
     *                 )
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

        // Collection pour stocker tous les résultats
        $allProducts = collect();

        // 1. Produits simples (sans variantes de couleur)
        $simpleProductsQuery = Product::whereDoesntHave('colorVariants')
            ->with(['category', 'manufacturer', 'primaryColor.parent', 'images', 'defaultModel3D']);
        
        // Appliquer les filtres aux produits simples
        if ($manufacturerFilter) {
            $simpleProductsQuery->where('manufacturer_id', $manufacturerFilter);
        }
        if ($categoryFilter) {
            $simpleProductsQuery->where('category_id', $categoryFilter);
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
            $allProducts->push($this->formatSimpleProduct($product));
        }

        // 2. Variantes de couleur (chaque variante = 1 produit)
        $variantsQuery = ProductColorVariant::with([
            'product.category',
            'product.manufacturer',
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
        if ($categoryFilter) {
            $variantsQuery->whereHas('product', function ($q) use ($categoryFilter) {
                $q->where('category_id', $categoryFilter);
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

        // Pagination manuelle
        $total = $allProducts->count();
        $currentPage = (int) $request->get('page', 1);
        $lastPage = (int) ceil($total / $perPage);
        
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
     *     description="Recherche des produits par SKU ou par nom. Les variantes de couleur sont traitées comme des produits à part entière.",
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
     *                 @OA\Property(property="sku", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="category_name", type="string"),
     *                 @OA\Property(property="category_uuid", type="string"),
     *                 @OA\Property(property="manufacturer", type="string"),
     *                 @OA\Property(property="principal_color", type="string", nullable=true),
     *                 @OA\Property(property="manufacturer_color", type="string", nullable=true),
     *                 @OA\Property(property="images", type="array", @OA\Items(
     *                     @OA\Property(property="url", type="string"),
     *                     @OA\Property(property="position", type="string"),
     *                     @OA\Property(property="neutral_background", type="boolean"),
     *                     @OA\Property(property="product_only", type="boolean")
     *                 )),
     *                 @OA\Property(property="model_3d", type="object", nullable=true,
     *                     @OA\Property(property="uuid", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="url", type="string", nullable=true)
     *                 )
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

        // Collection pour stocker tous les résultats
        $allProducts = collect();

        // 1. Produits simples (sans variantes de couleur) - recherche par SKU ou nom
        $simpleProducts = Product::whereDoesntHave('colorVariants')
            ->where(function ($q) use ($searchTerm) {
                $q->where('sku', 'ILIKE', $searchTerm)
                  ->orWhere('name', 'ILIKE', $searchTerm);
            })
            ->with(['category', 'manufacturer', 'primaryColor.parent', 'images', 'defaultModel3D'])
            ->get();
        
        foreach ($simpleProducts as $product) {
            $allProducts->push($this->formatSimpleProduct($product));
        }

        // 2. Variantes de couleur - recherche par SKU de la variante ou nom/SKU du produit parent
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
    private function formatSimpleProduct(Product $product): array
    {
        $primaryColor = $product->primaryColor;
        $principalColorName = null;
        $manufacturerColorName = null;

        if ($primaryColor) {
            if ($primaryColor->parent_id) {
                // C'est une couleur fabricant
                $manufacturerColorName = $primaryColor->name;
                $principalColorName = $primaryColor->parent?->name;
            } else {
                // C'est une couleur principale
                $principalColorName = $primaryColor->name;
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
            'sku' => $product->sku,
            'name' => $product->name,
            'category_name' => $product->category?->name,
            'category_uuid' => $product->category_id,
            'manufacturer' => $product->manufacturer?->name,
            'principal_color' => $principalColorName,
            'manufacturer_color' => $manufacturerColorName,
            'images' => $images,
            'model_3d' => $model3d,
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
        $manufacturerColorName = null;

        if ($primaryColor) {
            if ($primaryColor->parent_id) {
                // C'est une couleur fabricant
                $manufacturerColorName = $primaryColor->name;
                $principalColorName = $primaryColor->parent?->name;
            } else {
                // C'est une couleur principale
                $principalColorName = $primaryColor->name;
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
            'sku' => $variant->sku ?? $product->sku,
            'name' => $product->name,
            'category_name' => $product->category?->name,
            'category_uuid' => $product->category_id,
            'manufacturer' => $product->manufacturer?->name,
            'principal_color' => $principalColorName,
            'manufacturer_color' => $manufacturerColorName,
            'images' => $images,
            'model_3d' => $model3d,
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
}

