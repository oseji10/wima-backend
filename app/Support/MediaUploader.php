<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MediaUploader
{
    public static function upload(UploadedFile $file, string $directoryPath, string $urlPrefix): string
    {
        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid()->toString() . '.' . $extension;

        $file->move($directoryPath, $filename);

        return rtrim(config('media.domain'), '/') . '/' . trim($urlPrefix, '/') . '/' . $filename;
    }
}