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

    public function executeKw(string $model, string $method, array $args = [], array $kwargs = [])
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
}