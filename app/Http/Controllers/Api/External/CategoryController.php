<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/categories",
     *     summary="Liste des catégories",
     *     description="Retourne la liste des catégories structurée hiérarchiquement et ordonnée",
     *     operationId="getCategories",
     *     tags={"Catégories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="flat",
     *         in="query",
     *         description="Si true, retourne une liste plate au lieu d'une structure hiérarchique",
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Parameter(
     *         name="root_only",
     *         in="query",
     *         description="Si true, retourne uniquement les catégories racines (niveau 1, sans parent)",
     *         @OA\Schema(type="boolean", default=false)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des catégories",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="uuid", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="translations", type="object",
     *                     @OA\Property(property="fr", type="string", nullable=true),
     *                     @OA\Property(property="en", type="string", nullable=true),
     *                     @OA\Property(property="de", type="string", nullable=true),
     *                     @OA\Property(property="es", type="string", nullable=true),
     *                     @OA\Property(property="it", type="string", nullable=true),
     *                     @OA\Property(property="nl", type="string", nullable=true),
     *                     @OA\Property(property="pt", type="string", nullable=true),
     *                     @OA\Property(property="pl", type="string", nullable=true)
     *                 ),
     *                 @OA\Property(property="path", type="string"),
     *                 @OA\Property(property="level", type="integer"),
     *                 @OA\Property(property="order", type="integer"),
     *                 @OA\Property(property="image_url", type="string", nullable=true),
     *                 @OA\Property(property="parent_uuid", type="string", nullable=true),
     *                 @OA\Property(property="parent_name", type="string", nullable=true, description="Nom de la catégorie parente"),
     *                 @OA\Property(property="products_count", type="integer", description="Nombre total de produits dans cette catégorie ET ses sous-catégories"),
     *                 @OA\Property(property="children", type="array", @OA\Items(
     *                     @OA\Property(property="uuid", type="string"),
     *                     @OA\Property(property="name", type="string")
     *                 ), description="Liste des catégories enfants (uuid et name)")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $flat = filter_var($request->get('flat', false), FILTER_VALIDATE_BOOLEAN);
        $rootOnly = filter_var($request->get('root_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($rootOnly) {
            return $this->rootCategoriesList();
        }

        if ($flat) {
            return $this->flatList();
        }

        return $this->hierarchicalList();
    }

    /**
     * Retourne uniquement les catégories racines (niveau 1, sans parent)
     */
    private function rootCategoriesList(): JsonResponse
    {
        // Récupérer uniquement les catégories racines (sans parent)
        $categories = Category::whereNull('parent_id')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        // Calculer le comptage des produits incluant les sous-catégories
        $this->calculateProductsCountWithChildren($categories);

        $data = $categories->map(function ($category) {
            $formatted = $this->formatCategory($category);
            // Pour les catégories racines, on ajoute le nombre d'enfants directs
            $formatted['children_count'] = Category::where('parent_id', $category->id)->count();
            return $formatted;
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Retourne la liste hiérarchique des catégories
     */
    private function hierarchicalList(): JsonResponse
    {
        // Récupérer toutes les catégories avec la relation parent
        $categories = Category::with('parent')
            ->orderBy('order')
            ->orderBy('path')
            ->get();

        // Calculer le comptage des produits incluant les sous-catégories pour chaque catégorie
        $this->calculateProductsCountWithChildren($categories);

        // Construire l'arbre hiérarchique
        $tree = $this->buildTree($categories);

        return response()->json([
            'data' => $tree,
        ]);
    }

    /**
     * Retourne la liste plate des catégories
     */
    private function flatList(): JsonResponse
    {
        $categories = Category::with(['parent', 'children'])
            ->orderBy('order')
            ->orderBy('path')
            ->get();

        // Calculer le comptage des produits incluant les sous-catégories pour chaque catégorie
        $this->calculateProductsCountWithChildren($categories);

        $data = $categories->map(function ($category) {
            $formatted = $this->formatCategory($category);
            // Ajouter les children simplifiés pour la liste plate
            $formatted['children'] = $category->children->map(function ($child) {
                return [
                    'uuid' => $child->id,
                    'name' => $child->name,
                ];
            })->toArray();
            return $formatted;
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Calcule le nombre de produits pour chaque catégorie, incluant les sous-catégories
     * Utilise le champ path (ltree) pour une requête efficace
     */
    private function calculateProductsCountWithChildren($categories): void
    {
        foreach ($categories as $category) {
            // Compter les produits de cette catégorie ET de toutes ses sous-catégories
            // En utilisant le path: si category.path = "abc", les sous-catégories ont path = "abc.xxx"
            $count = Product::where('category_id', $category->id)
                ->orWhereHas('category', function ($query) use ($category) {
                    $query->where('path', 'LIKE', $category->path . '.%');
                })
                ->count();
            
            // Stocker le comptage dans un attribut dynamique
            $category->products_count = $count;
        }
    }

    /**
     * Construit l'arbre hiérarchique des catégories
     */
    private function buildTree($categories, $parentId = null): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $childrenFull = $this->buildTree($categories, $category->id);
                
                $item = $this->formatCategory($category);
                
                // Children complets pour la récursion de l'arbre
                $item['children'] = $childrenFull;
                
                // Ajouter aussi children_summary avec juste uuid et name pour référence rapide
                $item['children_summary'] = collect($childrenFull)->map(function ($child) {
                    return [
                        'uuid' => $child['uuid'],
                        'name' => $child['name'],
                    ];
                })->toArray();
                
                $tree[] = $item;
            }
        }

        return $tree;
    }

    /**
     * Formate une catégorie pour la réponse API
     */
    private function formatCategory(Category $category): array
    {
        $imageUrl = null;
        if ($category->image_s3_url) {
            try {
                $imageUrl = Storage::disk('s3')->temporaryUrl($category->image_s3_url, now()->addHours(24));
            } catch (\Exception $e) {
                $imageUrl = Storage::disk('s3')->url($category->image_s3_url);
            }
        }

        // Calculer le niveau de profondeur
        $level = $category->path ? substr_count($category->path, '.') : 0;

        // Récupérer les traductions (accès direct à l'attribut pour éviter le mutator)
        $translations = $category->getAttributes()['translations'] ?? null;
        if (is_string($translations)) {
            $translations = json_decode($translations, true);
        }

        // Récupérer le nom du parent si disponible
        $parentName = null;
        if ($category->relationLoaded('parent') && $category->parent) {
            $parentName = $category->parent->name;
        }

        // Récupérer le comptage des produits
        $productsCount = $category->products_count ?? 0;

        return [
            'uuid' => $category->id,
            'name' => $category->name,
            'translations' => $translations ?? [
                'fr' => null,
                'en' => null,
                'de' => null,
                'es' => null,
                'it' => null,
                'nl' => null,
                'pt' => null,
                'pl' => null,
            ],
            'path' => $category->path,
            'level' => $level,
            'order' => $category->order,
            'image_url' => $imageUrl,
            'parent_uuid' => $category->parent_id,
            'parent_name' => $parentName,
            'products_count' => $productsCount,
        ];
    }
}

