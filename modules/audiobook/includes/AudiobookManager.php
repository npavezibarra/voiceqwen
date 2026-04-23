<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the Audiobook Management interface.
 */
class AudiobookManager
{
    /**
     * Initialize the manager.
     */
    public static function init(): void
    {
        // AJAX handlers
        $actions = array(
            'vq_create_book',
            'vq_save_playlist',
            'vq_upload_chapter',
            'vq_get_book_editor',
            'vq_upload_cover',
            'vq_get_track_url',
            'vq_get_books',
            'vq_upload_local_chapter',
            'vq_sync_to_r2'
        );

        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, array(self::class, 'ajax_' . str_replace('vq_', '', $action)));
        }
    }

    /**
     * Get cover URL for a post.
     */
    public static function get_cover_url($post_id): string
    {
        $key = get_post_meta($post_id, '_vq_cover_key', true);
        if (!$key) {
            return '';
        }

        $mode = get_option('voiceqwen_storage_mode', 'local');
        if ($mode === 'r2') {
            $r2 = new R2Client();
            return $r2->get_presigned_url($key, '+1 hour') ?: '';
        }

        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/voiceqwen-audiobook/' . $key;
    }

    /**
     * Render the Manager UI.
     */
    public static function render_ui(): void
    {
        $template = plugin_dir_path(__FILE__) . '../audiobook-ui.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Render a sidebar list item.
     */
    private static function render_book_item($post): void
    {
        $author = get_post_meta($post->ID, '_vq_author', true);
        $cover_url = self::get_cover_url($post->ID);
        ?>
        <div class="vq-book-item" data-id="<?php echo $post->ID; ?>">
            <div class="vq-book-item-thumb">
                <?php if ($cover_url): ?>
                    <img src="<?php echo esc_url($cover_url); ?>" alt="">
                <?php else: ?>
                    <div class="vq-thumb-placeholder"></div>
                <?php endif; ?>
            </div>
            <div class="vq-book-item-content">
                <span class="vq-book-item-title"><?php echo esc_html($post->post_title); ?></span>
                <span class="vq-book-item-author"><?php echo esc_html($author); ?></span>
            </div>
            <div class="vq-book-item-arrow">›</div>
        </div>
        <?php
    }

    /**
     * Render a single book card (Editor).
     */
    public static function render_book_card($post): void
    {
        $author = get_post_meta($post->ID, '_vq_author', true);
        $playlist = get_post_meta($post->ID, '_vq_playlist', true);
        $playlist = is_array($playlist) ? $playlist : (json_decode($playlist, true) ?: []);
        $folder_name = get_post_meta($post->ID, '_vq_folder_name', true);

        ?>
        <div class="vq-card" data-id="<?php echo $post->ID; ?>" data-title="<?php echo esc_attr($post->post_title); ?>" data-folder="<?php echo esc_attr($folder_name); ?>">
            <div class="vq-card-header">
                <?php $cover_url = self::get_cover_url($post->ID); ?>
                <div class="vq-card-header-cover">
                    <?php if ($cover_url): ?>
                        <img src="<?php echo esc_url($cover_url); ?>" alt="" class="vq-mini-cover">
                    <?php else: ?>
                        <div class="vq-mini-placeholder"></div>
                    <?php endif; ?>
                </div>

                <div class="vq-card-info">
                    <h3><?php echo esc_html($post->post_title); ?></h3>
                    <div class="vq-card-meta">
                        <span class="vq-author"><?php echo esc_html($author); ?></span>
                    </div>
                </div>
                <div class="vq-card-actions">
                    <button class="nav-btn vq-upload-cover-btn" data-id="<?php echo $post->ID; ?>">
                        Cover
                    </button>
                    <input type="file" class="vq-cover-uploader" style="display:none;" accept="image/jpeg,image/png">
                    
                    <div class="vq-dropdown-wrap">
                        <button class="nav-btn vq-chapter-dropdown-btn">
                            + CAPÍTULO
                        </button>
                        <div class="vq-dropdown-menu">
                            <div class="vq-dropdown-item" data-action="upload-wav">Upload WAV</div>
                            <div class="vq-dropdown-item" data-action="select-r2">Select R2 Audio</div>
                            <div class="vq-dropdown-item" data-action="create-audio">Create Audio</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="vq-upload-progress-container" style="display:none;">
                <div class="vq-progress-bar" style="width: 0%;"></div>
                <small class="vq-upload-status"></small>
            </div>

            <div class="vq-chapters-container">
                <ul class="vq-chapters-list sortable" data-id="<?php echo $post->ID; ?>">
                    <?php if (empty($playlist)): ?>
                        <li class="vq-no-chapters">No chapters yet.</li>
                    <?php else: ?>
                        <?php foreach ($playlist as $index => $track): 
                            $ext = strtolower(pathinfo($track['key'], PATHINFO_EXTENSION));
                        ?>
                            <li class="vq-chapter-item" data-key="<?php echo esc_attr($track['key']); ?>">
                                <span class="vq-drag-handle">≡</span>
                                <input type="text" class="vq-chapter-title" value="<?php echo esc_attr($track['title']); ?>" placeholder="Chapter Title">
                                <div class="vq-chapter-actions">
                                    <?php 
                                    $storage = isset($track['storage']) ? $track['storage'] : 'r2';
                                    if ($storage === 'local'): ?>
                                        <span class="vq-badge vq-badge-local">LOCAL</span>
                                        <button class="vq-chapter-edit" title="Edit Audio" data-key="<?php echo esc_attr($track['key']); ?>">EDIT</button>
                                        <button class="vq-sync-btn" title="Sync to Cloudflare R2" data-key="<?php echo esc_attr($track['key']); ?>">↑</button>
                                    <?php else: ?>
                                        <span class="vq-badge vq-badge-r2">R2</span>
                                    <?php endif; ?>
                                    <button class="vq-inline-play" data-key="<?php echo esc_attr($track['key']); ?>" data-storage="<?php echo $storage; ?>">▶</button>
                                    <button class="vq-remove-track" title="Remove">×</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="vq-inline-player" style="display:none;">
                <div class="vq-wavesurfer-preview"></div>
                <div class="vq-preview-controls">
                     <span class="vq-preview-time">00:00 / 00:00</span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX Handlers
     */

    public static function ajax_create_book(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $title = sanitize_text_field($_POST['title']);
        $author = sanitize_text_field($_POST['author']);

        $post_id = wp_insert_post(array(
            'post_title' => $title,
            'post_type' => 'audiobook',
            'post_status' => 'publish'
        ));

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, '_vq_author', $author);

            // Create Local Folder
            $current_user = wp_get_current_user();
            $username = $current_user->user_login;
            $upload_dir = wp_upload_dir();
            
            $slug_title = sanitize_title($title);
            $slug_author = sanitize_title($author);
            $folder_name = $slug_title . '-' . $slug_author;
            
            $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
            $book_dir = $user_dir . '/' . $folder_name;
            
            if (!file_exists($book_dir)) {
                mkdir($book_dir, 0755, true);
            }
            
            update_post_meta($post_id, '_vq_folder_name', $folder_name);
            
            wp_send_json_success(array('id' => $post_id));
        }
        wp_send_json_error('Failed to create book');
    }

    public static function ajax_save_playlist(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $playlist = $_POST['playlist']; 
        update_post_meta($post_id, '_vq_playlist', $playlist);
        wp_send_json_success();
    }

    public static function ajax_upload_chapter(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $book_title = sanitize_file_name($_POST['book_title']);
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file provided');
        }

        $file = $_FILES['file'];
        $filename = sanitize_file_name($file['name']);
        $r2_key = $book_title . '/' . $filename;

        $r2 = new R2Client();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime_type = ($ext === 'mp3') ? 'audio/mpeg' : 'audio/wav';

        if ($r2->upload_object($file['tmp_name'], $r2_key, $mime_type)) {
            $playlist = get_post_meta($post_id, '_vq_playlist', true);
            $playlist = is_array($playlist) ? $playlist : (json_decode($playlist, true) ?: []);
            
            $new_track = array(
                'id' => uniqid(),
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'key' => $r2_key
            );
            
            $playlist[] = $new_track;
            update_post_meta($post_id, '_vq_playlist', $playlist);
            wp_send_json_success($new_track);
        }
        wp_send_json_error('R2 upload failed');
    }

    public static function ajax_upload_cover(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file provided');
        }

        $file = $_FILES['file'];
        $filename = sanitize_file_name($file['name']);
        $mode = get_option('voiceqwen_storage_mode', 'local');

        if ($mode === 'r2') {
            $r2_key = 'covers/' . $post_id . '_' . $filename;
            $r2 = new R2Client();
            if ($r2->upload_object($file['tmp_name'], $r2_key, $file['type'])) {
                update_post_meta($post_id, '_vq_cover_key', $r2_key);
                wp_send_json_success(self::get_cover_url($post_id));
            }
        } else {
            // Local fallback logic can be added here if needed
        }
        wp_send_json_error('Upload failed');
    }

    public static function ajax_get_book_editor(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'audiobook') {
            wp_send_json_error('Invalid book ID');
        }

        ob_start();
        self::render_book_card($post);
        $html = ob_get_clean();
        wp_send_json_success($html);
    }

    public static function ajax_get_books(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error();

        $books = get_posts(array(
            'post_type' => 'audiobook',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));

        $data = array();
        foreach ($books as $book) {
            $data[] = array(
                'id' => $book->ID,
                'title' => $book->post_title,
                'author' => get_post_meta($book->ID, '_vq_author', true)
            );
        }
        wp_send_json_success($data);
    }

    public static function ajax_get_track_url(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'voiceqwen_nonce')) {
            wp_send_json_error('Invalid security token. Please refresh the page.');
        }

        $key = sanitize_text_field($_POST['key']);
        $storage = isset($_POST['storage']) ? sanitize_text_field($_POST['storage']) : 'r2';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!$key) {
            wp_send_json_error('No track key provided');
        }

        if ($storage === 'local' && $post_id) {
            $current_user = wp_get_current_user();
            $username = $current_user->user_login;
            $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
            if ($folder_name) {
                $upload_dir = wp_upload_dir();
                $abs = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/' . $key;
                if (!file_exists($abs) && !str_ends_with(strtolower($key), '.wav')) {
                    $abs = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/' . $key . '.wav';
                    $key = $key . '.wav';
                }
                $v = @filemtime($abs);
                if (!$v) $v = time();
                $url = $upload_dir['baseurl'] . '/voiceqwen/' . $username . '/' . $folder_name . '/' . $key . '?v=' . $v;
                wp_send_json_success($url);
            }
        }

        // R2 Mode (Default)
        $r2 = new R2Client();
        $url = $r2->get_presigned_url($key, '+1 hour');
        if ($url) {
            wp_send_json_success($url);
        }
        
        wp_send_json_error('Failed to generate URL');
    }
    public static function ajax_upload_local_chapter(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
        }

        $post_id = intval($_POST['post_id']);
        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
        
        if (!$folder_name) {
            wp_send_json_error('Book folder not found');
        }

        $current_user = wp_get_current_user();
        $username = $current_user->user_login;
        $upload_dir = wp_upload_dir();
        
        $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
        $book_dir = $user_dir . '/' . $folder_name;

        if (!file_exists($book_dir)) {
            mkdir($book_dir, 0755, true);
        }

        $file = $_FILES['file'];
        $filename = sanitize_file_name($file['name']);
        $target_path = $book_dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            wp_send_json_success(array(
                'key' => $filename,
                'title' => pathinfo($filename, PATHINFO_FILENAME)
            ));
        }

        wp_send_json_error('Failed to save file locally');
    }

    public static function ajax_sync_to_r2(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $key = sanitize_text_field($_POST['key']); // The filename
        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);

        if (!$folder_name) {
            wp_send_json_error('Book folder not found');
        }

        $current_user = wp_get_current_user();
        $username = $current_user->user_login;
        $upload_dir = wp_upload_dir();
        
        $file_path = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/' . $key;

        if (!file_exists($file_path)) {
            wp_send_json_error('Local file not found: ' . $key);
        }

        // Upload to R2
        $r2 = new R2Client();
        // Destination in R2: {username}/{folder_name}/{key}
        $r2_key = $username . '/' . $folder_name . '/' . $key;
        
        $url = $r2->upload_file($file_path, $r2_key);

        if ($url) {
            wp_send_json_success(array('new_key' => $r2_key));
        }

        wp_send_json_error('R2 Upload failed');
    }
}
