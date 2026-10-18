<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Convert an uploaded image (or local file) to WebP format and save to target directory.
     *
     * @param UploadedFile|string $file
     * @param string $destinationDir Full directory path
     * @param int $quality Quality from 1 to 100 (default: 82)
     * @param string $prefix Optional prefix for the generated filename
     * @return string Generated filename (e.g. '1729267123_abc123.webp')
     */
    public static function uploadAsWebp($file, string $destinationDir, int $quality = 82, string $prefix = ''): string
    {
        if (!file_exists($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $isUploadedFile = $file instanceof UploadedFile;
        $sourcePath = $isUploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower($isUploadedFile ? $file->getClientOriginalExtension() : pathinfo($sourcePath, PATHINFO_EXTENSION));

        // SVG files should remain as SVG (vector graphics)
        if ($extension === 'svg') {
            $filename = ($prefix ? $prefix . '_' : '') . time() . '_' . Str::random(6) . '.svg';
            if ($isUploadedFile) {
                $file->move($destinationDir, $filename);
            } else {
                copy($sourcePath, rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename);
            }
            return $filename;
        }

        $filename = ($prefix ? $prefix . '_' : '') . time() . '_' . Str::random(8) . '.webp';
        $targetPath = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        // Try reading and converting image using GD
        try {
            $content = file_get_contents($sourcePath);
            if ($content !== false) {
                $image = @imagecreatefromstring($content);

                if ($image !== false) {
                    // Convert palette images to truecolor
                    imagepalettetotruecolor($image);
                    // Preserve alpha transparency for PNG/WebP/GIF
                    imagealphablending($image, false);
                    imagesavealpha($image, true);

                    // Save as WebP
                    $success = imagewebp($image, $targetPath, $quality);
                    imagedestroy($image);

                    if ($success && file_exists($targetPath) && filesize($targetPath) > 0) {
                        return $filename;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ImageService webp conversion fallback: ' . $e->getMessage());
        }

        // Fallback: If GD conversion is not possible, save original
        $fallbackExt = $extension ?: 'jpg';
        $fallbackName = ($prefix ? $prefix . '_' : '') . time() . '_' . Str::random(8) . '.' . $fallbackExt;
        $fallbackTarget = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fallbackName;

        if ($isUploadedFile) {
            $file->move($destinationDir, $fallbackName);
        } else {
            copy($sourcePath, $fallbackTarget);
        }

        return $fallbackName;
    }

    /**
     * Delete an existing image file if it exists.
     *
     * @param string|null $fullPath
     * @return void
     */
    public static function deleteFile(?string $fullPath): void
    {
        if ($fullPath && file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
