<?php

namespace App\Http\Controllers;

use App\Services\OdooService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ProductCategoryController extends Controller
{
    public function index(Request $request, OdooService $odooService): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 100), 500));
        $offset = max(0, (int) $request->query('offset', 0));

        try {
            $categories = $odooService->getCategoriesByProducts($limit, $offset);

            return response()->json([
                'data' => $categories,
                'meta' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total_categories' => count($categories),
                ],
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'No se pudieron obtener las categorías por productos.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
