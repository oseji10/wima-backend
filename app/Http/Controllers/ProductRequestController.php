<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductRequest;
class ProductRequestController extends Controller
{
    public function index()
    {
        $productrequests = ProductRequest::all();
        return response()->json($productrequests);
    }
    public function show($productrequestId)
    {
        $productrequest = ProductRequest::find($productrequestId);
        if (!$productrequest) {
            return response()->json(['message' => 'ProductRequest not found'], 404);
        }
        return response()->json($productrequest);
    }

   public function store(Request $request)
    {
        // Validate the payload
        $validatedData = $request->validate([
            'products' => 'required|array|min:1', // Ensure products is an array with at least one item
            'products.*.productId' => 'required|exists:products,productId', // Validate each productId exists
            'products.*.quantityRequested' => 'required|integer|min:1', // Validate quantity as an integer
        ]);

        $createdRequests = [];

        // Loop through each product in the payload
        foreach ($validatedData['products'] as $productData) {
            // Create a new ProductRequest for each product
            $productRequest = ProductRequest::create([
                'productId' => $productData['productId'],
                'quantityRequested' => $productData['quantityRequested'],
                'requestDate' => now(), // Store the request date
                'quantityDispatched' => 0, // Default value
                'quantityReceived' => 0, // Default value
                // Add other fields like status, approvedBy, batchNumber if needed
            ]);

            $createdRequests[] = [
                'productRequestId' => $productRequest->productRequestId,
                'productId' => $productRequest->productId,
                'productName' => $productRequest->product ? $productRequest->product->productName : 'Unknown', // Assuming a product relationship
                'quantityRequested' => $productRequest->quantityRequested,
                'quantityDispatched' => $productRequest->quantityDispatched,
                'quantityReceived' => $productRequest->quantityReceived,
                'requestDate' => $productRequest->requestDate,
            ];
        }

        // Return a JSON response
        return response()->json([
            'message' => 'Product requests created successfully',
            'productRequests' => $createdRequests,
        ], 201);
    }

    public function update(Request $request, $productrequestId)
    {
        $productrequest = ProductRequest::find($productrequestId);
        if (!$productrequest) {
            return response()->json(['message' => 'ProductRequest not found'], 404);
        }

        $data = $request->all();
        $productrequest->update($data);

        return response()->json([
            'message' => 'ProductRequest updated successfully',
            'productrequestId' => $productrequest->productrequestId,
            'productrequestName' => $productrequest->productrequestName], 201); // HTTP status code 201: Created

    }
    
    public function destroy($productrequestId)
    {
        $productrequest = ProductRequest::find($productrequestId);
        if (!$productrequest) {
            return response()->json(['message' => 'ProductRequest not found'], 404);
        }

        $productrequest->delete();
        return response()->json(['message' => 'ProductRequest deleted successfully']);
    }
    
}
