<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->get('q', '');
        $category = $request->get('category');
        $color = $request->get('color');
        $manufacturer = $request->get('manufacturer');

        $products = Product::search($query);

        if ($category) {
            $products = $products->where('category', $category);
        }

        if ($color) {
            $products = $products->where('colors', $color);
        }

        if ($manufacturer) {
            $products = $products->where('manufacturer', $manufacturer);
        }

        $results = $products->get();

        return ProductResource::collection($results);
    }
}
