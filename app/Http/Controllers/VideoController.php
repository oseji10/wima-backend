<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\MediaUploader;


class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Video::query()
            ->where('is_active', true);

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $videos = $query
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'title',
                'src',
                'thumbnail',
                'category',
                'duration',
            ]);

        return response()->json([
            'success' => true,
            'data' => $videos,
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Video::query()
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
        $video = Video::query()
            ->where('is_active', true)
            ->findOrFail($id, [
                'id',
                'title',
                'src',
                'thumbnail',
                'category',
                'duration',
            ]);

        return response()->json([
            'success' => true,
            'data' => $video,
        ]);
    }




    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'duration' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],

            // optional direct URLs
            'src' => ['nullable', 'url', 'max:2048'],
            'thumbnail' => ['nullable', 'url', 'max:2048'],

            // uploaded files
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg,video/quicktime', 'max:102400'],
            'thumbnail_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (($request->filled('src') && $request->hasFile('video_file')) ||
            (!$request->filled('src') && !$request->hasFile('video_file'))) {
            return response()->json([
                'success' => false,
                'message' => 'Provide either src or video_file, but not both.',
            ], 422);
        }

        $videoSrc = $validated['src'] ?? null;
        $thumbnailSrc = $validated['thumbnail'] ?? null;

        if ($request->hasFile('video_file')) {
            $videoSrc = MediaUploader::upload(
                $request->file('video_file'),
                config('media.paths.videos'),
                'videos'
            );
        }

        if ($request->hasFile('thumbnail_file')) {
            $thumbnailSrc = MediaUploader::upload(
                $request->file('thumbnail_file'),
                config('media.paths.video_thumbnails'),
                'video-thumbnails'
            );
        }

        $video = Video::create([
            'title' => $validated['title'],
            'src' => $videoSrc,
            'thumbnail' => $thumbnailSrc,
            'category' => strtolower(trim($validated['category'])),
            'duration' => $validated['duration'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Video created successfully.',
            'data' => $video,
        ], 201);
    }

}