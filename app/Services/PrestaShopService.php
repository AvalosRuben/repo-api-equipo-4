<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;

class PrestaShopService
{
    protected string $url;
    protected string $ws_key;

    public function __construct()
    {
        $this->url = config('services.prestashop.url');
        $this->ws_key = config('services.prestashop.ws_key');
    }

    public function getPayments(): array
    {
      $url = $this->url;
      $ws_key = $this->ws_key;
      $response = Http::get("{$url}/order_payments&display=full&output_format=JSON&ws_key={$ws_key}", []);
      return json_decode($response, true);
    }

    public function getSuppliers(): array
    {
      $url = $this->url;
      $ws_key = $this->ws_key;
      $response = Http::get("{$url}/suppliers&display=full&output_format=JSON&ws_key={$ws_key}", []);
      return json_decode($response, true);
    }

    public function getProducts(): array
    {
      $url = $this->url;
      $ws_key = $this->ws_key;
      $response = Http::get("{$url}/products&display=full&output_format=JSON&ws_key={$ws_key}", []);
      return json_decode($response, true);
    }
    public function getOrders(): array
    {
      $url = $this->url;
      $ws_key = $this->ws_key;
      $response = Http::get("{$url}/orders&display=full&output_format=JSON&ws_key={$ws_key}", []);
      return json_decode($response, true);
    }

    public function getCustomers(): array
{
    $url = $this->url;
    $ws_key = $this->ws_key;

    $response = Http::get(
        "{$url}/customers?display=full&output_format=JSON&ws_key={$ws_key}"
    );

    if (!$response->successful()) {
        throw new \Exception('Error al obtener clientes de PrestaShop');
    }

    return $response->json();
}
public function getProductBySku(string $sku): array
{
    $url = $this->url;
    $ws_key = $this->ws_key;

    $response = Http::get("{$url}/products", [
        'filter[reference]' => sprintf('%s', $sku),
        'display' => 'full',
        'output_format' => 'JSON',
        'ws_key' => $ws_key,
    ]);

    if (!$response->successful()) {
        return [
            'status' => 'error',
            'data' => null,
            'errors' => [
                [
                    'code' => (string) $response->status(),
                    'message' => 'ErrorFetching'
                ]
            ]
        ];
    }

    $data = $response->json();
    $products = $data['products'] ?? $data;

    if (empty($products)) {
        return [
            'status' => 'error',
            'data' => null,
            'errors' => [
                [
                    'code' => '404',
                    'message' => 'NotFound'
                ]
            ]
        ];
    }

    return [
        'status' => 'success',
        'data' => $products,
        'errors' => []
    ];
}
public function getOrderByReference(string $reference): ?array
{
    $url = $this->url;
    $ws_key = $this->ws_key;

    $response = Http::get("{$url}/orders", [
        'filter[reference]' => sprintf('["%s"]', $reference),
        'display' => 'full',
        'output_format' => 'JSON',
        'ws_key' => $ws_key,
    ]);

    if (!$response->successful()) {
        return null;
    }

    $data = $response->json();

    $orders = $data['orders'] ?? [];

    if (empty($orders)) {
        return null;
    }

    return $orders[0];
}
public function getOrders2(): array
{
    $url = $this->url;
    $ws_key = $this->ws_key;

    $response = Http::get("{$url}/orders", [
        'display' => 'full',
        'output_format' => 'JSON',
        'ws_key' => $ws_key,
    ]);

    if (!$response->successful()) {
        return [
            'status' => 'error',
            'data' => null,
            'errors' => [
                [
                    'code' => (string) $response->status(),
                    'message' => 'Error fetching orders from PrestaShop'
                ]
            ]
        ];
    }

    $data = $response->json();

    $orders = $data['orders'] ?? [];

    return [
        'status' => 'success',
        'data' => $orders,
        'errors' => []
    ];
}

public function createProduct(array $product): array
{
    $url        = $this->url;
    $ws_key     = $this->ws_key;

    $name        = $product['name'] ?? 'Sin nombre';
    $description = (!empty($product['description']) && $product['description'] !== false) 
        ? $product['description'] 
        : '';
    $price       = number_format((float)($product['list_price'] ?? 0), 6, '.', '');
    $reference   = $product['default_code'] ?? '';
    $linkRewrite = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

   $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <product>
        <state>1</state>
        <price>{$price}</price>
        <reference>{$reference}</reference>
        <active>1</active>
        <available_for_order>1</available_for_order>
        <show_price>1</show_price>
        <visibility>both</visibility>
        <minimal_quantity>1</minimal_quantity>
        <id_category_default>2</id_category_default>
        <name>
        <language id="1"><![CDATA[{$name}]]></language>
        </name>
        <description>
        <language id="1"><![CDATA[{$description}]]></language>
        </description>
        <description_short>
        <language id="1"><![CDATA[]]></language>
        </description_short>
        <link_rewrite>
        <language id="1"><![CDATA[{$linkRewrite}]]></language>
        </link_rewrite>
        <associations>
        <categories>
            <category><id>2</id></category>
        </categories>
        </associations>
    </product>
    </prestashop>
    XML;

    $response = Http::withHeaders([
        'Content-Type' => 'application/xml',
    ])->withBody($xml, 'application/xml')
      ->post("{$url}/products?ws_key={$ws_key}");

    if (!$response->successful()) {
        return [
            'status'  => 'error',
            'message' => 'Error al crear producto',
            'details' => $response->body(),
        ];
    }

    $createdXml = simplexml_load_string($response->body());
    $productId  = (int)($createdXml->product->id ?? 0);

    $stockResult = null;
    if ($productId > 0 && isset($product['qty_available'])) {
        $stockResult = $this->updateStock($productId, (int)$product['qty_available']);
    }

    return [
        'status'       => 'success',
        'message'      => "Producto '{$name}' creado con ID {$productId}",
        'ps_id'        => $productId,
    ];
}

private function updateStock(int $productId, int $quantity): void
{
    $url    = $this->url;
    $ws_key = $this->ws_key;

    $response = Http::get("{$url}/stock_availables", [
        'filter[id_product]' => $productId,
        'display'            => 'full',
        'output_format'      => 'JSON',
        'ws_key'             => $ws_key,
    ]);

    $data    = $response->json();
    $stockId = $data['stock_availables'][0]['id'] ?? null;

   $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <stock_available>
        <id>{$stockId}</id>
        <id_product>{$productId}</id_product>
        <id_product_attribute>0</id_product_attribute>
        <id_shop>1</id_shop>
        <quantity>{$quantity}</quantity>
        <depends_on_stock>0</depends_on_stock>
        <out_of_stock>2</out_of_stock>
    </stock_available>
    </prestashop>
    XML;

    $putResponse = Http::withHeaders([
        'Content-Type' => 'application/xml',
    ])->withBody($xml, 'application/xml')
      ->put("{$url}/stock_availables/{$stockId}?ws_key={$ws_key}");
}

