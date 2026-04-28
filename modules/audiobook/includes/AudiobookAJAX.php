<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

use VoiceQwen\Audiobook\AudiobookProcessor;
use VoiceQwen\Audiobook\AudiobookUI;
use VoiceQwen\Audiobook\AudiobookUtils;
use VoiceQwen\Audiobook\R2Client;

class AudiobookAJAX
{
    public static function vq_create_book(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $title = sanitize_text_field($_POST['title']);
        $author = sanitize_text_field($_POST['author']);

        $post_id = AudiobookProcessor::create_book($title, $author);
        if ($post_id) {
            wp_send_json_success(['id' => $post_id]);
        }
        wp_send_json_error('Failed to create book');
    }

    public static function vq_update_book_author(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $author = sanitize_text_field($_POST['author']);
        update_post_meta($post_id, '_vq_author', $author);
        wp_send_json_success(['author' => $author]);
    }

    public static function vq_save_playlist(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $playlist = $_POST['playlist']; 
        update_post_meta($post_id, '_vq_playlist', $playlist);
        wp_send_json_success();
    }

    public static function vq_upload_chapter(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $book_title = sanitize_file_name($_POST['book_title'] ?? 'book');
        if (empty($_FILES['file'])) wp_send_json_error('No file provided');

        $track = AudiobookProcessor::upload_chapter($post_id, $book_title, $_FILES['file']);
        if ($track) {
            wp_send_json_success($track);
        }
        wp_send_json_error('Upload failed');
    }

    public static function vq_upload_local_chapter(): void {
        self::vq_upload_chapter();
    }

    public static function vq_get_book_editor(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'audiobook') wp_send_json_error('Invalid book');

