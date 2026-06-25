<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UploadS3File
{
    /**
     * @param mixed $file
     * @param string $folder
     * @return string
     */
    public static function getUrl(mixed $file, string $folder = '/'): string
    {
        try {
            $filename = "fungo-".time().'.'.$file->extension();
            $disk = config('filesystems.default') === 's3' ? 's3' : 'public';

            // Write WITHOUT requesting a public-read ACL. The S3 bucket has ACLs locked
            // down ("Block public ACLs" / Object Ownership = bucket owner enforced), so
            // asking for a public ACL makes the PutObject FAIL and the object never lands
            // (confirmed: such writes report success-ish but the object is missing from S3).
            // Public read is granted by the bucket POLICY on players/*, so no per-object
            // ACL is needed — or wanted.
            $image = Storage::disk($disk)->putFileAs(ltrim($folder, '/'), $file, $filename);
            if (!$image) {
                throw new RuntimeException('The profile image could not be stored.');
            }

            return self::publicUrl($disk, $image);
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            throw $exception;
        }
    }

    private static function publicUrl(string $disk, string $path): string
    {
        if ($disk !== 's3') {
            return Storage::disk($disk)->url($path);
        }

        $publicUrl = trim((string) config('filesystems.disks.s3.public_url', ''));
        if ($publicUrl !== '') {
            return rtrim($publicUrl, '/').'/'.ltrim($path, '/');
        }

        $bucket = trim((string) config('filesystems.disks.s3.bucket', ''));
        $region = trim((string) config('filesystems.disks.s3.region', ''));
        if ($bucket === '' || $region === '') {
            throw new RuntimeException('The S3 public image URL is not configured.');
        }

        return sprintf(
            'https://%s.s3.%s.amazonaws.com/%s',
            $bucket,
            $region,
            ltrim($path, '/'),
        );
    }

    public static function publicS3Url(string $path): string
    {
        return self::publicUrl('s3', ltrim($path, '/'));
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
