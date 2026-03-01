<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OdooService
{
    protected string $url;
    protected string $db;
    protected string $username;
    protected string $password;
    protected ?int $uid = null;

    public function __construct()
    {
        $this->url = config('services.odoo.url');
        $this->db = config('services.odoo.db');
        $this->username = config('services.odoo.username');
        $this->password = config('services.odoo.password');
    }

    protected function callRpc(string $service, string $method, array $args = []): array
    {
        $response = Http::post($this->url, [
            'jsonrpc' => '2.0',
            'method'  => 'call',
            'params'  => [
                'service' => $service,
                'method'  => $method,
                'args'    => $args,
            ],
            'id' => 1,
        ]);

        return $response->json();
    }

    protected function login(): int
    {
        if ($this->uid !== null) {
            return $this->uid;
        }

        $result = $this->callRpc('common', 'login', [
            $this->db,
            $this->username,
            $this->password,
        ]);

        if (!isset($result['result'])) {
            throw new \Exception('Error al autenticar con Odoo');
        }

        $this->uid = $result['result'];

        return $this->uid;
    }

    public function executeKw(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        $uid = $this->login();

        $params = [
            $this->db,
            $uid,
            $this->password,
            $model,
            $method,
            $args,
        ];

        if (!empty($kwargs)) {
            $params[] = $kwargs;
        }

        $response = $this->callRpc('object', 'execute_kw', $params);

        if (isset($response['error'])) {
            throw new \Exception('Error en execute_kw: ' . json_encode($response['error']));
        }

        return $response['result'];
    }


    public function getProducts(int $limit = 10, int $offset = 0): array
    {
        return $this->executeKw(
            'product.template',
            'search_read',
            [
                []
            ],
            [
                'fields' => ['id', 'name', 'list_price'],
                'limit'  => $limit,
                'offset' => $offset,
                'order'  => 'id asc',
            ]
        );
    }

    public function getProductById(int $id): array
    {
        $result = $this->executeKw(
            'product.template',
            'search_read',
            [
                [['id', '=', $id]]
            ],
            [
                'fields' => ['id', 'name', 'list_price'],
                'limit'  => 1,
            ]
        );

        return $result[0] ?? [];
    }

    public function getOrders(int $limit = 10, int $offset = 0): array
    {
        return $this->executeKw(
            'sale.order',
            'search_read',
            [
                // solo pedidos que no esten cancelados
                [['state', '!=', 'cancel']]
            ],
            [
                'fields' => [
                    'id',
                    'name',
                    'partner_id',
                    'amount_total',
                    'state',
                    'date_order'
                ],
                'limit'  => $limit,
                'offset' => $offset,
                'order'  => 'id asc',
            ]
        );
    }

    public function getCategoriesByProducts(int $limit = 100, int $offset = 0): array
    {
        $products = $this->getProducts($limit, $offset);
        $categories = [];

        foreach ($products as $product) {
            $categoryId = $product['categ_id'][0] ?? null;
            $categoryName = $product['categ_id'][1] ?? 'Sin categoría';
            $key = (string) ($categoryId ?? 'none');

            if (!isset($categories[$key])) {
                $categories[$key] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'products' => [],
                ];
            }

            $categories[$key]['products'][] = [
                'id' => $product['id'] ?? null,
                'name' => $product['name'] ?? null,
                'list_price' => $product['list_price'] ?? null,
                'description_sale' => $product['description_sale'] ?? null,
            ];
        }

        return array_values($categories);
    }

     # Basically the same as getProducts except it shows their stock instead. Incredible thanks mejia
     public function getStock(int $limit = 10, int $offset = 0): array
    {
        return $this->executeKw(
            'product.template',
            'search_read',
            [
                []
            ],
            [
                'fields' => ['id', 'name', 'qty_available'],
                'limit'  => $limit,
                'offset' => $offset,
                'order'  => 'id desc',
            ]
        );
    }

    public function getStockById(int $id): array
    {
        $result = $this->executeKw(
            'product.template',
            'search_read',
            [
                [['id', '=', $id]]
            ],
            [
                'fields' => ['id', 'name', 'list_price'],
                'limit'  => 1,
            ]
        );

        return $result[0] ?? [];
    }

    public function getSuppliers(int $limit = 10, int $offset = 0): array
    {
        return $this->executeKw(
            'res.partner',
            'search_read',
            [
                [['supplier_rank', '>', 0]] //solo proveedores
            ],
            [
                'fields' => ['id', 'name', 'email', 'phone'],
                'limit'  => $limit,
                'offset' => $offset,
                'order'  => 'id asc',
            ]
        );
    }


   public function getAllProducts(): array
    {
    $allProducts = [];
    $offset      = 0;
    $batchSize   = 100;

    do {
        $batch = $this->executeKw(
            'product.template',
            'search_read',
            [[]],
            [
                'fields' => [
                    'id',
                    'name',
                    'list_price',
                    'qty_available',
                    'default_code',
                    'description',
                ],
                'limit'  => $batchSize,
                'offset' => $offset,
                'order'  => 'id asc',
            ]
        );

        $allProducts = array_merge($allProducts, $batch);
        $offset += $batchSize;

    } while (count($batch) === $batchSize);

    return $allProducts;
}

}
