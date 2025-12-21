<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
     *                 @OA\Property(property="children", type="array", @OA\Items(type="object"))
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $flat = filter_var($request->get('flat', false), FILTER_VALIDATE_BOOLEAN);

        if ($flat) {
            return $this->flatList();
        }

        return $this->hierarchicalList();
    }

    /**
     * Retourne la liste hiérarchique des catégories
     */
    private function hierarchicalList(): JsonResponse
    {
        // Récupérer toutes les catégories ordonnées par order puis path
        $categories = Category::orderBy('order')->orderBy('path')->get();

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
        $categories = Category::orderBy('order')->orderBy('path')->get();

        $data = $categories->map(function ($category) {
            return $this->formatCategory($category);
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Construit l'arbre hiérarchique des catégories
     */
    private function buildTree($categories, $parentId = null): array
    {
        $tree = [];

        foreach ($categories as $category) {
            if ($category->parent_id === $parentId) {
                $children = $this->buildTree($categories, $category->id);
                
                $item = $this->formatCategory($category);
                $item['children'] = $children;
                
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
        ];
    }
}
