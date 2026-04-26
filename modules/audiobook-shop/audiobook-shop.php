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

            // Check for linked WooCommerce product
            $is_purchased = true;
            $product_id = 0;
            $buy_id = 0;
            $price_html = '';
            
            if (class_exists('WooCommerce')) {
                // Find product or variation linked to this audiobook
                $linked_args = [
                    'post_type' => ['product', 'product_variation'],
                    'posts_per_page' => 1,
                    'meta_query' => [
                        [
                            'key' => '_vq_linked_audiobook',
                            'value' => $post->ID
                        ]
                    ]
                ];
                $linked = get_posts($linked_args);
                
                if ($linked) {
                    $item = $linked[0];
                    $buy_id = $item->ID; // The specific ID to add to cart (could be variation ID)
                    
                    if (is_user_logged_in()) {
                        $current_user = wp_get_current_user();
                        // Check if bought
                        $is_purchased = wc_customer_bought_product($current_user->user_email, $current_user->ID, $buy_id);
                    } else {
                        $is_purchased = false;
                    }
                    
                    $wc_product = wc_get_product($buy_id);
                    if ($wc_product) {
                        $price_html = $wc_product->get_price_html();
                    }
                }
            }

            // Prepare chapters
            $updated_playlist = false;
            $chapters = [];
            foreach ($playlist as $index => $track) {
                $track_id = $track['id'] ?? ($index + 1);
                $duration = $track['duration'] ?? '00:00';
                $storage = $track['storage'] ?? 'r2';

                // Healing: if duration is 00:00 and it's local, try to get it
                if ($duration === '00:00' && $storage === 'local' && class_exists('\VoiceQwen\Audiobook\AudiobookManager')) {
                    // Try to resolve the local path
                    $current_user = wp_get_current_user();
                    $username = $current_user->user_login;
                    $upload_dir = wp_upload_dir();
                    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
                    $folder_name = get_post_meta($post->ID, '_vq_folder_name', true);
                    
                    $clean_key = $track['key'];
                    if (strpos($clean_key, $username . '/') === 0) {
                        $clean_key = substr($clean_key, strlen($username) + 1);
                    }
                    
                    $local_path = $user_dir . '/' . $clean_key;
                    if (file_exists($local_path)) {
                        $duration = \VoiceQwen\Audiobook\AudiobookManager::get_wav_duration_formatted($local_path);
                        if ($duration !== '00:00') {
                            $playlist[$index]['duration'] = $duration;
                            $updated_playlist = true;
                        }
                    }
                }

                $chapters[] = [
                    'id' => $track_id,
                    'title' => $track['title'],
                    'key' => $track['key'],
                    'storage' => $storage,
                    'duration' => $duration,
                    'progress' => $progress[$index] ?? ['time' => 0, 'finished' => false]
                ];
            }

            if ($updated_playlist) {
                update_post_meta($post->ID, '_vq_playlist', $playlist);
            }

            $data[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'author' => $author ?: 'Unknown Author',
                'cover' => $cover_url ?: 'https://images.unsplash.com/photo-1543004218-ee14110497f9?auto=format&fit=crop&q=80&w=600',
                'background' => $background_url,
                'chapters' => $chapters,
                'is_purchased' => $is_purchased,
                'product_id' => $buy_id,
                'price_html' => $price_html,
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
