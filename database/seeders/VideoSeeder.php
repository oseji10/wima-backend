<?php

namespace Database\Seeders;

use App\Models\Video;
use Illuminate\Database\Seeder;

class VideoSeeder extends Seeder
{
    public function run(): void
    {
        $videos = [
            [
                'title' => 'Threshing Activity',
                'src' => 'https://resources.wimanigeria.com/videos/1.mp4',
                'thumbnail' => '/assets/videos/thumbnails/field1.png',
                'category' => 'field work',
                'duration' => '0:10',
                'sort_order' => 1,
            ],
            [
                'title' => 'Equipment Factory',
                'src' => 'https://resources.wimanigeria.com/videos/3.mp4',
                'thumbnail' => '/assets/videos/thumbnails/field3.png',
                'category' => 'field work',
                'duration' => '0:22',
                'sort_order' => 2,
            ],
            [
                'title' => 'Spraying Activity',
                'src' => 'https://resources.wimanigeria.com/videos/5.mp4',
                'thumbnail' => '/assets/videos/thumbnails/field5.png',
                'category' => 'field work',
                'duration' => '0:06',
                'sort_order' => 3,
            ],
            [
                'title' => 'Cargo transport',
                'src' => 'https://resources.wimanigeria.com/videos/farmer.mp4',
                'thumbnail' => '/assets/videos/thumbnails/field6.png',
                'category' => 'field work',
                'duration' => '0:58',
                'sort_order' => 4,
            ],
            [
                'title' => 'Jingle',
                'src' => 'https://resources.wimanigeria.com/videos/join-wima.mp4',
                'thumbnail' => '/assets/videos/thumbnails/jingle.png',
                'category' => 'corporate',
                'duration' => '1:10',
                'sort_order' => 5,
            ],
            [
                'title' => 'WIMA',
                'src' => 'https://resources.wimanigeria.com/videos/C4103.mp4',
                'thumbnail' => '/assets/videos/thumbnails/wima.png',
                'category' => 'corporate',
                'duration' => '2:28',
                'sort_order' => 6,
            ],
            [
                'title' => 'WIMA Agri-Mech Fair 2026',
                'src' => 'https://resources.wimanigeria.com/videos/IMG_1193.mp4',
                'thumbnail' => '/assets/videos/thumbnails/depgv.png',
                'category' => 'corporate',
                'duration' => '0:49',
                'sort_order' => 7,
            ],
        ];

        foreach ($videos as $video) {
            Video::updateOrCreate(
                ['src' => $video['src']],
                array_merge($video, ['is_active' => true])
            );
        }
    }
}