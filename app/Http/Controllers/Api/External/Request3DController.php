<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessFal3DGeneration;
use App\Jobs\ProcessMeshy3DGeneration;
use App\Models\Product;
use App\Models\ProductColorVariant;
use App\Models\ProductImage;
use App\Models\ProductModel3D;
use App\Services\FalService;
use App\Services\MeshyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class Request3DController extends Controller
{
    /**
     * @OA\Get(
     *     path="/prerequest3d",
     *     summary="Préparer une demande de modèle 3D",
     *     description="Retourne les images éligibles pour la génération 3D (neutral_background=true, product_only=true). Vérifie également qu'aucun modèle 3D n'est déjà en cours de génération ou publié.",
     *     operationId="prerequest3d",
     *     tags={"Modèles 3D"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="variant_uuid",
     *         in="query",
     *         description="UUID de la variante de couleur",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="product_sku",
     *         in="query",
     *         description="SKU du produit (alternative à variant_uuid)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Images éligibles pour la génération 3D",
     *         @OA\JsonContent(
     *             @OA\Property(property="mode", type="string", enum={"variant", "general"}),
     *             @OA\Property(property="product_sku", type="string"),
     *             @OA\Property(property="variant_uuid", type="string", nullable=true),
     *             @OA\Property(property="variant_sku", type="string", nullable=true),
     *             @OA\Property(property="eligible_images", type="array", @OA\Items(
     *                 @OA\Property(property="uuid", type="string"),
     *                 @OA\Property(property="position", type="string"),
     *                 @OA\Property(property="url", type="string")
     *             )),
     *             @OA\Property(property="recommended_ai_model", type="string", enum={"fal-mono", "fal", "meshy"}),
     *             @OA\Property(property="can_generate", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Paramètres manquants"),
     *     @OA\Response(response=404, description="Produit ou variante non trouvé"),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(
     *         response=409,
     *         description="Conflit - Modèle 3D déjà existant",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="error", type="string", example="Generation in progress"),
     *                     @OA\Property(property="message", type="string", example="This product is currently in processing for 3D generation, it takes less than 5 minutes, please retry later."),
     *                     @OA\Property(property="model_3d_uuid", type="string"),
     *                     @OA\Property(property="status", type="string", example="Requested")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="error", type="string", example="Already published"),
     *                     @OA\Property(property="message", type="string", example="This product already has a 3D model, if the 3D model has an issue please contact our support."),
     *                     @OA\Property(property="model_3d_uuid", type="string"),
     *                     @OA\Property(property="status", type="string", example="Published")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function prerequest(Request $request): JsonResponse
    {
        $variantUuid = $request->get('variant_uuid');
        $productSku = $request->get('product_sku');

        if (!$variantUuid && !$productSku) {
            return response()->json([
                'error' => 'Missing parameter',
                'message' => 'Vous devez fournir soit variant_uuid, soit product_sku.',
            ], 400);
        }

        $product = null;
        $variant = null;
        $mode = 'general';

        // Récupérer le produit ou la variante
        if ($variantUuid) {
            $variant = ProductColorVariant::with(['product', 'productImages'])->find($variantUuid);
            if (!$variant) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Variante non trouvée.',
                ], 404);
            }
            $product = $variant->product;
            $mode = 'variant';
        } else {
            $product = Product::with('images')->where('sku', $productSku)->first();
            if (!$product) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Produit non trouvé.',
                ], 404);
            }
            
            // Vérifier si c'est un produit avec variantes
            if ($product->colorVariants()->count() > 0) {
                return response()->json([
                    'error' => 'Invalid request',
                    'message' => 'Ce produit possède des variantes de couleur. Veuillez utiliser variant_uuid pour spécifier la variante.',
                ], 400);
            }
        }

        // Vérifier s'il existe déjà un modèle 3D pour ce produit/variante
        $existingModelCheck = $this->checkExisting3DModel($product, $variant, $mode);
        if ($existingModelCheck) {
            return $existingModelCheck;
        }

        // Récupérer les images éligibles
        $images = $this->getEligibleImages($product, $variant);
        
        $eligibleImages = $images->map(function ($image) {
            return [
                'uuid' => $image->id,
                'position' => $image->position,
                'url' => $this->getPresignedUrl($image->s3_url),
            ];
        })->toArray();

        // Déterminer le modèle AI recommandé
        $recommendedModel = $this->recommendAiModel($images);
        $canGenerate = count($eligibleImages) > 0;
        
        $message = $canGenerate 
            ? "Génération possible avec le modèle {$recommendedModel}."
            : "Aucune image éligible trouvée (neutral_background=true et product_only=true requis).";

        return response()->json([
            'mode' => $mode,
            'product_sku' => $product->sku,
            'variant_uuid' => $variant?->id,
            'variant_sku' => $variant?->sku,
            'eligible_images' => $eligibleImages,
            'recommended_ai_model' => $recommendedModel,
            'can_generate' => $canGenerate,
            'message' => $message,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/request3d",
     *     summary="Demander la génération d'un modèle 3D",
     *     description="Lance la génération d'un modèle 3D. Le modèle AI est automatiquement sélectionné selon les images fournies: 1 image front = fal-mono, 3 images (front+back+left/right) = fal multi-image.",
     *     operationId="request3d",
     *     tags={"Modèles 3D"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"images"},
     *             @OA\Property(property="variant_uuid", type="string", format="uuid", description="UUID de la variante de couleur (requis si pas de product_uuid)"),
     *             @OA\Property(property="product_uuid", type="string", format="uuid", description="UUID du produit (requis si pas de variant_uuid)"),
     *             @OA\Property(
     *                 property="images",
     *                 type="array",
     *                 description="Tableau des images à utiliser pour la génération",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"uuid", "position"},
     *                     @OA\Property(property="uuid", type="string", format="uuid", description="UUID de l'image"),
     *                     @OA\Property(property="position", type="string", enum={"front", "back", "left", "right"}, description="Position de l'image")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Génération lancée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="model_3d_uuid", type="string"),
     *             @OA\Property(property="status", type="string", example="Requested"),
     *             @OA\Property(property="ai_model_used", type="string", enum={"fal-mono", "fal"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Paramètres manquants ou invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Produit, variante ou image non trouvé"),
     *     @OA\Response(
     *         response=409,
     *         description="Conflit - Modèle 3D déjà existant ou en cours de génération",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="error", type="string", example="Generation in progress"),
     *                     @OA\Property(property="message", type="string", example="This product is currently in processing for 3D generation, it takes less than 5 minutes, please retry later."),
     *                     @OA\Property(property="model_3d_uuid", type="string"),
     *                     @OA\Property(property="status", type="string", example="Requested")
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="error", type="string", example="Already published"),
     *                     @OA\Property(property="message", type="string", example="This product already has a 3D model, if the 3D model has an issue please contact our support."),
     *                     @OA\Property(property="model_3d_uuid", type="string"),
     *                     @OA\Property(property="status", type="string", example="Published")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=500, description="Erreur lors de la génération")
     * )
     */
    public function request(Request $request): JsonResponse
    {
        $variantUuid = $request->get('variant_uuid');
        $productUuid = $request->get('product_uuid');
        $imagesInput = $request->get('images', []);

        // Validation: variant_uuid OU product_uuid requis
        if (!$variantUuid && !$productUuid) {
            return response()->json([
                'error' => 'Missing parameter',
                'message' => 'You must provide either variant_uuid or product_uuid.',
            ], 400);
        }

        // Validation: tableau images requis
        if (empty($imagesInput) || !is_array($imagesInput)) {
            return response()->json([
                'error' => 'Missing parameter',
                'message' => 'You must provide an images array.',
            ], 400);
        }

        // Positions valides
        $validPositions = ['front', 'back', 'left', 'right'];
        
        // Valider et normaliser les images
        $imagesByPosition = [];
        foreach ($imagesInput as $index => $imageData) {
            if (!isset($imageData['uuid']) || !isset($imageData['position'])) {
                return response()->json([
                    'error' => 'Invalid image data',
                    'message' => "Image at index {$index} must have 'uuid' and 'position' properties.",
                ], 400);
            }

            $position = strtolower($imageData['position']);
            if (!in_array($position, $validPositions)) {
                return response()->json([
                    'error' => 'Invalid position',
                    'message' => "Position '{$imageData['position']}' is invalid. Valid positions are: front, back, left, right.",
                ], 400);
            }

            // Vérifier les doublons de position
            if (isset($imagesByPosition[$position])) {
                return response()->json([
                    'error' => 'Duplicate position',
                    'message' => "Position '{$position}' is specified multiple times.",
                ], 400);
            }

            $imagesByPosition[$position] = $imageData['uuid'];
        }

        // Déterminer le modèle AI selon les images fournies
        $hasFront = isset($imagesByPosition['front']);
        $hasBack = isset($imagesByPosition['back']);
        $hasSide = isset($imagesByPosition['left']) || isset($imagesByPosition['right']);
        $imageCount = count($imagesByPosition);

        // Validation des combinaisons d'images
        if (!$hasFront) {
            return response()->json([
                'error' => 'Missing front image',
                'message' => 'A front image is required for 3D generation.',
            ], 400);
        }

        // Déterminer le modèle AI automatiquement
        $aiModel = null;
        if ($imageCount === 1 && $hasFront) {
            $aiModel = 'fal-mono';
        } elseif ($imageCount === 3 && $hasFront && $hasBack && $hasSide) {
            $aiModel = 'fal';
        } else {
            return response()->json([
                'error' => 'Invalid image combination',
                'message' => 'Valid combinations are: 1 front image (mono), or 3 images (front + back + left/right) (multi).',
            ], 400);
        }

        $product = null;
        $variant = null;
        $mode = 'general';

        // Récupérer le produit ou la variante
        if ($variantUuid) {
            $variant = ProductColorVariant::with(['product', 'models3d'])->find($variantUuid);
            if (!$variant) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Variant not found.',
                ], 404);
            }
            $product = $variant->product;
            $mode = 'variant';
        } else {
            $product = Product::with(['defaultModel3D'])->find($productUuid);
            if (!$product) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Product not found.',
                ], 404);
            }
            
            // Vérifier si c'est un produit avec variantes
            if ($product->colorVariants()->count() > 0) {
                return response()->json([
                    'error' => 'Invalid request',
                    'message' => 'This product has color variants. Please use variant_uuid instead.',
                ], 400);
            }
        }

        // Vérifier s'il existe déjà un modèle 3D pour ce produit/variante
        $existingModelCheck = $this->checkExisting3DModel($product, $variant, $mode);
        if ($existingModelCheck) {
            return $existingModelCheck;
        }

        // Récupérer et valider les images depuis la base de données
        $imageUrlsMap = [];
        foreach ($imagesByPosition as $position => $imageUuid) {
            $image = ProductImage::find($imageUuid);
            if (!$image) {
                return response()->json([
                    'error' => 'Image not found',
                    'message' => "Image with UUID '{$imageUuid}' not found.",
                ], 404);
            }

            // Vérifier que l'image appartient au produit
            if ($image->product_id !== $product->id) {
                return response()->json([
                    'error' => 'Invalid image',
                    'message' => "Image '{$imageUuid}' does not belong to this product.",
                ], 400);
            }

            // Convertir la position en format attendu (première lettre majuscule)
            $normalizedPosition = ucfirst($position);
            $imageUrlsMap[$normalizedPosition] = $this->getPresignedUrl($image->s3_url);
        }

        // Générer le chemin de sortie
        $variantSku = $mode === 'variant' ? $variant->sku : null;
        $s3Path = $product->generateAssetPath('glb', $variantSku);
        $bucket = config('filesystems.disks.s3.bucket');
        $outputPath = 's3://' . $bucket . '/' . $s3Path;

        // Créer l'enregistrement ProductModel3D
        $model3D = ProductModel3D::create([
            'product_id' => $product->id,
            's3_url' => null,
            'status' => ProductModel3D::STATUS_REQUESTED,
            'is_default' => false,
        ]);

        // Associer la variante si mode variant
        if ($mode === 'variant') {
            $model3D->colorVariants()->attach($variant->id);
        }

        try {
            // Lancer la génération selon le modèle AI
            if ($aiModel === 'fal') {
                $result = $this->generateWithFal($model3D, $imageUrlsMap, $outputPath);
            } else {
                $result = $this->generateWithFalMono($model3D, $imageUrlsMap, $outputPath);
            }

            if (!$result['success']) {
                $model3D->update(['status' => ProductModel3D::STATUS_ERROR]);
                return response()->json([
                    'success' => false,
                    'error' => 'Generation failed',
                    'message' => $result['error'] ?? 'Error during generation.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Generation started successfully.',
                'model_3d_uuid' => $model3D->id,
                'status' => ProductModel3D::STATUS_REQUESTED,
                'ai_model_used' => $aiModel,
            ]);

        } catch (\Exception $e) {
            $model3D->update(['status' => ProductModel3D::STATUS_ERROR]);
            
            return response()->json([
                'success' => false,
                'error' => 'Exception',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifie s'il existe déjà un modèle 3D pour le produit/variante
     * Retourne une JsonResponse d'erreur si un modèle existe, null sinon
     */
    private function checkExisting3DModel(Product $product, ?ProductColorVariant $variant, string $mode): ?JsonResponse
    {
        $existingModel = null;

        if ($mode === 'variant' && $variant) {
            // Vérifier les modèles 3D associés à la variante
            $existingModel = $variant->models3d()
                ->whereIn('status', [ProductModel3D::STATUS_REQUESTED, ProductModel3D::STATUS_PUBLISHED])
                ->first();
        } else {
            // Vérifier les modèles 3D du produit (mode général)
            $existingModel = ProductModel3D::where('product_id', $product->id)
                ->whereIn('status', [ProductModel3D::STATUS_REQUESTED, ProductModel3D::STATUS_PUBLISHED])
                ->first();
        }

        if (!$existingModel) {
            return null;
        }

        // Messages d'erreur spécifiques selon le statut
        if ($existingModel->status === ProductModel3D::STATUS_REQUESTED) {
            return response()->json([
                'error' => 'Generation in progress',
                'message' => 'This product is currently in processing for 3D generation, it takes less than 5 minutes, please retry later.',
                'model_3d_uuid' => $existingModel->id,
                'status' => $existingModel->status,
            ], 409);
        }

        if ($existingModel->status === ProductModel3D::STATUS_PUBLISHED) {
            return response()->json([
                'error' => 'Already published',
                'message' => 'This product already has a 3D model, if the 3D model has an issue please contact our support.',
                'model_3d_uuid' => $existingModel->id,
                'status' => $existingModel->status,
            ], 409);
        }

        return null;
    }

    /**
     * Récupère les images éligibles pour la génération 3D
     */
    private function getEligibleImages(Product $product, ?ProductColorVariant $variant): \Illuminate\Support\Collection
    {
        if ($variant) {
            // Images liées à la variante
            return $variant->productImages()
                ->where('neutral_background', true)
                ->where('product_only', true)
                ->get();
        }

        // Images du produit (mode général)
        return $product->images()
            ->where('neutral_background', true)
            ->where('product_only', true)
            ->get();
    }

    /**
     * Recommande le modèle AI basé sur les images disponibles
     */
    private function recommendAiModel($images): string
    {
        $positions = $images->pluck('position')->toArray();
        
        $hasFront = in_array('Front', $positions);
        $hasBack = in_array('Back', $positions);
        $hasSide = in_array('Left', $positions) || in_array('Right', $positions);

        if ($hasFront && $hasBack && $hasSide) {
            return 'fal'; // Multi-image
        }

        if ($hasFront && count($positions) === 1) {
            return 'fal-mono'; // Mono-image
        }

        return 'meshy'; // Fallback
    }

    /**
     * Lance la génération avec fal.ai multi-image
     */
    private function generateWithFal(ProductModel3D $model3D, array $imageUrlsMap, string $outputPath): array
    {
        $frontUrl = $imageUrlsMap['Front'] ?? null;
        $backUrl = $imageUrlsMap['Back'] ?? null;
        $sideUrl = $imageUrlsMap['Left'] ?? $imageUrlsMap['Right'] ?? null;

        if (!$frontUrl || !$backUrl || !$sideUrl) {
            return [
                'success' => false,
                'error' => 'Images manquantes pour fal multi-image (Front, Back, Left/Right requis).',
            ];
        }

        $falService = new FalService();
        $result = $falService->generate3D($frontUrl, $backUrl, $sideUrl, $outputPath);

        if ($result['success']) {
            if ($result['status'] === 'completed' && isset($result['model_mesh_url'])) {
                ProcessFal3DGeneration::dispatch($model3D->id, $result['model_mesh_url'], $outputPath);
            } else {
                $model3D->update(['meshy_task_id' => $result['request_id']]);
                ProcessFal3DGeneration::dispatch($model3D->id, null, $outputPath)->delay(now()->addSeconds(30));
            }
        }

        return $result;
    }

    /**
     * Lance la génération avec fal.ai mono-image
     */
    private function generateWithFalMono(ProductModel3D $model3D, array $imageUrlsMap, string $outputPath): array
    {
        $frontUrl = $imageUrlsMap['Front'] ?? null;

        if (!$frontUrl) {
            return [
                'success' => false,
                'error' => 'Image Front manquante pour fal mono-image.',
            ];
        }

        $falService = new FalService();
        $result = $falService->generate3DFromSingleImage($frontUrl, $outputPath);

        if ($result['success']) {
            if ($result['status'] === 'completed' && isset($result['model_mesh_url'])) {
                ProcessFal3DGeneration::dispatch($model3D->id, $result['model_mesh_url'], $outputPath);
            } else {
                $model3D->update(['meshy_task_id' => $result['request_id']]);
                ProcessFal3DGeneration::dispatch($model3D->id, null, $outputPath)->delay(now()->addSeconds(30));
            }
        }

        return $result;
    }

    /**
     * Lance la génération avec Meshy
     */
    private function generateWithMeshy(ProductModel3D $model3D, array $imageUrlsMap, string $outputPath): array
    {
        $imageUrls = array_values($imageUrlsMap);

        $meshyService = new MeshyService();
        $result = $meshyService->generate3D($imageUrls, $outputPath, 'meshy-5');

        if ($result['success']) {
            $model3D->update(['meshy_task_id' => $result['task_id']]);
            ProcessMeshy3DGeneration::dispatch($model3D->id, $result['task_id'], $outputPath)->delay(now()->addSeconds(30));
        }

        return $result;
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

