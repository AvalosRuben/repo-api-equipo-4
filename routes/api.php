<?php

use Illuminate\Support\Facades\Route;
use App\Services\OdooService;
use App\Services\PrestaShopService;
use Illuminate\Http\Request;

// GET  - 10 en 10
Route::get('/odoo/productos', function (OdooService $odoo) {
    $limit = (int) request()->get('limit', 10);
    $offset = (int) request()->get('offset', 0);

    return response()->json(
        $odoo->getAllProducts($limit, $offset)
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

Route::match(['put', 'patch'], '/prestashop/productos/clave/{clave}', function (Request $request, $clave, PrestaShopService $prestashop) {
    $payload = $request->only(['name', 'description', 'price', 'active', 'reference']);
    $method = strtoupper((string) $request->query('method', $request->method()));

    $result = $prestashop->updateProductByReference($clave, $payload, $method);

    if (($result['status'] ?? 'error') === 'success') {
        return response()->json($result);
    }

    $code = (int) ($result['errors'][0]['code'] ?? 400);
    if ($code < 100 || $code > 599) {
        $code = 400;
    }

    return response()->json($result, $code);
});

// desactivar producto por referencia
Route::get('/prestashop/productos/desactivar/{clave}', function ($clave, App\Services\PrestaShopService $prestashop) {
    return response()->json($prestashop->desactivarProductoPorSku($clave));
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

Route::post('/create-product-by-reference/{reference}', function ($reference, OdooService $odoo, PrestaShopService $prestashop){
    $prestashopQuery = $prestashop->getProductBySku($reference);
    if (!(isset($prestashopQuery['errors'][0]['code']) && $prestashopQuery['errors'][0]['code'] == 404)) {
        return response()->json([
            'status' => 'error',
            'data' => null,
            'errors' => [
                [
                    'code' => '409',
                    'message' => 'Conflict.'
                ]
            ]
        ], 409);
    }

    $odooQuery = $odoo->getProductByReference($reference);
    if ($odooQuery['qty_available'] == '0' || $odooQuery['list_price'] == '0') {
        return response()->json([
            'status' => 'error',
            'data' => null,
            'errors' => [
                [
                    'code' => '400',
                    'message' => 'Bad Request.'
                ]
            ]
        ], 400);
    }

    return $prestashop->createProduct($odooQuery);
});

Route::get('/sync/odoo-to-prestashop', function (OdooService $odoo, PrestaShopService $presta) {

    $products = $odoo->getAllProducts();

    $results = [
        'creados'  => [],
        'omitidos' => [],
        'errores'  => [],
    ];

    foreach ($products as $product) {
        $nombre = $product['name'] ?? 'Sin nombre';
        $precio = (float)($product['list_price'] ?? 0);
        $stock  = (int)($product['qty_available'] ?? 0);
        $sku    = $product['default_code'] ?? null;

        // Regla 1: no crear si precio $0 Y stock 0
        if ($precio == 0 || $stock == 0) {
            $results['omitidos'][] = [
                'id'     => $product['id'],
                'nombre' => $nombre,
                'razon'  => 'Precio $0 o stock 0',
            ];
            continue;
        }

        // Regla 2: sin SKU no podemos detectar duplicados, omitir
        if (empty($sku)) {
            $results['omitidos'][] = [
                'id'     => $product['id'],
                'nombre' => $nombre,
                'razon'  => 'Sin SKU/referencia',
            ];
            continue;
        }

        // Regla 3: no duplicar, verificar si ya existe en PrestaShop
        $existe = $presta->getProductBySku($sku);
        if ($existe['status'] === 'success') {
            $results['omitidos'][] = [
                'id'     => $product['id'],
                'nombre' => $nombre,
                'razon'  => 'Ya existe en PrestaShop',
            ];
            continue;
        }

        // Crear producto
        $resultado = $presta->createProduct($product);

        if ($resultado['status'] === 'success') {
            $results['creados'][] = [
            'id'           => $product['id'],
            'nombre'       => $nombre,
            'ps_id'        => $resultado['ps_id'] ?? null,
        ];
        } else {
            $results['errores'][] = [
                'id'      => $product['id'],
                'nombre'  => $nombre,
                'detalle' => $resultado['details'] ?? $resultado['message'],
            ];
        }
    }

    return response()->json([
        'resumen' => [
            'total_odoo' => count($products),
            'creados'    => count($results['creados']),
            'omitidos'   => count($results['omitidos']),
            'errores'    => count($results['errores']),
        ],
        'detalle' => $results,
    ]);
});