<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

use VoiceQwen\Audiobook\AudiobookUtils;
use VoiceQwen\Audiobook\R2Client;
use VoiceQwen\Audiobook\CoverOptimizer;

class AudiobookUI
{
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
    public static function render_book_item($post): void
    {
        $author = get_post_meta($post->ID, '_vq_author', true);
        $cover_url = AudiobookUtils::get_cover_url($post->ID);
        ?>
        <div class="vq-book-item" data-id="<?php echo $post->ID; ?>">
            <div class="vq-book-item-thumb">
                <?php if ($cover_url): ?>
                    <img src="<?php echo esc_url($cover_url); ?>" alt="">
                <?php else: ?>
                    <div class="vq-thumb-placeholder"></div>
                <?php endif; ?>
            </div>
            <div class="vq-book-item-info">
                <div class="vq-book-item-title"><?php echo esc_html($post->post_title); ?></div>
                <div class="vq-book-item-author"><?php echo esc_html($author); ?></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a full book editor card.
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
        $dirty = false;
        $upload_dir = wp_upload_dir();

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
                <?php $cover_url = AudiobookUtils::get_cover_url($post->ID); ?>
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
                        <input type="text" class="vq-card-author-input" placeholder="Author..." value="" autocomplete="off" spellcheck="false">
                    <?php endif; ?>
                </div>
                <div class="vq-card-actions">
                    <button class="nav-btn vq-upload-cover-btn" data-id="<?php echo $post->ID; ?>">Cover</button>
                    <input type="file" class="vq-cover-uploader" style="display:none;" accept="image/*">

                    <button class="nav-btn vq-upload-background-btn" data-id="<?php echo $post->ID; ?>">FONDO</button>
                    <input type="file" class="vq-background-uploader" style="display:none;" accept="image/*">
                    
                    <button class="nav-btn vq-export-book-btn" data-id="<?php echo $post->ID; ?>" title="Download book data for import">EXPORT</button>
                    
                    <div class="vq-dropdown-wrap">
                        <button class="nav-btn vq-chapter-dropdown-btn">+ CAPÍTULO</button>
                        <div class="vq-dropdown-menu">
                            <div class="vq-dropdown-item" data-action="upload-wav">Upload WAV</div>
                            <div class="vq-dropdown-item" data-action="upload-txt">Upload Chapters (.txt)</div>
                            <div class="vq-dropdown-item" data-action="select-r2">Select R2 Audio</div>
                            <div class="vq-dropdown-item" data-action="create-audio">Create Audio</div>
                        </div>
                    </div>
                    <input type="file" class="vq-text-uploader" style="display:none;" accept=".txt" multiple>
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
                            $is_changed = false;
                            $local_exists = false;
                            $local_path = '';

                            // Fuzzy Audio Discovery
                            if (empty($track['key'])) {
                                $target_prefix = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                                $base_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/';
                                if (is_dir($base_dir)) {
                                    $files = scandir($base_dir);
                                    foreach ($files as $f) {
                                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                        if ($ext !== 'wav') continue;
                                        
                                        // Match if it starts with the prefix (e.g. "03...") or contains it as a segment (e.g. "...-03-...")
                                        if (strpos($f, $target_prefix) === 0 || strpos($f, '-' . $target_prefix . '-') !== false || strpos($f, ' ' . $target_prefix . ' ') !== false) {
                                            $track['key'] = $folder_name . '/' . $f;
                                            $track['storage'] = 'local';
                                            $playlist[$index]['key'] = $track['key'];
                                            $playlist[$index]['storage'] = 'local';
                                            update_post_meta($post->ID, '_vq_playlist', $playlist);
                                            
                                            $dirty = true;
                                            $local_exists = true;
                                            $local_path = $base_dir . $f;
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            $clean_key = $track['key'] ?? '';
                            if (strpos($clean_key, $username . '/') === 0) {
                                $clean_key = substr($clean_key, strlen($username) + 1);
                            }
                            $raw_filename = $clean_key;
                            if ($folder_name && strpos($clean_key, $folder_name . '/') === 0) {
                                $raw_filename = substr($clean_key, strlen($folder_name) + 1);
                            }
                            
                            $paths_to_check = [];
                            if (!empty($raw_filename)) {
                                $paths_to_check = [
                                    $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/' . $raw_filename,
                                    $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $raw_filename,
                                ];
                            }

                            foreach ($paths_to_check as $path) {
                                if (is_file($path)) {
                                    $local_path = $path;
                                    $local_exists = true;
                                    break;
                                }
                            }

                            if (!$local_exists) {
                                $filename = basename($clean_key);
                                $possible_paths = [
                                    $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/' . $filename,
                                    $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $filename
                                ];
                                foreach ($possible_paths as $p) {
                                    if (is_file($p)) {
                                        $local_path = $p;
                                        $local_exists = true;
                                        break;
                                    }
                                }
                            }
                            
                            // Fuzzy Text Discovery
                            if (empty($track['text_key'])) {
                                $target_prefix = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
                                $base_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username . '/' . $folder_name . '/';
                                if (is_dir($base_dir)) {
                                    $files = scandir($base_dir);
                                    foreach ($files as $f) {
                                        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                                        if ($ext !== 'txt') continue;

                                        if (strpos($f, $target_prefix) === 0 || strpos($f, '-' . $target_prefix . '-') !== false || strpos($f, ' ' . $target_prefix . ' ') !== false) {
                                            $track['text_key'] = $folder_name . '/' . $f;
                                            $playlist[$index]['text_key'] = $track['text_key'];
                                            update_post_meta($post->ID, '_vq_playlist', $playlist);
                                            break;
                                        }
                                    }
                                }
                            }
                            
                            $storage = $track['storage'] ?? 'local';
                            if (empty($track['key'])) {
                                $storage = 'text';
                            } elseif (!empty($track['text_key']) && !$local_exists && empty($track['r2_size'])) {
                                $storage = 'text';
                            } elseif ($storage === 'r2' && $local_exists && empty($track['r2_size'])) {
                                // Prevent false positive R2 tags: if it exists locally but we have no R2 verification, it's local
                                $storage = 'local';
                            }
                            
                            if ($storage === 'r2' && $local_exists && !empty($track['r2_size'])) {
                                $local_size = filesize($local_path);
                                if ($local_size != $track['r2_size']) {
                                    $is_changed = true;
                                }
                            }
                        ?>
                            <li class="vq-chapter-item" data-key="<?php echo esc_attr($track['key'] ?? ''); ?>" data-text-key="<?php echo esc_attr($track['text_key'] ?? ''); ?>" data-duration="<?php echo esc_attr($track['duration'] ?? '00:00'); ?>">
                                <span class="vq-drag-handle">≡</span>
                                <input type="text" class="vq-chapter-title" value="<?php echo esc_attr($track['title']); ?>" placeholder="Chapter Title">
                                <div class="vq-chapter-actions">
                                    <?php if ($storage === 'text'): ?>
                                        <span class="vq-badge vq-badge-text">TEXT</span>
                                        <button class="vq-chapter-voice" title="Generate Speech" data-text-key="<?php echo esc_attr($track['text_key']); ?>">
                                            <span class="material-symbols-outlined">mic</span>
                                        </button>
                                    <?php elseif ($storage === 'local'): ?>
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
                                    <?php if ($storage !== 'text'): ?>
                                        <button class="vq-inline-play" data-key="<?php echo esc_attr($track['key']); ?>" data-storage="<?php echo $storage; ?>">
                                            <span class="material-symbols-outlined">play_circle</span>
                                        </button>
                                    <?php endif; ?>
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
}
