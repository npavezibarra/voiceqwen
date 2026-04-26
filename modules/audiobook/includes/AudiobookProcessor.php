<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

use VoiceQwen\Audiobook\R2Client;
use VoiceQwen\Audiobook\AudiobookUtils;
use VoiceQwen\Audiobook\CoverOptimizer;

class AudiobookProcessor
{
    /**
     * Create a new audiobook and its local folder.
     */
    public static function create_book($title, $author)
    {
        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type' => 'audiobook',
            'post_status' => 'publish'
        ));

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_vq_author', $author);

            $current_user = wp_get_current_user();
            $username = $current_user->user_login;
            $upload_dir = wp_upload_dir();
            
            $slug_title = sanitize_title($title);
            $slug_author = sanitize_title($author);
            $folder_name = $slug_title . '-' . $slug_author;
            
            $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
            $book_dir = $user_dir . '/' . $folder_name;
            
            if (!file_exists($book_dir)) {
                wp_mkdir_p($book_dir);
            }
            
            update_post_meta($post_id, '_vq_folder_name', $folder_name);
            return $post_id;
        }
        return false;
    }

    /**
     * Upload a chapter to R2 and update playlist.
     */
    public static function upload_chapter($post_id, $book_title, $file)
    {
        $folder_name = get_post_meta($post_id, '_vq_folder_name', true) ?: $book_title;
        $raw_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $clean_name = sanitize_title($raw_name);
        $filename = $clean_name . '.' . $ext;
        $r2_key = $folder_name . '/' . $filename;

        $r2 = new R2Client();
        $ext_lower = strtolower($ext);
        $mime_type = ($ext_lower === 'mp3') ? 'audio/mpeg' : 'audio/wav';

        if ($r2->upload_object($file['tmp_name'], $r2_key, $mime_type)) {
            $playlist = get_post_meta($post_id, '_vq_playlist', true);
            $playlist = is_array($playlist) ? $playlist : (json_decode($playlist, true) ?: []);
            
            $beautiful_title = ucwords(str_replace(['-', '_'], ' ', $clean_name));

            $new_track = array(
                'id' => uniqid(),
                'title' => $beautiful_title,
                'key' => $r2_key,
                'duration' => AudiobookUtils::get_wav_duration_formatted($file['tmp_name']),
                'r2_size' => filesize($file['tmp_name'])
            );
            
            $playlist[] = $new_track;
            update_post_meta($post_id, '_vq_playlist', $playlist);
            return $new_track;
        }
        return false;
    }

    /**
     * Sync a track from R2 to local storage.
     */
    public static function download_from_r2($post_id, $key)
    {
        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
        $current_user = wp_get_current_user();
        $username = $current_user->user_login;
        $upload_dir = wp_upload_dir();
        
        $clean_key = $key;
        if (strpos($key, $username . '/') === 0) {
            $clean_key = substr($key, strlen($username) + 1);
        } elseif ($username !== 'nicolas' && strpos($key, 'nicolas/') === 0) {
            $clean_key = substr($key, 8);
        }
        
        $raw_filename = $clean_key;
        if ($folder_name && strpos($clean_key, $folder_name . '/') === 0) {
            $raw_filename = substr($clean_key, strlen($folder_name) + 1);
        }

        $local_path = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . ($folder_name ? $folder_name . '/' : '') . $raw_filename;

        $r2 = new R2Client();
        $contents = $r2->get_object($key);

        if ($contents === false) return false;

        wp_mkdir_p(dirname($local_path));
        if (file_put_contents($local_path, $contents)) {
            $playlist = get_post_meta($post_id, '_vq_playlist', true);
            foreach ($playlist as &$track) {
                if ($track['key'] === $key) {
                    $track['r2_size'] = filesize($local_path);
                    break;
                }
            }
            update_post_meta($post_id, '_vq_playlist', $playlist);
            return true;
        }
        return false;
    }

    /**
     * Upload cover or background.
     */
    public static function upload_media($post_id, $file, $type = 'cover')
    {
        $limit = 614400; // 600KB
        if ($type === 'cover') {
            $optimized = CoverOptimizer::optimize_to_jpeg($file['tmp_name'], $limit, 1000);
        } else {
            $optimized = CoverOptimizer::optimize_background($file['tmp_name'], $limit);
        }

        if (!empty($optimized['error'])) return ['error' => $optimized['error']];

        $stamp = time();
        $folder = ($type === 'cover') ? 'covers' : 'backgrounds';
        $key = $folder . '/' . $post_id . '_' . $stamp . '.jpg';

        $upload_dir = wp_upload_dir();
        $abs = $upload_dir['basedir'] . '/voiceqwen-audiobook/' . $key;
        wp_mkdir_p(dirname($abs));

        if (@copy($optimized['path'], $abs)) {
            $meta_key = ($type === 'cover') ? '_vq_cover_key' : '_vq_background_key';
            update_post_meta($post_id, $meta_key, $key);
            @unlink($optimized['path']);
            return ['key' => $key, 'url' => ($type === 'cover' ? AudiobookUtils::get_cover_url($post_id) : AudiobookUtils::get_background_url($post_id))];
        }
        return ['error' => 'Failed to copy file'];
    }

    /**
     * Sync local file to R2.
     */
    public static function sync_to_r2($post_id, $key)
    {
        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
        $username = wp_get_current_user()->user_login;
        $upload_dir = wp_upload_dir();
        
        $base_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/';

        $clean_key = $key;
        if (strpos($key, $username . '/') === 0) $clean_key = substr($key, strlen($username) + 1);
        elseif (strpos($key, 'nicolas/') === 0) $clean_key = substr($key, 8);

        $file_path = $base_dir . $clean_key;

        if (!file_exists($file_path)) {
            $paths = [
                $base_dir . $folder_name . '/' . $clean_key,
                $upload_dir['basedir'] . '/voiceqwen/nicolas/' . $folder_name . '/' . $clean_key
            ];
            foreach ($paths as $p) {
                if (file_exists($p)) { $file_path = $p; break; }
            }
        }

        if (!file_exists($file_path)) return ['error' => 'File not found locally'];

        $r2 = new R2Client();
        $r2_key = (strpos($clean_key, $folder_name . '/') === 0) ? $clean_key : $folder_name . '/' . $clean_key;

        $result = $r2->upload_object($file_path, $r2_key, 'audio/wav');
        if ($result === true) {
            $playlist = get_post_meta($post_id, '_vq_playlist', true);
            foreach ($playlist as &$track) {
                if ($track['key'] === $key) {
                    $track['storage'] = 'r2';
                    $track['key'] = $r2_key;
                    $track['r2_size'] = filesize($file_path);
                    break;
                }
            }
            update_post_meta($post_id, '_vq_playlist', $playlist);
            return ['new_key' => $r2_key];
        }
        return ['error' => $result];
    }
}
