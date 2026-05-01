<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadS3File
{
    /**
     * @param mixed $file
     * @param string $folder
     * @return string
     */
    public static function getUrl(mixed $file, string $folder = '/'): string
    {
        $url = config('services.images.logo', 'https://fungometrics.s3.amazonaws.com/logo.png');
        try {
            $filename = "fungo-".time().'.'.$file->extension();
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
            $image = Storage::disk($disk)->putFileAs(ltrim($folder, '/'), $file, $filename);
            if (isset($image)) {
                $url = Storage::disk($disk)->url($image);
            }
            return $url;
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            return $url;
        }
    }

    public static function deleteObject(string $file, string $folder = '/'): void
    {
        try {
            $name = explode('/', $file);
            Storage::disk('s3')->delete($folder."/".$name[4]);
        } catch (Exception $exception) {
            Log::error("NOT DELETE FILE".$exception->getMessage());
        }
    }
}
