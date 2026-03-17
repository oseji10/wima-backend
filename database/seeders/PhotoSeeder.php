<?php

namespace Database\Seeders;

use App\Models\Photo2;
use Illuminate\Database\Seeder;

class PhotoSeeder extends Seeder
{
    public function run(): void
    {
        $photos = [
            ['src' => 'https://resources.wimanigeria.com/images/slide/1.jpg', 'category' => 'corporate', 'sort_order' => 1, 'title' => 'Corporate Photo 1'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/2.jpg', 'category' => 'corporate', 'sort_order' => 2, 'title' => 'Corporate Photo 2'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/3.jpg', 'category' => 'corporate', 'sort_order' => 3, 'title' => 'Corporate Photo 3'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/4.jpg', 'category' => 'corporate', 'sort_order' => 4, 'title' => 'Corporate Photo 4'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/5.jpg', 'category' => 'corporate', 'sort_order' => 5, 'title' => 'Corporate Photo 5'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/6.jpg', 'category' => 'corporate', 'sort_order' => 6, 'title' => 'Corporate Photo 6'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/7.jpg', 'category' => 'corporate', 'sort_order' => 7, 'title' => 'Corporate Photo 7'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/8.jpg', 'category' => 'corporate', 'sort_order' => 8, 'title' => 'Corporate Photo 8'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/9.jpg', 'category' => 'corporate', 'sort_order' => 9, 'title' => 'Corporate Photo 9'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/10.jpg', 'category' => 'corporate', 'sort_order' => 10, 'title' => 'Corporate Photo 10'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/11.jpg', 'category' => 'corporate', 'sort_order' => 11, 'title' => 'Corporate Photo 11'],
            ['src' => 'https://resources.wimanigeria.com/images/slide/12.jpg', 'category' => 'corporate', 'sort_order' => 12, 'title' => 'Corporate Photo 12'],

            ['src' => 'https://backend.wimanigeria.com/media/1.jpg', 'category' => 'field work', 'sort_order' => 13, 'title' => 'Field Work 1'],
            ['src' => 'https://backend.wimanigeria.com/media/2.jpg', 'category' => 'field work', 'sort_order' => 14, 'title' => 'Field Work 2'],
            ['src' => 'https://backend.wimanigeria.com/media/3.jpg', 'category' => 'field work', 'sort_order' => 15, 'title' => 'Field Work 3'],
            ['src' => 'https://backend.wimanigeria.com/media/4.jpg', 'category' => 'field work', 'sort_order' => 16, 'title' => 'Field Work 4'],
            ['src' => 'https://backend.wimanigeria.com/media/5.jpg', 'category' => 'field work', 'sort_order' => 17, 'title' => 'Field Work 5'],
            ['src' => 'https://backend.wimanigeria.com/media/6.jpg', 'category' => 'field work', 'sort_order' => 18, 'title' => 'Field Work 6'],
            ['src' => 'https://backend.wimanigeria.com/media/7.jpg', 'category' => 'field work', 'sort_order' => 19, 'title' => 'Field Work 7'],
            ['src' => 'https://backend.wimanigeria.com/media/8.jpg', 'category' => 'field work', 'sort_order' => 20, 'title' => 'Field Work 8'],
            ['src' => 'https://backend.wimanigeria.com/media/9.jpg', 'category' => 'field work', 'sort_order' => 21, 'title' => 'Field Work 9'],
            ['src' => 'https://backend.wimanigeria.com/media/10.jpg', 'category' => 'field work', 'sort_order' => 22, 'title' => 'Field Work 10'],
            ['src' => 'https://backend.wimanigeria.com/media/11.jpg', 'category' => 'field work', 'sort_order' => 23, 'title' => 'Field Work 11'],
            ['src' => 'https://backend.wimanigeria.com/media/12.jpg', 'category' => 'field work', 'sort_order' => 24, 'title' => 'Field Work 12'],
            ['src' => 'https://backend.wimanigeria.com/media/13.jpg', 'category' => 'field work', 'sort_order' => 25, 'title' => 'Field Work 13'],
            ['src' => 'https://backend.wimanigeria.com/media/14.jpg', 'category' => 'field work', 'sort_order' => 26, 'title' => 'Field Work 14'],
            ['src' => 'https://backend.wimanigeria.com/media/15.jpg', 'category' => 'field work', 'sort_order' => 27, 'title' => 'Field Work 15'],
            ['src' => 'https://backend.wimanigeria.com/media/16.jpg', 'category' => 'field work', 'sort_order' => 28, 'title' => 'Field Work 16'],
            ['src' => 'https://backend.wimanigeria.com/media/17.jpg', 'category' => 'field work', 'sort_order' => 29, 'title' => 'Field Work 17'],
            ['src' => 'https://backend.wimanigeria.com/media/18.jpg', 'category' => 'field work', 'sort_order' => 30, 'title' => 'Field Work 18'],
            ['src' => 'https://backend.wimanigeria.com/media/19.jpg', 'category' => 'field work', 'sort_order' => 31, 'title' => 'Field Work 19'],
            ['src' => 'https://backend.wimanigeria.com/media/20.jpg', 'category' => 'field work', 'sort_order' => 32, 'title' => 'Field Work 20'],
            ['src' => 'https://backend.wimanigeria.com/media/21.jpg', 'category' => 'field work', 'sort_order' => 33, 'title' => 'Field Work 21'],
            ['src' => 'https://backend.wimanigeria.com/media/22.jpg', 'category' => 'field work', 'sort_order' => 34, 'title' => 'Field Work 22'],
            ['src' => 'https://backend.wimanigeria.com/media/23.jpg', 'category' => 'field work', 'sort_order' => 35, 'title' => 'Field Work 23'],
            ['src' => 'https://backend.wimanigeria.com/media/24.jpg', 'category' => 'field work', 'sort_order' => 36, 'title' => 'Field Work 24'],
            ['src' => 'https://backend.wimanigeria.com/media/25.jpg', 'category' => 'field work', 'sort_order' => 37, 'title' => 'Field Work 25'],
        ];

        foreach ($photos as $photo) {
          Photo2::updateOrCreate(
                ['src' => $photo['src']],
                array_merge($photo, [
                    'is_active' => true,
                    'instagram_url' => null,
                ])
            );
        }
    }
}