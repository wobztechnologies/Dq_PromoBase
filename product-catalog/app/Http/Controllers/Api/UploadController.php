<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function presignedUrl(Request $request)
    {
        $request->validate([
            'product_id' => 'required|uuid',
            'extension' => 'required|string|max:10',
        ]);

        $path = 'products/' . $request->product_id . '/images/' . Str::random(20) . '.' . $request->extension;
        
        $url = Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(15));

        return response()->json([
            'url' => $url,
            'path' => $path,
        ]);
    }
}
