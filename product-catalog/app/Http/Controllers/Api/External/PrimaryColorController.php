<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\PrimaryColor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrimaryColorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/primarycolors",
     *     summary="Liste des couleurs principales",
     *     description="Retourne la liste des couleurs principales avec leurs couleurs fabricants associées",
     *     operationId="getPrimaryColors",
     *     tags={"Couleurs"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des couleurs principales",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="uuid", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="hex_code", type="string", nullable=true),
     *                 @OA\Property(property="manufacturer_colors", type="array", @OA\Items(
     *                     @OA\Property(property="uuid", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="hex_code", type="string", nullable=true),
     *                     @OA\Property(property="color_sku_code", type="string", nullable=true),
     *                     @OA\Property(property="manufacturer_uuid", type="string", nullable=true),
     *                     @OA\Property(property="manufacturer_name", type="string", nullable=true)
     *                 ))
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Récupérer les couleurs principales (sans parent)
        $primaryColors = PrimaryColor::whereNull('parent_id')
            ->with(['children.manufacturer'])
            ->orderBy('name')
            ->get();

        $data = $primaryColors->map(function ($color) {
            return [
                'uuid' => $color->id,
                'name' => $color->name,
                'hex_code' => $color->hex_code,
                'manufacturer_colors' => $color->children->map(function ($child) {
                    return [
                        'uuid' => $child->id,
                        'name' => $child->name,
                        'hex_code' => $child->hex_code,
                        'color_sku_code' => $child->color_sku_code,
                        'rgb' => $child->rgb,
                        'pantone_c' => $child->pantone_c,
                        'pantone_tcx' => $child->pantone_tcx,
                        'pms' => $child->pms,
                        'manufacturer_uuid' => $child->manufacturer_id,
                        'manufacturer_name' => $child->manufacturer?->name,
                    ];
                })->toArray(),
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}
