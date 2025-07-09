<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
use App\Models\ProductType;
class ProductsController extends Controller
{
    public function index()
    {
        $products = Products::with('product_type', 'added_by', 'product_images')->get();
        return response()->json($products);
       
    }

  public function productTypes()
    {
        $productTypes = ProductType::all();
        return response()->json($productTypes);
    }

    public function store(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
        'productName' => 'required|string|max:255',
        'cost' => 'required|string|max:255',
        'productType' => 'required|integer|exists:product_type,typeId',
        'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Adjust validation rules as needed
    ]);
    
        // Create a new user with the data (ensure that the fields are mass assignable in the model)
        $validatedData['addedBy'] = auth()->user()->id; // Assuming the user is authenticated and you want to set the addedBy field to the current user's ID
        $products = Products::create($validatedData);

        $imagePath = $request->file('image')->store('product_images', 'public');
        
        // Assuming you have a ProductImage model to handle product images
        $products->product_images()->create([
            'imagePath' => $imagePath,
            'productId' => $products->productId, // Assuming productId is the foreign key in ProductImage
        ]);
        
        $products->load(['product_type', 'added_by', 'product_images']);
        return response()->json([
            'productId' => $products->productId,
            'productName' => $products->productName,
            'productType' => $products->product_type->typeName,
            'cost' => $products->cost,
            'addedBy' => $products->added_by->firstName . ' ' . $products->added_by->lastName,
            'image_path' => $imagePath,
        ], 201); // HTTP status code 201: Created
    }



    public function show($productId)
    {
        $product = Products::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product);
    }

    public function edit(Request $request, $productId)
    {
        $validatedData = $request->validate([
            'productName' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'productType' => 'nullable|integer|exists:product_type,typeId',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
        ]);

        $product = Products::with(['product_type', 'added_by', 'product_images'])->find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Update product fields if provided
        if ($request->has('productName')) {
            $product->productName = $validatedData['productName'];
        }

        if ($request->has('cost')) {
            $product->cost = $validatedData['cost'];
        }

        if ($request->has('productType')) {
            $product->productType = $validatedData['productType'];
        }

        // Handle image upload if provided
        if ($request->hasFile('image')) {
            // Ensure the file is valid
            if ($request->file('image')->isValid()) {
                // Get the existing image record
                $existingImage = $product->product_images()->first();

                // Store new image
                $imagePath = $request->file('image')->store('product_images', 'public');

                if ($existingImage) {
                    // Delete the old image file if it exists
                    if ($existingImage->imagePath && Storage::disk('public')->exists($existingImage->imagePath)) {
                        Storage::disk('public')->delete($existingImage->imagePath);
                    }

                    // Update the existing image record
                    $existingImage->imagePath = $imagePath;
                    $existingImage->save();
                } else {
                    // Create a new image record if none exists
                    $product->product_images()->create([
                        'imagePath' => $imagePath,
                        'productId' => $product->productId,
                    ]);
                }
            } else {
                return response()->json(['message' => 'Invalid image file'], 422);
            }
        }

        // Save the updated product
        $product->save();

        // Load the image path for the response
        $image = $product->product_images()->first();
        $imageUrl = $image ? Storage::url($image->imagePath) : null;

        return response()->json([
            'message' => 'Product updated successfully',
            'productId' => $product->productId,
            'productName' => $product->productName,
            'productType' => $product->productType, // Return ID to match frontend
            'cost' => $product->cost,
            'status' => $product->status ?? 'Active', // Include status as frontend expects it
            'imageUrl' => $imageUrl, // Match frontend expectation
            'product_type' => $product->product_type ? [
                'typeId' => $product->product_type->typeId,
                'typeName' => $product->product_type->typeName,
            ] : null,
            'addedBy' => $product->added_by ? $product->added_by->firstName . ' ' . $product->added_by->lastName : null,
        ], 200);
    }


    

    public function destroy($productId)
    {
        $product = Products::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function productImage($productId)
    {
        $product = Products::find($productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $images = $product->product_images; // Assuming product_images is a relationship defined in the Products model
        return response()->json($images);
    }

    public function addProductImage(Request $request)
    {
        $product = Products::find($request->productId);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Validate the request data
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // Adjust validation rules as needed
        ]);

        // Store the image and associate it with the product
        $imagePath = $request->file('image')->store('product_images', 'public');
        
        // Assuming you have a ProductImage model to handle product images
        $product->product_images()->create(['image_path' => $imagePath]);

        return response()->json(['message' => 'Image added successfully', 'image_path' => $imagePath], 201);
    }
    
}
