<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DispenseController extends Controller
{
    public function dispense(Request $request)
    {
        $request->validate([
            'prescription_id' => 'required',
            'quantity' => 'required|numeric|min:1',
        ]);

        $token = $request->bearerToken(); // Get JWT from the request
        $apiKey = env('CHILDHOOD_PORTAL_API_KEY'); // Load API key from .env

        $response = Http::withHeaders([
            'Authorization' => "Bearer $token",
            'X-Childhood-Portal-Key' => $apiKey,
        ])->post('http://your-inventory-api-domain/api/dispense', [
            'prescription_id' => $request->prescription_id,
            'quantity' => $request->quantity,
        ]);

        return $response->json();
    }
}