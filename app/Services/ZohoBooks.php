<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ZohoBooks
{
    private function accessToken()
    {
        
        $response = Http::asForm()->post('https://accounts.zoho.com/oauth/v2/token', [
            'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
            'client_id' => env('ZOHO_CLIENT_ID'),
            'client_secret' => env('ZOHO_CLIENT_SECRET'),
            'grant_type' => 'refresh_token',
        ]);

        return $response->json()['access_token'];
    }

    private function headers()
    {
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $this->accessToken(),
            'Content-Type' => 'application/json'
        ];
    }

    public function createCustomer($data)
    {
        $url = env('ZOHO_BASE') . '/contacts?organization_id=' . env('ZOHO_ORG_ID');

        return Http::withHeaders($this->headers())->post($url, $data)->json();
    }

    public function createInvoice($data)
    {
        $url = env('ZOHO_BASE') . '/invoices?organization_id=' . env('ZOHO_ORG_ID');

        return Http::withHeaders($this->headers())->post($url, $data)->json();
    }
}
