<?php

return [
    'domain' => env('RESOURCE_DOMAIN', 'https://resources.wimanigeria.com'),

    'paths' => [
        'root' => env('RESOURCE_ROOT', '/var/www/resources.wimanigeria.com'),
        'images' => env('RESOURCE_IMAGES_DIR', '/var/www/resources.wimanigeria.com/images'),
        'videos' => env('RESOURCE_VIDEOS_DIR', '/var/www/resources.wimanigeria.com/videos'),
        'audio' => env('RESOURCE_AUDIO_DIR', '/var/www/resources.wimanigeria.com/audio'),
        'video_thumbnails' => env('RESOURCE_VIDEO_THUMBNAILS_DIR', '/var/www/resources.wimanigeria.com/video-thumbnails'),
        'audio_covers' => env('RESOURCE_AUDIO_COVERS_DIR', '/var/www/resources.wimanigeria.com/audio-covers'),
    ],
];