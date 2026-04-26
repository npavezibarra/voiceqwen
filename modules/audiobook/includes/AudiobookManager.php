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
            'vq_update_book_author',
            'vq_save_playlist',
            'vq_upload_chapter',
            'vq_get_book_editor',
            'vq_upload_cover',
            'vq_upload_background',
            'vq_get_track_url',
            'vq_get_books',
            'vq_upload_local_chapter',
            'vq_sync_to_r2',
            'vq_export_audiobook',
            'vq_import_audiobook',
            'vq_download_from_r2'
        );

        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, array(self::class, 'ajax_' . str_replace('vq_', '', $action)));
            if ($action === 'vq_get_track_url') {
                add_action('wp_ajax_nopriv_' . $action, array(self::class, 'ajax_' . str_replace('vq_', '', $action)));
            }
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

        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/voiceqwen-audiobook/' . $key;
    }

    /**
     * Get background URL for a post.
     */
    public static function get_background_url($post_id): string
    {
        $key = get_post_meta($post_id, '_vq_background_key', true);
        if (!$key) {
            return '';
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

        // Auto-healing: Check if local tracks are already in R2
        $r2 = new R2Client();
        $current_user = wp_get_current_user();
        $username = $current_user->user_login;
        $updated_meta = false;

        foreach ($playlist as &$track) {
            $storage = isset($track['storage']) ? $track['storage'] : 'r2';
            if ($storage === 'local') {
                $key = $track['key'];
                $clean_key = $key;
                if (strpos($key, $username . '/') === 0) {
                    $clean_key = substr($key, strlen($username) + 1);
                }
                $r2_key = $clean_key;
                if ($folder_name && strpos($clean_key, $folder_name . '/') !== 0) {
                    $r2_key = $folder_name . '/' . $clean_key;
                }

                if ($r2->object_exists($r2_key)) {
                    $track['storage'] = 'r2';
                    $track['key'] = $r2_key;
                    $updated_meta = true;
                    error_log("VoiceQwen: Auto-healed track to R2 (Folder): " . $track['title']);
                } elseif ($r2->object_exists($clean_key)) {
                    // Try checking the root if not found in folder
                    $track['storage'] = 'r2';
                    $track['key'] = $clean_key;
                    $updated_meta = true;
                    error_log("VoiceQwen: Auto-healed track to R2 (Root): " . $track['title']);
                }
            }
        }
        if ($updated_meta) {
            update_post_meta($post->ID, '_vq_playlist', $playlist);
        }

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
                    <?php if (!empty($author)): ?>
                        <div class="vq-card-author"><?php echo esc_html($author); ?></div>
                    <?php else: ?>
                        <input
                            type="text"
                            class="vq-card-author-input"
                            placeholder="Author..."
                            value=""
                            autocomplete="off"
                            spellcheck="false"
                        >
                    <?php endif; ?>
                </div>
                <div class="vq-card-actions">
                    <button class="nav-btn vq-upload-cover-btn" data-id="<?php echo $post->ID; ?>">
                        Cover
                    </button>
                    <input type="file" class="vq-cover-uploader" style="display:none;" accept="image/*">

                    <button class="nav-btn vq-upload-background-btn" data-id="<?php echo $post->ID; ?>">
                        FONDO
                    </button>
                    <input type="file" class="vq-background-uploader" style="display:none;" accept="image/*">
                    
                    <button class="nav-btn vq-export-book-btn" data-id="<?php echo $post->ID; ?>" title="Download book data for import">
                        EXPORT
                    </button>
                    
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
                            $storage = isset($track['storage']) ? $track['storage'] : 'r2';
                            
                            // Check for local changes (Diff)
                            $is_changed = false;
                            $local_exists = false;
                            $local_path = '';

                            $clean_key = $track['key'];
                            if (strpos($clean_key, $username . '/') === 0) {
                                $clean_key = substr($clean_key, strlen($username) + 1);
                            }
                            // If it already contains folder_name/, strip it to get the raw filename
                            $raw_filename = $clean_key;
                            if ($folder_name && strpos($clean_key, $folder_name . '/') === 0) {
                                $raw_filename = substr($clean_key, strlen($folder_name) + 1);
                            }
                            
                            // Try multiple locations
                            $paths_to_check = [
                                $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/' . $raw_filename,
                                $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $raw_filename,
                                $upload_dir['basedir'] . '/voiceqwen/nicolas/' . $folder_name . '/' . $raw_filename,
                                $upload_dir['basedir'] . '/voiceqwen/nicolas/' . $raw_filename
                            ];

                            foreach ($paths_to_check as $path) {
                                if (file_exists($path)) {
                                    $local_path = $path;
                                    $local_exists = true;
                                    break;
                                }
                            }

                            // LAST RESORT: Manual path anchor for Local by Flywheel
                            if (!$local_exists) {
                                $filename = basename($clean_key);
                                $base_mac = '/Users/nicolas/Local Sites/voiceqwen/app/public/wp-content/uploads/voiceqwen/nicolas/';
                                
                                $possible_paths = [
                                    $base_mac . $folder_name . '/' . $filename,
                                    $base_mac . $filename
                                ];

                                foreach ($possible_paths as $p) {
                                    if (file_exists($p)) {
                                        $local_path = $p;
                                        $local_exists = true;
                                        break;
                                    }
                                }
                            }

                            if ($storage === 'r2' && $local_exists && isset($track['r2_size'])) {
                                if (filesize($local_path) !== (int)$track['r2_size']) {
                                    $is_changed = true;
                                }
                            }
                        ?>
                            <li class="vq-chapter-item" data-key="<?php echo esc_attr($track['key']); ?>" data-duration="<?php echo esc_attr($track['duration'] ?? '00:00'); ?>">
                                <span class="vq-drag-handle">≡</span>
                                <input type="text" class="vq-chapter-title" value="<?php echo esc_attr($track['title']); ?>" placeholder="Chapter Title">
                                <div class="vq-chapter-actions">
                                    <?php if ($storage === 'local'): ?>
                                        <span class="vq-badge vq-badge-local">LOCAL</span>
                                        <?php if ($local_exists): ?>
                                            <button class="vq-chapter-edit" title="Edit Audio" data-key="<?php echo esc_attr($track['key']); ?>">
                                                <span class="material-symbols-outlined">graphic_eq</span>
                                            </button>
                                        <?php endif; ?>
                                        <button class="vq-sync-btn" title="Sync to Cloudflare R2" data-key="<?php echo esc_attr($track['key']); ?>">
                                            <span class="material-symbols-outlined">cloud_upload</span>
                                        </button>
                                    <?php else: ?>
                                        <?php if ($is_changed): ?>
                                            <span class="vq-badge vq-badge-changed">CHANGED</span>
                                            <button class="vq-chapter-edit" title="Edit Audio" data-key="<?php echo esc_attr($track['key']); ?>">
                                                <span class="material-symbols-outlined">graphic_eq</span>
                                            </button>
                                            <button class="vq-sync-btn" title="Update Cloud with Local (Overwrite R2)" data-key="<?php echo esc_attr($track['key']); ?>">
                                                <span class="material-symbols-outlined">cloud_sync</span>
                                            </button>
                                            <button class="vq-download-from-r2" title="Restore Cloud version to Local (Overwrite Local)" data-key="<?php echo esc_attr($track['key']); ?>">
                                                <span class="material-symbols-outlined">settings_backup_restore</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="vq-badge vq-badge-r2">R2</span>
                                            <?php if ($local_exists): ?>
                                                <button class="vq-chapter-edit" title="Edit Audio" data-key="<?php echo esc_attr($track['key']); ?>">
                                                    <span class="material-symbols-outlined">graphic_eq</span>
                                                </button>
                                            <?php else: ?>
                                                <button class="vq-download-from-r2" title="Download to Local" data-key="<?php echo esc_attr($track['key']); ?>">
                                                    <span class="material-symbols-outlined">cloud_download</span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <button class="vq-inline-play" data-key="<?php echo esc_attr($track['key']); ?>" data-storage="<?php echo $storage; ?>">
                                        <span class="material-symbols-outlined">play_circle</span>
                                    </button>
                                    <button class="vq-remove-track" title="Remove">
                                        <span class="material-symbols-outlined">delete_forever</span>
                                    </button>
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

    public static function ajax_update_book_author(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $author = isset($_POST['author']) ? sanitize_text_field($_POST['author']) : '';

        $post = $post_id ? get_post($post_id) : null;
        if (!$post || $post->post_type !== 'audiobook') {
            wp_send_json_error('Invalid book ID');
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Unauthorized');
        }

        update_post_meta($post_id, '_vq_author', $author);
        wp_send_json_success(array('author' => $author));
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

        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
        if (!$folder_name) {
            $folder_name = $book_title; // Fallback
        }

        $file = $_FILES['file'];
        $raw_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $clean_name = sanitize_title($raw_name);
        $filename = $clean_name . '.' . $ext;
        $r2_key = $folder_name . '/' . $filename;

        $r2 = new R2Client();
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime_type = ($ext === 'mp3') ? 'audio/mpeg' : 'audio/wav';

        if ($r2->upload_object($file['tmp_name'], $r2_key, $mime_type)) {
            $playlist = get_post_meta($post_id, '_vq_playlist', true);
            $playlist = is_array($playlist) ? $playlist : (json_decode($playlist, true) ?: []);
            
            // Generate a beautiful title from filename
            $raw_filename = pathinfo($filename, PATHINFO_FILENAME);
            $beautiful_title = str_replace(['-', '_'], ' ', $raw_filename);
            $beautiful_title = ucwords($beautiful_title);

            $new_track = array(
                'id' => uniqid(),
                'title' => $beautiful_title,
                'key' => $r2_key,
                'duration' => self::get_wav_duration_formatted($file['tmp_name']),
                'r2_size' => filesize($file['tmp_name']) // Store size for diffing
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
        $optimized = CoverOptimizer::optimize_to_jpeg($file['tmp_name'], 614400, 1000);
        if (!empty($optimized['error'])) {
            wp_send_json_error('Cover optimization failed: ' . $optimized['error']);
        }
        $optimized_path = $optimized['path'];

        $stamp = time();
        $key = 'covers/' . $post_id . '_' . $stamp . '.jpg';

        $upload_dir = wp_upload_dir();
        $base_dir = rtrim($upload_dir['basedir'], '/') . '/voiceqwen-audiobook';
        $abs = $base_dir . '/' . $key;
        $abs_dir = dirname($abs);
        
        if (!wp_mkdir_p($abs_dir)) {
            wp_send_json_error('Failed to create covers directory');
        }

        if (@copy($optimized_path, $abs)) {
            update_post_meta($post_id, '_vq_cover_key', $key);
            @unlink($optimized_path);
            wp_send_json_success(self::get_cover_url($post_id));
        } else {
            wp_send_json_error('Failed to copy optimized image');
        }
    }

    public static function ajax_upload_background(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file provided');
        }

        $file = $_FILES['file'];
        $optimized = CoverOptimizer::optimize_background($file['tmp_name'], 614400);
        if (!empty($optimized['error'])) {
            wp_send_json_error('Background optimization failed: ' . $optimized['error']);
        }
        $optimized_path = $optimized['path'];

        $stamp = time();
        $key = 'backgrounds/' . $post_id . '_' . $stamp . '.jpg';

        $upload_dir = wp_upload_dir();
        $base_dir = rtrim($upload_dir['basedir'], '/') . '/voiceqwen-audiobook';
        $abs = $base_dir . '/' . $key;
        $abs_dir = dirname($abs);
        
        if (!wp_mkdir_p($abs_dir)) {
            wp_send_json_error('Failed to create backgrounds directory');
        }

        if (@copy($optimized_path, $abs)) {
            update_post_meta($post_id, '_vq_background_key', $key);
            @unlink($optimized_path);
            wp_send_json_success(self::get_background_url($post_id));
        } else {
            wp_send_json_error('Failed to copy optimized image');
        }
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

        error_log("VoiceQwen Shop: Requesting URL for Key: $key, Storage: $storage, Post ID: $post_id");

        if (!$key) {
            wp_send_json_error('No track key provided');
        }

        if ($post_id) {
            $current_user = wp_get_current_user();
            $username = $current_user->user_login;
            $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
            $upload_dir = wp_upload_dir();
            
            // Smarter path resolution to avoid double-folder naming
            $clean_key = $key;
            if (strpos($key, $username . '/') === 0) {
                $clean_key = substr($key, strlen($username) + 1);
            }
            
            $raw_filename = $clean_key;
            if ($folder_name && strpos($clean_key, $folder_name . '/') === 0) {
                $raw_filename = substr($clean_key, strlen($folder_name) + 1);
            }

            $base_voiceqwen_url = $upload_dir['baseurl'] . '/voiceqwen/' . $username . '/';
            $base_voiceqwen_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/';

            // Local path construction
            $rel_path = $folder_name ? $folder_name . '/' . $raw_filename : $raw_filename;
            $abs = $base_voiceqwen_dir . $rel_path;
            
            if (file_exists($abs)) {
                $v = @filemtime($abs) ?: time();
                error_log("VoiceQwen Shop: Found local file: $abs");
                wp_send_json_success($base_voiceqwen_url . $rel_path . '?v=' . $v);
            } else {
                error_log("VoiceQwen Shop: Local file NOT found: $abs");
            }
        }

        // R2 Mode (Default)
        error_log("VoiceQwen Shop: Trying R2 for Key: $key");
        $r2 = new R2Client();
        $url = $r2->get_presigned_url($key, '+1 hour');
        if ($url) {
            error_log("VoiceQwen Shop: Generated R2 URL: " . substr($url, 0, 100) . "...");
            wp_send_json_success($url);
        }
        
        error_log("VoiceQwen Shop: FAILED to generate R2 URL for Key: $key");
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
        $raw_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        // Clean filename for the key/slug
        $clean_name = sanitize_title($raw_name);
        $filename = $clean_name . '.' . $ext;
        $target_path = $book_dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            // Beautiful title for the UI
            $beautiful_title = str_replace(['-', '_'], ' ', $raw_name);
            $beautiful_title = ucwords($beautiful_title);

            wp_send_json_success(array(
                'key' => $folder_name . '/' . $filename,
                'title' => $beautiful_title
            ));
        }

        wp_send_json_error('Failed to save file locally');
    }

    public static function ajax_sync_to_r2(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $key = sanitize_text_field($_POST['key']); // The filename or rel_path
        
        error_log("VoiceQwen: Starting R2 Sync for Post ID: $post_id, Key: $key");

        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
        $current_user = wp_get_current_user();
        $username = $current_user->user_login ?: 'nicolas'; // Fallback to nicolas if empty
        $upload_dir = wp_upload_dir();
        
        $base_voiceqwen_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/';
        if (!is_dir($base_voiceqwen_dir) && $username !== 'nicolas') {
             // Second fallback: check nicolas folder directly if current user folder doesn't exist
             $base_voiceqwen_dir = $upload_dir['basedir'] . '/voiceqwen/nicolas/';
        }
        
        // Clean key
        $clean_key = $key;
        if (strpos($key, $username . '/') === 0) {
            $clean_key = substr($key, strlen($username) + 1);
        } elseif (strpos($key, 'nicolas/') === 0) {
            $clean_key = substr($key, 8);
        }

        $file_path = $base_voiceqwen_dir . $clean_key;

        // 3. Fallback logic: check various patterns and deep search
        if (!file_exists($file_path)) {
            $paths_to_check = [
                $base_voiceqwen_dir . $folder_name . '/' . $clean_key,
                $base_voiceqwen_dir . $clean_key,
                $upload_dir['basedir'] . '/voiceqwen/nicolas/' . $folder_name . '/' . $clean_key,
                $upload_dir['basedir'] . '/voiceqwen/nicolas/' . $clean_key
            ];
            
            foreach ($paths_to_check as $alt_path) {
                if (file_exists($alt_path)) {
                    $file_path = $alt_path;
                    break;
                }
            }

            // Final Deep Search: If still not found, search the filename anywhere in voiceqwen/nicolas
            if (!file_exists($file_path)) {
                $filename = basename($clean_key);
                $search_dir = $upload_dir['basedir'] . '/voiceqwen/nicolas/';
                if (is_dir($search_dir)) {
                    $it = new \RecursiveDirectoryIterator($search_dir);
                    foreach (new \RecursiveIteratorIterator($it) as $file) {
                        if (basename($file) === $filename) {
                            $file_path = $file->getPathname();
                            break;
                        }
                    }
                }
            }
        }

        error_log("VoiceQwen: Final resolved local path: $file_path (User: $username)");

        if (!file_exists($file_path)) {
            error_log("VoiceQwen: Sync Failed - File not found: $file_path");
            wp_send_json_error('Local file not found at: ' . $file_path);
            return;
        }

        $r2 = new R2Client();
        
        // Use clean_key for R2 (it already contains folder_name/ if it was a new upload)
        // If it doesn't contain folder_name/, prepend it
        $r2_key = $clean_key;
        if ($folder_name && strpos($clean_key, $folder_name . '/') !== 0) {
            $r2_key = $folder_name . '/' . $clean_key;
        }
        
        error_log("VoiceQwen: Target R2 Key: $r2_key");

        $test = $r2->test_connection();
        if ($test !== true) {
            error_log("VoiceQwen: Sync Failed - R2 Connection Error: $test");
            wp_send_json_error('R2 Connection Error: ' . $test);
        }

        $result = $r2->upload_object($file_path, $r2_key, 'audio/wav');
        if ($result === true) {
            error_log("VoiceQwen: Sync Success!");
            
            // Update playlist to mark as R2
            $playlist = get_post_meta($post_id, '_vq_playlist', true);
            if (is_array($playlist)) {
                $updated = false;
                foreach ($playlist as &$track) {
                    if ($track['key'] === $key) {
                        $track['storage'] = 'r2';
                        $track['key'] = $r2_key;
                        $track['r2_size'] = filesize($file_path); // Store current size
                        $updated = true;
                        break;
                    }
                }
                if ($updated) {
                    update_post_meta($post_id, '_vq_playlist', $playlist);
                    error_log("VoiceQwen: Playlist updated to R2 for key: $key");
                }
            }

            wp_send_json_success(['new_key' => $r2_key]);
        }
        
        error_log("VoiceQwen: Sync Failed - $result");
        wp_send_json_error('R2 upload failed: ' . $result);
    }

    public static function ajax_export_audiobook(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $post_id = intval($_POST['post_id']);
        
        $post = get_post($post_id);
        if (!$post) wp_send_json_error('Book not found');

        $author = get_post_meta($post_id, '_vq_author', true);
        $playlist = get_post_meta($post_id, '_vq_playlist', true);
        $playlist = is_array($playlist) ? $playlist : (json_decode($playlist, true) ?: []);
        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
        
        // Get Cover as Base64
        $cover_base64 = '';
        $cover_key = get_post_meta($post_id, '_vq_cover_key', true);
        if ($cover_key) {
            $upload_dir = wp_upload_dir();
            $cover_path = $upload_dir['basedir'] . '/voiceqwen-audiobook/' . $cover_key;
            if (file_exists($cover_path)) {
                $type = pathinfo($cover_path, PATHINFO_EXTENSION);
                $data = file_get_contents($cover_path);
                $cover_base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
            }
        }

        $export_data = [
            'version' => '1.0',
            'title' => $post->post_title,
            'author' => $author,
            'folder_name' => $folder_name,
            'playlist' => $playlist,
            'cover_base64' => $cover_base64
        ];

        wp_send_json_success($export_data);
    }

    public static function ajax_import_audiobook(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        
        if (empty($_FILES['file'])) {
            wp_send_json_error('No file provided');
        }

        $json_data = file_get_contents($_FILES['file']['tmp_name']);
        $data = json_decode($json_data, true);

        if (!$data || !isset($data['title'])) {
            wp_send_json_error('Invalid export file');
        }

        // Create the book post
        $new_post_id = wp_insert_post([
            'post_type' => 'audiobook',
            'post_title' => $data['title'],
            'post_status' => 'publish'
        ]);

        if (is_wp_error($new_post_id)) {
            wp_send_json_error('Failed to create book: ' . $new_post_id->get_error_message());
        }

        // Set metadata
        update_post_meta($new_post_id, '_vq_author', $data['author'] ?? '');
        update_post_meta($new_post_id, '_vq_folder_name', $data['folder_name'] ?? '');
        
        // Import playlist (ensure storage is set correctly for the live site context)
        $playlist = $data['playlist'] ?? [];
        update_post_meta($new_post_id, '_vq_playlist', $playlist);

        // Import Cover from Base64
        if (!empty($data['cover_base64'])) {
            $parts = explode(',', $data['cover_base64']);
            if (count($parts) === 2) {
                $image_data = base64_decode($parts[1]);
                $stamp = time();
                $filename = 'covers/' . $new_post_id . '_' . $stamp . '.jpg';
                
                $upload_dir = wp_upload_dir();
                $base_dir = rtrim($upload_dir['basedir'], '/') . '/voiceqwen-audiobook';
                $abs = $base_dir . '/' . $filename;
                
                wp_mkdir_p(dirname($abs));
                if (file_put_contents($abs, $image_data)) {
                    update_post_meta($new_post_id, '_vq_cover_key', $filename);
                }
            }
        }

        wp_send_json_success(['post_id' => $new_post_id]);
    }

    public static function ajax_download_from_r2(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');
        $key = sanitize_text_field($_POST['key']);
        $post_id = intval($_POST['post_id']);

        if (!$key || !$post_id) wp_send_json_error('Missing data');

        $folder_name = get_post_meta($post_id, '_vq_folder_name', true);
        $current_user = wp_get_current_user();
        $username = $current_user->user_login ?: 'nicolas';
        $upload_dir = wp_upload_dir();
        
        // Resolve target path
        $clean_key = $key;
        if (strpos($key, $username . '/') === 0) $clean_key = substr($key, strlen($username) + 1);
        elseif (strpos($key, 'nicolas/') === 0) $clean_key = substr($key, 8);
        
        // If it contains folder_name/, strip it to get the raw filename for local storage
        $raw_filename = $clean_key;
        if ($folder_name && strpos($clean_key, $folder_name . '/') === 0) {
            $raw_filename = substr($clean_key, strlen($folder_name) + 1);
        }

        $local_path = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . ($folder_name ? $folder_name . '/' : '') . $raw_filename;

        // Fetch from R2
        $r2 = new R2Client();
        $contents = $r2->get_object($key);

        if ($contents === false) {
            wp_send_json_error('Failed to fetch file from R2');
        }

        // Save locally
        wp_mkdir_p(dirname($local_path));
        if (file_put_contents($local_path, $contents)) {
            // Update r2_size to match the downloaded file exactly
            $playlist = get_post_meta($post_id, '_vq_playlist', true);
            foreach ($playlist as &$track) {
                if ($track['key'] === $key) {
                    $track['r2_size'] = filesize($local_path);
                    break;
                }
            }
            update_post_meta($post_id, '_vq_playlist', $playlist);
            wp_send_json_success('File downloaded successfully');
        } else {
            wp_send_json_error('Failed to save file locally');
        }
    }
    /**
     * Helper to get WAV duration in "MM:SS" format.
     */
    public static function get_wav_duration_formatted($file_path): string {
        $seconds = self::get_wav_duration($file_path);
        if ($seconds <= 0) return '00:00';
        
        $mins = floor($seconds / 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d', $mins, $secs);
    }

    /**
     * Helper to read WAV header and calculate duration in seconds.
     */
    public static function get_wav_duration($file): float {
        if (!file_exists($file) || !is_readable($file)) return 0;
        
        $fp = fopen($file, 'r');
        if (!$fp) return 0;
        
        // Read RIFF header
        $header = fread($fp, 12);
        if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
            fclose($fp);
            return 0;
        }

        $duration = 0;
        $bytes_per_sec = 0;
        while (!feof($fp)) {
            $chunk_header = fread($fp, 8);
            if (strlen($chunk_header) < 8) break;
            
            $chunk_id = substr($chunk_header, 0, 4);
            $chunk_size_raw = substr($chunk_header, 4, 4);
            $chunk_size_arr = unpack('V', $chunk_size_raw);
            $chunk_size = $chunk_size_arr[1];

            if ($chunk_id === 'fmt ') {
                $fmt_data = fread($fp, $chunk_size);
                $fmt = unpack('vformat/vchannels/Vsamplerate/Vbytespersec/vblockalign/vbitspersample', substr($fmt_data, 0, 16));
                $bytes_per_sec = $fmt['bytespersec'];
            } elseif ($chunk_id === 'data') {
                if ($bytes_per_sec > 0) {
                    $duration = $chunk_size / $bytes_per_sec;
                }
                fseek($fp, $chunk_size, SEEK_CUR);
            } else {
                fseek($fp, $chunk_size, SEEK_CUR);
            }
            
            if ($chunk_size % 2 !== 0) {
                fseek($fp, 1, SEEK_CUR);
            }
        }
        
        fclose($fp);
        return (float)$duration;
    }
}
