<?php
/* Modified for Joomla 5/6 on 2026-09-24: local viewer, gallery path validation, and output escaping. */
/** Copyright (c) 2006-2022 JoomlaWorks Ltd. GPL-2.0. */
defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

class SimpleImageGalleryHelper
{
    public function renderGallery(string $directory, string $rootName, string $folder, int $width, int $height, int $quality, int $ttl): array
    {
        if (!extension_loaded('gd')) {
            return [];
        }
        $cache = JPATH_SITE . '/cache/jw_sig';
        if (!is_dir($cache) && !@mkdir($cache, 0755, true) && !is_dir($cache)) {
            return [];
        }
        // Prevent PHP execution in a directly accessible thumbnail directory.
        if (!is_file($cache . '/.htaccess')) {
            @file_put_contents($cache . '/.htaccess', "<FilesMatch \"\\.(?:php[0-9s]?|phtml|phar)\$\">\nRequire all denied\n</FilesMatch>\n");
        }
        $gallery = [];
        $files = @scandir($directory);
        if ($files === false) {
            return [];
        }
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        $base = rtrim(Uri::root(true), '/');
        foreach ($files as $filename) {
            if (!preg_match('/\.(?:jpe?g|png|gif|webp)\z/i', $filename)) {
                continue;
            }
            $file = $directory . '/' . $filename;
            if (!is_file($file) || is_link($file) || !is_readable($file)) {
                continue;
            }
            $dimensions = @getimagesize($file);
            if (!$dimensions || !in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
                continue;
            }
            $key = hash('sha256', $file . ':' . $width . ':' . $height . ':' . (string) filemtime($file));
            $thumbName = 'jw_sig_' . $key . '.jpg';
            $thumb = $cache . '/' . $thumbName;
            if (!is_file($thumb) || filemtime($thumb) + $ttl < time()) {
                if (!$this->makeThumbnail($file, $thumb, $dimensions, $width, $height, $quality)) {
                    continue;
                }
            }
            $segments = array_map('rawurlencode', explode('/', trim($rootName, '/')));
            $folderSegments = array_map('rawurlencode', explode('/', $folder));
            $gallery[] = (object) [
                'filename' => $filename,
                'sourceImageFilePath' => $base . '/' . implode('/', $segments) . '/' . implode('/', $folderSegments) . '/' . rawurlencode($filename),
                'thumbImageFilePath' => $base . '/cache/jw_sig/' . $thumbName,
                'width' => $width,
                'height' => $height,
            ];
        }
        return $gallery;
    }

    private function makeThumbnail(string $file, string $target, array $dimensions, int $boxWidth, int $boxHeight, int $quality): bool
    {
        [$width, $height, $type] = $dimensions;
        if ($width < 1 || $height < 1) {
            return false;
        }
        $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_GIF => 'imagecreatefromgif', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
        $loader = $loaders[$type];
        if (!function_exists($loader)) {
            return false;
        }
        $source = @$loader($file);
        if (!$source) {
            return false;
        }
        $ratio = min($boxWidth / $width, $boxHeight / $height, 1);
        $thumbWidth = max(1, (int) round($width * $ratio));
        $thumbHeight = max(1, (int) round($height * $ratio));
        $image = imagecreatetruecolor($thumbWidth, $thumbHeight);
        if (!$image) {
            imagedestroy($source);
            return false;
        }
        $ok = imagecopyresampled($image, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        $temporary = tempnam(dirname($target), 'sig_');
        $saved = $ok && $temporary !== false && imagejpeg($image, $temporary, $quality) && rename($temporary, $target);
        if (!$saved && $temporary !== false) {
            @unlink($temporary);
        }
        imagedestroy($source);
        imagedestroy($image);
        return $saved;
    }
}
