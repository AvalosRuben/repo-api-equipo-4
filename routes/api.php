<?php

use Illuminate\Support\Facades\Route;
use App\Services\OdooService;


// GET  - 10 en 10
Route::get('/productos', function (OdooService $odoo) {
    $limit = (int) request()->get('limit', 10);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getProducts($limit, $offset)
    );
});

// GET por id indv
Route::get('/productos/{id}', function ($id, OdooService $odoo) {
    $product = $odoo->getProductById((int) $id);

    if (empty($product)) {
        return response()->json([
            'message' => 'Producto no encontrado'
        ], 404);
    }

    return response()->json($product);
});

// GET Ordenes
Route::get('/ordenes', function (OdooService $odoo) {
    $limit = min((int) request()->get('limit', 10), 100);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getOrders($limit, $offset)
    );
});

// GET Proveedores
Route::get('/proveedores', function (OdooService $odoo) {
    $limit = min((int) request()->get('limit', 10), 100);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getSuppliers($limit, $offset)
    );
});