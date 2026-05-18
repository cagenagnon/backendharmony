<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FedaPayService
{
    protected string $baseUrl;

    protected string $secretKey;

    public function __construct()
    {
        $mode = env('FEDAPAY_MODE', 'sandbox');
        $this->secretKey = env('FEDAPAY_SECRET_KEY', '');
        $this->baseUrl = $mode === 'live' ? 'https://api.fedapay.com/v1/' : 'https://sandbox-api.fedapay.com/v1/';
    }

    public function createTransaction(array $data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Accept' => 'application/json',
            ])->post($this->baseUrl.'transactions', $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('FedaPay createTransaction failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        } catch (\Throwable $e) {
            Log::error('FedaPay createTransaction exception', ['message' => $e->getMessage()]);

            return null;
        }
    }

    public function getTransaction(string $id)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->secretKey,
                'Accept' => 'application/json',
            ])->get($this->baseUrl.'transactions/'.$id);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('FedaPay getTransaction failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        } catch (\Throwable $e) {
            Log::error('FedaPay getTransaction exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
