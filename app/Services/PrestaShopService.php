<?php

namespace App\Services;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Http;

class PrestaShopService
{
    protected string $url;
    protected string $token;

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

}