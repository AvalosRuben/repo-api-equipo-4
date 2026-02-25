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

Route::get('/prestashop/productos', function (PrestaShopService $prestashop) {
    return response()->json(
        $prestashop->getProducts()
    );
});

Route::get('/prestashop/ordenes', function (PrestaShopService $prestashop) {
    return response()->json(
        $prestashop->getOrders()
    );
});
Route::get('/prestashop/clientes', function (PrestaShopService $prestashop) {
    return response()->json(
        $prestashop->getCustomers()
    );
});

Route::get('/prestashop/productos/clave/{clave}', function ($clave, App\Services\PrestaShopService $prestashop) {
    return response()->json($prestashop->getProductBySku($clave));
});
// GET Orden por REFERENCE (Prestashop)
Route::get('/prestashop/ordenes/{reference}', function ($reference, PrestaShopService $prestashop) {
    $order = $prestashop->getOrderByReference($reference);

    if (empty($order)) {
        return response()->json([
            'status' => 'error',
            'data' => null,
            'errors' => [
                [
                    'code' => '404',
                    'message' => 'Order not found'
                ]
            ]
        ], 404);
    }

    return response()->json([
        'status' => 'success',
        'data' => $order,
        'errors' => []
    ]);
});