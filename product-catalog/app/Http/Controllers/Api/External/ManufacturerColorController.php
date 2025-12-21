<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\PrimaryColor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManufacturerColorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/manufacturerscolors",
     *     summary="Liste des couleurs fabricants",
     *     description="Retourne la liste des couleurs fabricants avec leur couleur principale associée",
     *     operationId="getManufacturerColors",
     *     tags={"Couleurs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="manufacturer",
     *         in="query",
     *         description="Filtrer par UUID de fabricant",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="primary_color",
     *         in="query",
     *         description="Filtrer par UUID de couleur principale",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des couleurs fabricants",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="uuid", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="hex_code", type="string", nullable=true),
     *                 @OA\Property(property="color_sku_code", type="string", nullable=true),
     *                 @OA\Property(property="rgb", type="string", nullable=true),
     *                 @OA\Property(property="pantone_c", type="string", nullable=true),
     *                 @OA\Property(property="pantone_tcx", type="string", nullable=true),
     *                 @OA\Property(property="pms", type="string", nullable=true),
     *                 @OA\Property(property="primary_color", type="object",
     *                     @OA\Property(property="uuid", type="string"),
     *                     @OA\Property(property="name", type="string")
     *                 ),
     *                 @OA\Property(property="manufacturer", type="object", nullable=true,
     *                     @OA\Property(property="uuid", type="string"),
     *                     @OA\Property(property="name", type="string")
     *                 )
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Récupérer les couleurs fabricants (avec parent = couleur principale)
        $query = PrimaryColor::whereNotNull('parent_id')
            ->with(['parent', 'manufacturer'])
            ->orderBy('name');

        // Filtres
        if ($request->has('manufacturer')) {
            $query->where('manufacturer_id', $request->get('manufacturer'));
        }

        if ($request->has('primary_color')) {
            $query->where('parent_id', $request->get('primary_color'));
        }

        $manufacturerColors = $query->get();

        $data = $manufacturerColors->map(function ($color) {
            return [
                'uuid' => $color->id,
                'name' => $color->name,
                'hex_code' => $color->hex_code,
                'color_sku_code' => $color->color_sku_code,
                'rgb' => $color->rgb,
                'pantone_c' => $color->pantone_c,
                'pantone_tcx' => $color->pantone_tcx,
                'pms' => $color->pms,
                'primary_color' => $color->parent ? [
                    'uuid' => $color->parent->id,
                    'name' => $color->parent->name,
                ] : null,
                'manufacturer' => $color->manufacturer ? [
                    'uuid' => $color->manufacturer->id,
                    'name' => $color->manufacturer->name,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