        ob_start();
        AudiobookUI::render_book_card($post);
        wp_send_json_success(ob_get_clean());
    }

    public static function vq_get_books(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $books = get_posts(['post_type' => 'audiobook', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $data = [];
        foreach ($books as $book) {
            $data[] = [
                'id' => $book->ID,
                'title' => $book->post_title,
                'author' => get_post_meta($book->ID, '_vq_author', true)
            ];
        }
        wp_send_json_success($data);
    }

    public static function vq_get_chapter_text(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $text_key = sanitize_text_field($_POST['text_key']);
        if (!$text_key) wp_send_json_error('No text key');

        $upload_dir = wp_upload_dir();
        $username = wp_get_current_user()->user_login;
        $paths = [
            $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $text_key
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                wp_send_json_success(file_get_contents($path));
            }
        }
        wp_send_json_error('Text not found');
    }

    public static function vq_save_chapter_text(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $text_key = sanitize_text_field($_POST['text_key']);
        $content = $_POST['content'];
        if (!$text_key) wp_send_json_error('No text key');

        $upload_dir = wp_upload_dir();
        $username = wp_get_current_user()->user_login;
        $path = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $text_key;

        wp_mkdir_p(dirname($path));
        if (file_put_contents($path, $content) !== false) {
            wp_send_json_success('Saved');
        }
        wp_send_json_error('Save failed');
    }

    public static function vq_upload_cover(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        if (empty($_FILES['file'])) wp_send_json_error('No file');
        $res = AudiobookProcessor::upload_media($post_id, $_FILES['file'], 'cover');
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success($res['url']);
    }

    public static function vq_upload_background(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        if (empty($_FILES['file'])) wp_send_json_error('No file');
        $res = AudiobookProcessor::upload_media($post_id, $_FILES['file'], 'background');
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success($res['url']);
    }

    public static function vq_sync_to_r2(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $key = sanitize_text_field($_POST['key']);
        $res = AudiobookProcessor::sync_to_r2($post_id, $key);
        if (isset($res['error'])) wp_send_json_error($res['error']);
        wp_send_json_success($res);
    }

    public static function vq_download_from_r2(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $key = sanitize_text_field($_POST['key']);
        if (AudiobookProcessor::download_from_r2($post_id, $key)) {
            wp_send_json_success('Downloaded');
        }
        wp_send_json_error('Download failed');
    }

    public static function vq_get_track_url(): void {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'voiceqwen_nonce')) {
            wp_send_json_error('Invalid token');
        }
        $key = sanitize_text_field($_POST['key']);
        $post_id = intval($_POST['post_id']);
        $r2 = new R2Client();
        
        // Try local first if post_id is provided
        if ($post_id) {
            $folder = get_post_meta($post_id, '_vq_folder_name', true);
            $username = wp_get_current_user()->user_login ?: 'nicolas';
            $upload_dir = wp_upload_dir();
            $clean_key = (strpos($key, $username . '/') === 0) ? substr($key, strlen($username) + 1) : $key;
            $raw_filename = ($folder && strpos($clean_key, $folder . '/') === 0) ? substr($clean_key, strlen($folder) + 1) : $clean_key;
            
            $rel = $folder ? $folder . '/' . $raw_filename : $raw_filename;
            $abs = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $rel;
            
            if (file_exists($abs)) {
                wp_send_json_success($upload_dir['baseurl'] . '/voiceqwen/' . $username . '/' . $rel . '?v=' . filemtime($abs));
            }
        }

        $url = $r2->get_presigned_url($key, '+1 hour');
        if ($url) wp_send_json_success($url);
        wp_send_json_error('Failed to get URL');
    }

    public static function vq_export_audiobook(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        if (!$post) wp_send_json_error('Not found');

        $cover_base64 = '';
        $cover_key = get_post_meta($post_id, '_vq_cover_key', true);
        if ($cover_key) {
            $path = wp_upload_dir()['basedir'] . '/voiceqwen-audiobook/' . $cover_key;
            if (file_exists($path)) {
                $cover_base64 = 'data:image/' . pathinfo($path, PATHINFO_EXTENSION) . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        wp_send_json_success([
            'version' => '1.0',
            'title' => $post->post_title,
            'author' => get_post_meta($post_id, '_vq_author', true),
            'folder_name' => get_post_meta($post_id, '_vq_folder_name', true),
            'playlist' => get_post_meta($post_id, '_vq_playlist', true),
            'cover_base64' => $cover_base64
        ]);
    }

    public static function vq_upload_text_chapters(): void {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        if (empty($_FILES['files'])) wp_send_json_error('No files');

        $folder = get_post_meta($post_id, '_vq_folder_name', true);
        $username = wp_get_current_user()->user_login;
        $book_dir = wp_upload_dir()['basedir'] . '/voiceqwen/' . $username . '/' . $folder;
        wp_mkdir_p($book_dir);

        $playlist = get_post_meta($post_id, '_vq_playlist', true) ?: [];
        $new_tracks = [];
        $files = $_FILES['files'];

        for ($i = 0; $i < count($files['name']); $i++) {
            if (strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION)) !== 'txt') continue;
            
            $raw_filename = pathinfo($files['name'][$i], PATHINFO_FILENAME);
            $filename = sanitize_title($raw_filename) . '.txt';
            
            if (move_uploaded_file($files['tmp_name'][$i], $book_dir . '/' . $filename)) {
                $text_key = $folder . '/' . $filename;
                $matched = false;
                
                // Try to find an existing track to link
                $prefix = '';
                if (preg_match('/^(\d+)/', $raw_filename, $m)) {
                    $prefix = str_pad($m[1], 2, '0', STR_PAD_LEFT);
                }

                foreach ($playlist as &$track) {
                    // Match by prefix (e.g., 02 matches 02-something) or title
                    $track_prefix = '';
                    if (preg_match('/^(\d+)/', $track['title'], $tm)) {
                        $track_prefix = str_pad($tm[1], 2, '0', STR_PAD_LEFT);
                    }

                    if (($prefix && $prefix === $track_prefix) || sanitize_title($track['title']) === sanitize_title($raw_filename)) {
                        $track['text_key'] = $text_key;
                        $matched = true;
                        $new_tracks[] = $track; // Send back for UI update
                        break;
                    }
                }

                if (!$matched) {
                    $new_track = [
                        'id' => uniqid(),
                        'title' => ucwords(str_replace(['-', '_'], ' ', $raw_filename)),
                        'text_key' => $text_key,
                        'storage' => 'text',
                        'duration' => '00:00'
                    ];
                    $playlist[] = $new_track;
                    $new_tracks[] = $new_track;
                }
            }
        }
        update_post_meta($post_id, '_vq_playlist', $playlist);
        wp_send_json_success($new_tracks);
    }
}