    // Desactiva un producto en PrestaShop.
    public function desactivarProductoPorSku(string $sku): array
    {
        $resp = $this->getProductBySku($sku);
        if ($resp['status'] !== 'success' || empty($resp['data'])) {
            return [
                'status' => 'error',
                'message' => 'Producto no encontrado',
            ];
        }

        $product = $resp['data'][0];
        $id = $product['id'] ?? null;
        if (empty($id)) {
            return [
                'status' => 'error',
                'message' => 'ID del producto no disponible',
            ];
        }

        $xml = <<<XML
    <?xml version="1.0" encoding="UTF-8"?>
    <prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <product>
        <id>{$id}</id>
        <active>0</active>
    </product>
    </prestashop>
    XML;

        $response = Http::withHeaders([
            'Content-Type' => 'application/xml',
        ])->withBody($xml, 'application/xml')
          ->patch("{$this->url}/products/{$id}?ws_key={$this->ws_key}");

        if (!$response->successful()) {
            return [
                'status' => 'error',
                'message' => 'Error al desactivar producto',
                'details' => $response->body(),
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Producto desactivado',
            'id' => $id,
        ];
    }

    public function updateProductByReference(string $reference, array $payload, string $method = 'PATCH'): array
    {
        $existing = $this->getProductBySku($reference);

        if (($existing['status'] ?? 'error') !== 'success' || empty($existing['data'])) {
            return [
                'status' => 'error',
                'data' => null,
                'errors' => [
                    [
                        'code' => '404',
                        'message' => 'Product not found by reference',
                    ],
                ],
            ];
        }

        $product = $existing['data'][0] ?? null;
        $id = (int) ($product['id'] ?? 0);

        if ($id <= 0) {
            return [
                'status' => 'error',
                'data' => null,
                'errors' => [
                    [
                        'code' => '400',
                        'message' => 'Invalid product id',
                    ],
                ],
            ];
        }

        $name = trim((string) ($payload['name'] ?? $this->extractLanguageValue($product['name'] ?? null, '')));
        $description = (string) ($payload['description'] ?? $this->extractLanguageValue($product['description'] ?? null, ''));
        $price = number_format((float) ($payload['price'] ?? $product['price'] ?? 0), 6, '.', '');
        $targetReference = trim((string) ($payload['reference'] ?? $product['reference'] ?? $reference));
        $active = array_key_exists('active', $payload)
            ? ((int) ((bool) $payload['active']))
            : (int) ($product['active'] ?? 1);

        $linkRewrite = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">
    <product>
        <id>{$id}</id>
        <price>{$price}</price>
        <reference><![CDATA[{$targetReference}]]></reference>
        <active>{$active}</active>
        <name>
            <language id="1"><![CDATA[{$name}]]></language>
        </name>
        <description>
            <language id="1"><![CDATA[{$description}]]></language>
        </description>
        <link_rewrite>
            <language id="1"><![CDATA[{$linkRewrite}]]></language>
        </link_rewrite>
    </product>
</prestashop>
XML;

        $httpMethod = strtoupper($method);
        if (!in_array($httpMethod, ['PUT', 'PATCH'], true)) {
            $httpMethod = 'PATCH';
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/xml',
        ])->withBody($xml, 'application/xml')
          ->send($httpMethod, "{$this->url}/products/{$id}?ws_key={$this->ws_key}");

        if (!$response->successful()) {
            return [
                'status' => 'error',
                'data' => [
                    'id' => $id,
                    'reference' => $reference,
                    'method' => $httpMethod,
                ],
                'errors' => [
                    [
                        'code' => (string) $response->status(),
                        'message' => 'Error updating product',
                        'details' => $response->body(),
                    ],
                ],
            ];
        }

        return [
            'status' => 'success',
            'data' => [
                'id' => $id,
                'reference' => $targetReference,
                'method' => $httpMethod,
            ],
            'errors' => [],
        ];
    }

    private function extractLanguageValue(mixed $value, string $fallback = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (isset($value['value']) && is_string($value['value'])) {
                return $value['value'];
            }

            $first = $value[0] ?? null;
            if (is_array($first) && isset($first['value']) && is_string($first['value'])) {
                return $first['value'];
            }
        }

        return $fallback;
    }

}