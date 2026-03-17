<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Photo2;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Support\MediaUploader;


class PhotoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Photo2::query()
            ->where('is_active', true);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $photos = $query
            ->orderBy('sort_order')
            ->orderBy('created_at', 'asc')
            ->get([
                'id',
                'title',
                'src',
                'category',
                'instagram_url',
            ]);

        return response()->json([
            'success' => true,
            'data' => $photos,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Photo2::query()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $photo = Photo2::query()
            ->where('is_active', true)
            ->findOrFail($id, [
                'id',
                'title',
                'src',
                'category',
                'instagram_url',
            ]);

        return response()->json([
            'success' => true,
            'data' => $photo,
        ]);
    }


public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'title' => ['nullable', 'string', 'max:255'],
        'category' => ['required', 'string', 'max:100'],
        'instagram_url' => ['nullable', 'url', 'max:2048'],
        'sort_order' => ['nullable', 'integer', 'min:0'],
        'is_active' => ['nullable', 'boolean'],

        'src' => ['nullable', 'url', 'max:2048'],

        'image_files' => ['nullable', 'array'],
        'image_files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
    ]);

    $category = strtolower(trim($validated['category']));
    $isActive = $validated['is_active'] ?? true;

    // If sort_order was supplied, start from it.
    // Otherwise start from current max + 1.
    $nextSortOrder = array_key_exists('sort_order', $validated)
        ? (int) $validated['sort_order']
        : ((int) Photo2::max('sort_order')) + 1;

    // Case 1: single URL
    if ($request->filled('src')) {
        $photo = Photo2::create([
            'title' => $validated['title'] ?? null,
            'src' => $validated['src'],
            'category' => $category,
            'instagram_url' => $validated['instagram_url'] ?? null,
            'sort_order' => $nextSortOrder,
            'is_active' => $isActive,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Photo created successfully.',
            'data' => [$photo],
        ], 201);
    }

    // Case 2: multiple uploads
    if (!$request->hasFile('image_files')) {
        return response()->json([
            'success' => false,
            'message' => 'Provide either src or image_files.',
        ], 422);
    }

    $uploadedPhotos = [];

    foreach ($request->file('image_files') as $file) {
        $imageSrc = \App\Support\MediaUploader::upload(
            $file,
            config('media.paths.images'),
            'images'
        );

        $photo = Photo2::create([
            'title' => $validated['title'] ?? null,
            'src' => $imageSrc,
            'category' => $category,
            'instagram_url' => $validated['instagram_url'] ?? null,
            'sort_order' => $nextSortOrder,
            'is_active' => $isActive,
        ]);

        $uploadedPhotos[] = $photo;
        $nextSortOrder++;
    }

    return response()->json([
        'success' => true,
        'message' => count($uploadedPhotos) . ' photos uploaded successfully.',
        'data' => $uploadedPhotos,
    ], 201);
}

}