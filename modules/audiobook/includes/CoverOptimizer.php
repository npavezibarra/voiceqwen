<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

class CoverOptimizer
{
    /**
     * Optimize an uploaded cover image and return a JPEG path <= $max_bytes.
     *
     * @return array{path:string,mime:string,error?:string}
     */
    public static function optimize_to_jpeg(string $src_path, int $max_bytes = 600000, int $max_dim = 1200): array
    {
        if (!file_exists($src_path)) {
            return array('path' => '', 'mime' => '', 'error' => 'Missing file');
        }

        if (!function_exists('wp_get_image_editor')) {
            return array('path' => '', 'mime' => '', 'error' => 'Image editor not available');
        }

        $editor = wp_get_image_editor($src_path);
        if (is_wp_error($editor)) {
            return array('path' => '', 'mime' => '', 'error' => $editor->get_error_message());
        }

        $size = $editor->get_size();
        $w = isset($size['width']) ? intval($size['width']) : 0;
        $h = isset($size['height']) ? intval($size['height']) : 0;
        if ($w <= 0 || $h <= 0) {
            return array('path' => '', 'mime' => '', 'error' => 'Invalid image');
        }

        // Resize down to a reasonable maximum dimension.
        if (max($w, $h) > $max_dim) {
            $editor->resize($max_dim, $max_dim, false);
        }

        $upload_dir = wp_upload_dir();
        $tmp_dir = rtrim($upload_dir['basedir'], '/') . '/voiceqwen-tmp';
        if (!file_exists($tmp_dir)) {
            @mkdir($tmp_dir, 0755, true);
        }
        $tmp_path = $tmp_dir . '/cover_' . time() . '_' . wp_generate_password(6, false, false) . '.jpg';

        $quality_steps = array(82, 75, 68, 60, 52, 45, 40);
        $passes = 0;

        while ($passes < 4) {
            foreach ($quality_steps as $q) {
                $editor->set_quality($q);
                $saved = $editor->save($tmp_path, 'image/jpeg');
                if (is_wp_error($saved)) {
                    return array('path' => '', 'mime' => '', 'error' => $saved->get_error_message());
                }
                clearstatcache(true, $tmp_path);
                $bytes = @filesize($tmp_path);
                if ($bytes !== false && $bytes > 0 && $bytes <= $max_bytes) {
                    return array('path' => $tmp_path, 'mime' => 'image/jpeg');
                }
            }

            // Still too large: reduce dimensions and try again.
            $size = $editor->get_size();
            $w = isset($size['width']) ? intval($size['width']) : 0;
            $h = isset($size['height']) ? intval($size['height']) : 0;
            if ($w <= 0 || $h <= 0) break;

            $nw = max(320, (int) floor($w * 0.85));
            $nh = max(320, (int) floor($h * 0.85));
            $editor->resize($nw, $nh, false);
            $passes++;
        }

        // Best-effort return (might be > max_bytes, but still optimized).
        if (file_exists($tmp_path)) {
            return array('path' => $tmp_path, 'mime' => 'image/jpeg');
        }
        return array('path' => '', 'mime' => '', 'error' => 'Failed to optimize image');
    }
}

