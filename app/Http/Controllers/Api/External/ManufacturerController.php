<?php

namespace App\Http\Controllers\Api\External;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ManufacturerController extends Controller
{
    /**
     * @OA\Get(
     *     path="/manufacturers",
     *     summary="Liste des fabricants",
     *     description="Retourne la liste des fabricants avec leurs UUID",
     *     operationId="getManufacturers",
     *     tags={"Fabricants"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Liste des fabricants",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="uuid", type="string"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="logo_url", type="string", nullable=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $manufacturers = Manufacturer::orderBy('name')->get();

        $data = $manufacturers->map(function ($manufacturer) {
            $logoUrl = null;
            if ($manufacturer->logo_s3_url) {
                try {
                    $logoUrl = Storage::disk('s3')->temporaryUrl($manufacturer->logo_s3_url, now()->addHours(24));
                } catch (\Exception $e) {
                    $logoUrl = Storage::disk('s3')->url($manufacturer->logo_s3_url);
                }
            }

            return [
                'uuid' => $manufacturer->id,
                'name' => $manufacturer->name,
                'logo_url' => $logoUrl,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}

