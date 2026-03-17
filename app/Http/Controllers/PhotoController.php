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
            ->orderByDesc('created_at')
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

        // single URL fallback
        'src' => ['nullable', 'url', 'max:2048'],

        // multiple upload support
        'image_files' => ['nullable', 'array'],
        'image_files.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
    ]);

    // Case 1: single URL
    if ($request->filled('src')) {
        $photo = Photo2::create([
            'title' => $validated['title'] ?? null,
            'src' => $validated['src'],
            'category' => strtolower(trim($validated['category'])),
            'instagram_url' => $validated['instagram_url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
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
            'category' => strtolower(trim($validated['category'])),
            'instagram_url' => $validated['instagram_url'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $uploadedPhotos[] = $photo;
    }

    return response()->json([
        'success' => true,
        'message' => count($uploadedPhotos) . ' photos uploaded successfully.',
        'data' => $uploadedPhotos,
    ], 201);
}

}