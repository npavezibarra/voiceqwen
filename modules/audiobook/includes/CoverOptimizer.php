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
    public static function optimize_to_jpeg(string $src_path, int $max_bytes = 614400, int $max_dim = 1000): array
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

        // 1. Crop to square (Center)
        $dim = min($w, $h);
        $x = ($w - $dim) / 2;
        $y = ($h - $dim) / 2;
        $editor->crop($x, $y, $dim, $dim);

        // 2. Resize if too large
        if ($dim > $max_dim) {
            $editor->resize($max_dim, $max_dim, false);
        }

        $upload_dir = wp_upload_dir();
        $tmp_dir = rtrim($upload_dir['basedir'], '/') . '/voiceqwen-tmp';
        if (!file_exists($tmp_dir)) {
            if (!wp_mkdir_p($tmp_dir)) {
                return array('path' => '', 'mime' => '', 'error' => 'Could not create temporary directory: ' . $tmp_dir);
            }
        }
        $tmp_path = $tmp_dir . '/cover_' . time() . '_' . wp_generate_password(6, false, false) . '.jpg';

        $quality_steps = array(90, 82, 75, 68, 60, 52, 45, 35);
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
            $curr_w = isset($size['width']) ? intval($size['width']) : 0;
            if ($curr_w <= 320) break;

            $new_dim = max(320, (int) floor($curr_w * 0.8));
            $editor->resize($new_dim, $new_dim, false);
            $passes++;
        }

        // Best-effort return
        if (file_exists($tmp_path)) {
            return array('path' => $tmp_path, 'mime' => 'image/jpeg');
        }
        return array('path' => '', 'mime' => '', 'error' => 'Failed to optimize image');
    }

    /**
     * Optimize an uploaded background image (1440x900).
     */
    public static function optimize_background(string $src_path, int $max_bytes = 614400): array
    {
        if (!file_exists($src_path)) {
            return array('path' => '', 'mime' => '', 'error' => 'Missing file');
        }

        $editor = wp_get_image_editor($src_path);
        if (is_wp_error($editor)) {
            return array('path' => '', 'mime' => '', 'error' => $editor->get_error_message());
        }

        $size = $editor->get_size();
        $w = $size['width'];
        $h = $size['height'];

        // Target aspect ratio 1440:900 = 1.6
        $target_ar = 1440 / 900;
        $curr_ar = $w / $h;

        if ($curr_ar > $target_ar) {
            // Wider than target: crop width
            $crop_w = (int)($h * $target_ar);
            $crop_x = (int)(($w - $crop_w) / 2);
            $editor->crop($crop_x, 0, $crop_w, $h);
        } else {
            // Taller than target: crop height
            $crop_h = (int)($w / $target_ar);
            $crop_y = (int)(($h - $crop_h) / 2);
            $editor->crop(0, $crop_y, $w, $crop_h);
        }

        // Resize to 1440x900
        $editor->resize(1440, 900, false);

        $upload_dir = wp_upload_dir();
        $tmp_dir = rtrim($upload_dir['basedir'], '/') . '/voiceqwen-tmp';
        if (!file_exists($tmp_dir)) wp_mkdir_p($tmp_dir);
        $tmp_path = $tmp_dir . '/bg_' . time() . '_' . wp_generate_password(6, false, false) . '.jpg';

        $quality_steps = array(85, 75, 65, 55, 45, 35);
        foreach ($quality_steps as $q) {
            $editor->set_quality($q);
            $editor->save($tmp_path, 'image/jpeg');
            if (filesize($tmp_path) <= $max_bytes) {
                return array('path' => $tmp_path, 'mime' => 'image/jpeg');
            }
        }

        return array('path' => $tmp_path, 'mime' => 'image/jpeg'); // Return best effort
    }
}

