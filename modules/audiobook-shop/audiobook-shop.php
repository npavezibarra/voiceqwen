<?php
/**
 * Audiobook Shop Module Entry Point
 */

namespace VoiceQwen\AudiobookShop;

if (!defined('ABSPATH')) {
    exit;
}

class AudiobookShop {
    public static function init() {
        add_action('wp_ajax_vq_shop_get_books', [self::class, 'ajax_get_books']);
        add_action('wp_ajax_nopriv_vq_shop_get_books', [self::class, 'ajax_get_books']);
        add_action('wp_ajax_vq_shop_save_progress', [self::class, 'ajax_save_progress']);
    }

    public static function ajax_get_books() {
        $books_posts = get_posts([
            'post_type' => 'audiobook',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $data = [];
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        // Find Manager Page URL
        $manager_page_query = new \WP_Query(array(
            'post_type' => 'page',
            'meta_key' => '_vq_page_type',
            'meta_value' => 'audiobook',
            'posts_per_page' => 1
        ));
        $manager_url = '';
        if ($manager_page_query->have_posts()) {
            $manager_url = get_permalink($manager_page_query->posts[0]->ID);
        }

        foreach ($books_posts as $post) {
            $author = get_post_meta($post->ID, '_vq_author', true);
            $playlist = get_post_meta($post->ID, '_vq_playlist', true);
            $playlist = is_array($playlist) ? $playlist : (json_decode($playlist, true) ?: []);
            
            // Get user progress for this book
            $progress = [];
            if ($user_id) {
                $progress = get_post_meta($post->ID, '_vq_user_progress_' . $user_id, true);
                $progress = is_array($progress) ? $progress : [];
            }

            // Get Cover & Background URLs using the existing AudiobookManager helpers
            $cover_url = '';
            $background_url = '';
            if (class_exists('\VoiceQwen\Audiobook\AudiobookManager')) {
                $cover_url = \VoiceQwen\Audiobook\AudiobookManager::get_cover_url($post->ID);
                $background_url = \VoiceQwen\Audiobook\AudiobookManager::get_background_url($post->ID);
            }

            // Prepare chapters
            $chapters = [];
            foreach ($playlist as $index => $track) {
                $track_id = $track['id'] ?? ($index + 1);
                $chapters[] = [
                    'id' => $track_id,
                    'title' => $track['title'],
                    'key' => $track['key'],
                    'storage' => $track['storage'] ?? 'r2',
                    'duration' => $track['duration'] ?? '00:00',
                    'progress' => $progress[$index] ?? ['time' => 0, 'finished' => false]
                ];
            }

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'author' => $author ?: 'Unknown Author',
                'cover' => $cover_url ?: 'https://images.unsplash.com/photo-1543004218-ee14110497f9?auto=format&fit=crop&q=80&w=600',
                'background' => $background_url,
                'chapters' => $chapters,
                'edit_url' => $is_admin ? add_query_arg('book_id', $post->ID, $manager_url) : null
            ];
        }

        wp_send_json_success([
            'books' => $data,
            'is_admin' => $is_admin
        ]);
    }

    /**
     * AJAX: Save playback progress for the current user.
     */
    public static function ajax_save_progress() {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Not logged in');
        }

        $book_id = intval($_POST['book_id']);
        $chapter_index = intval($_POST['chapter_index']);
        $time = floatval($_POST['time']);
        $finished = ($_POST['finished'] === 'true');

        if (!$book_id) {
            wp_send_json_error('Invalid book ID');
        }

        $progress = get_post_meta($book_id, '_vq_user_progress_' . $user_id, true);
        $progress = is_array($progress) ? $progress : [];

        $progress[$chapter_index] = [
            'time' => $time,
            'finished' => $finished,
            'updated' => time()
        ];

        update_post_meta($book_id, '_vq_user_progress_' . $user_id, $progress);
        wp_send_json_success();
    }
}

AudiobookShop::init();
