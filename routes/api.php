<?php

use Illuminate\Support\Facades\Route;
use App\Services\OdooService;
use App\Services\PrestaShopService;


// GET  - 10 en 10
Route::get('/odoo/productos', function (OdooService $odoo) {
    $limit = (int) request()->get('limit', 10);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getProducts($limit, $offset)
    );
});

// GET por id indv
Route::get('/odoo/productos/{id}', function ($id, OdooService $odoo) {
    $product = $odoo->getProductById((int) $id);

    if (empty($product)) {
        return response()->json([
            'message' => 'Producto no encontrado'
        ], 404);
    }

    return response()->json($product);
});

Route::get('/odoo/stock/', function (OdooService $odoo) {
    $limit = (int) request()->get('limit', 10);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getStock($limit, $offset)
    );
});

Route::get('/odoo/stock/{id}', function ($id, OdooService $odoo) {
    $product = $odoo->getStockById((int) $id);

    if (empty($product)) {
        return response()->json([
            'message' => 'Producto no encontrado'
        ], 404);
    }

    return response()->json($product);
});

// GET Ordenes
Route::get('/odoo/ordenes', function (OdooService $odoo) {
    $limit = min((int) request()->get('limit', 10), 100);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getOrders($limit, $offset)
    );
});

// GET Proveedores
Route::get('/odoo/proveedores', function (OdooService $odoo) {
    $limit = min((int) request()->get('limit', 10), 100);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getSuppliers($limit, $offset)
    );
});

Route::get('/prestashop/pagos', function (PrestaShopService $prestashop) {
    return response()->json(
        $prestashop->getPayments()
    );
});

Route::get('/prestashop/proveedores', function (PrestaShopService $prestashop) {
    return response()->json(
        $prestashop->getSuppliers()
    );
});