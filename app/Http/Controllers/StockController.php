<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Stock;
use App\Models\Products;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StockController extends Controller
{
     public function index()
    {
        $stocks = Stock::with('product', 'lga_info')->get();
        return response()->json($stocks);
       
    }


     public function availableStock()
    {
        $user = auth()->user();
        $stocks = Stock::with('product', 'lga_info')
        ->where('lgaId', $user->staff->lga)
        ->get();
        return response()->json($stocks);
       
    }


    public function store(Request $request)
{
    $validated = $request->validate([
        'productId' => 'required|string|exists:products,productId',
        'quantityReceived' => 'required|integer|min:1',
    ]);
    $user = auth()->user();
    $stock = Stock::create([
        'productId' => $validated['productId'],
        'quantityReceived' => $request->quantityReceived,
        'lgaId' => $user->staff->lga,
        'receivedBy' => $user->id,
    ]);

    // Load the product relationship to include productName in the response
    $stock->load('product');

    return response()->json([
        'stockId' => $stock->stockId,
        'productId' => $stock->productId,
        'quantityReceived' => $stock->quantityReceived,
        'product' => $stock->product ? [
            'productName' => $stock->product->productName
        ] : null
    ], 201);
}

   public function update(Request $request, $stockId)
{
    $validated = $request->validate([
        'productId' => 'required|string|exists:products,productId',
        'quantityReceived' => 'required|integer|min:1',
    ]);

    $stock = Stock::where('stockId', $stockId)->first();
    if (!$stock) {
        return response()->json(['message' => 'Stock type not found'], 404);
    }

    $stock->update($validated);
    
     $stock->load('product');

    return response()->json([
        'stockId' => $stock->stockId,
        'productId' => $stock->productId,
        'quantityReceived' => $stock->quantityReceived,
        'product' => $stock->product ? [
            'productName' => $stock->product->productName
        ] : null
    ], 200);
}

    public function destroy($stockId)
    {
        $stock = Stock::where('stockId', $stockId)->first();
        if (!$stock) {
            return response()->json(['message' => 'Stock type not found'], 404);
        }

        $stock->delete();
        return response()->json(['message' => 'Stock type deleted successfully'], 200);
    }
    // public function show($stockId)
    // {
    //     $stock = Stock::where('stockId', $stockId)->first();
    //     if (!$stock) {
    //         return response()->json(['message' => 'Stock type not found'], 404);
    //     }
    //     return response()->json($stock);
    // }
   
}
