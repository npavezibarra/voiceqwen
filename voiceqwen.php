<?php
/**
 * Plugin Name: LOCUTOR
 * Description: Creates a "Voice" page and adds it to the main menu.
 * Version: 1.1
 * Author: Antigravity
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// 1. Include Logic Subsystems
require_once plugin_dir_path( __FILE__ ) . 'includes/file-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax-generation.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax-files.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax-editor.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax-meta.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/ajax-markers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-voiceqwen-audio-analyzer.php';
require_once plugin_dir_path( __FILE__ ) . 'modules/audiobook/audiobook.php';

// 2. Custom Template Loader
// 2. Custom Template Loader
function voiceqwen_custom_template( $template ) {
    if ( is_page( 'voice' ) ) {
        $custom_template = plugin_dir_path( __FILE__ ) . 'templates/voice-template.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    
    // Dynamic Audiobook Page
    $audi_slug = get_option('voiceqwen_audiobook_page_slug', 'audi');
    if ( is_page( $audi_slug ) ) {
        $custom_template = plugin_dir_path( __FILE__ ) . 'templates/audi-template.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'voiceqwen_custom_template', 99 );

// 3. Asset Management
function voiceqwen_enqueue_assets() {
    wp_enqueue_style( 'voiceqwen-base', plugins_url( 'assets/css/base.css', __FILE__ ) );
    wp_enqueue_style( 'voiceqwen-theme-vaporwave', plugins_url( 'assets/css/theme-vaporwave.css', __FILE__ ) );
    wp_enqueue_style( 'voiceqwen-waveform-viewer', plugins_url( 'assets/css/waveform-viewer.css', __FILE__ ) );
    wp_enqueue_style( 'voiceqwen-audiobook', plugins_url( 'assets/css/audiobook.css', __FILE__ ) );
    
    wp_enqueue_script( 'wavesurfer', 'https://unpkg.com/wavesurfer.js@7.12.6/dist/wavesurfer.min.js', array(), '7.12.6', true );
    wp_enqueue_script( 'wavesurfer-regions', 'https://unpkg.com/wavesurfer.js@7.12.6/dist/plugins/regions.min.js', array( 'wavesurfer' ), '7.12.6', true );
    wp_enqueue_script( 'wavesurfer-timeline', 'https://unpkg.com/wavesurfer.js@7.12.6/dist/plugins/timeline.min.js', array( 'wavesurfer' ), '7.12.6', true );
    wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', array(), '1.15.0', true );
    wp_enqueue_style( 'material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200' );

    wp_enqueue_script( 'voiceqwen-core', plugins_url( 'assets/js/core.js', __FILE__ ), array( 'jquery' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-generation', plugins_url( 'assets/js/generation.js', __FILE__ ), array( 'voiceqwen-core' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-avatar-manager', plugins_url( 'assets/js/avatar-manager.js', __FILE__ ), array( 'voiceqwen-core' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-waveform-logic', plugins_url( 'assets/js/waveform-logic.js', __FILE__ ), array( 'jquery' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-waveform-ui', plugins_url( 'assets/js/waveform-ui.js', __FILE__ ), array( 'voiceqwen-core', 'wavesurfer', 'voiceqwen-waveform-logic' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-waveform-ruler-controls', plugins_url( 'assets/js/waveform-ruler-controls.js', __FILE__ ), array( 'voiceqwen-waveform-ui' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-waveform-markers', plugins_url( 'assets/js/waveform-markers.js', __FILE__ ), array( 'voiceqwen-waveform-ui' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-file-manager', plugins_url( 'assets/js/file-manager.js', __FILE__ ), array( 'voiceqwen-core', 'voiceqwen-waveform-ui' ), '1.1', true );

    wp_enqueue_script( 'voiceqwen-audiobook', plugins_url( 'modules/audiobook/audiobook.js', __FILE__ ), array( 'voiceqwen-core' ), '1.0', true );
    wp_enqueue_script( 'voiceqwen-audiobook-author', plugins_url( 'modules/audiobook/audiobook-author.js', __FILE__ ), array( 'voiceqwen-audiobook' ), '1.0', true );
    
    $upload_dir = wp_upload_dir();
    $current_user = wp_get_current_user();

    wp_localize_script( 'voiceqwen-core', 'voiceqwen_ajax', array(
        'url'        => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'voiceqwen_nonce' ),
        'upload_url' => $upload_dir['baseurl'] . '/voiceqwen',
        'username'   => $current_user->user_login
    ) );
}
add_action( 'wp_enqueue_scripts', 'voiceqwen_enqueue_assets' );

function voiceqwen_admin_enqueue_assets( $hook ) {
    if ( 'toplevel_page_audio-analysis' !== $hook ) return;
    wp_enqueue_style( 'voiceqwen-base', plugins_url( 'assets/css/base.css', __FILE__ ) );
    
    wp_enqueue_script( 'wavesurfer', 'https://unpkg.com/wavesurfer.js@7.12.6/dist/wavesurfer.min.js', array(), '7.12.6', true );
    wp_enqueue_script( 'wavesurfer-regions', 'https://unpkg.com/wavesurfer.js@7.12.6/dist/plugins/regions.min.js', array( 'wavesurfer' ), '7.12.6', true );
    wp_enqueue_script( 'wavesurfer-timeline', 'https://unpkg.com/wavesurfer.js@7.12.6/dist/plugins/timeline.min.js', array( 'wavesurfer' ), '7.12.6', true );

    wp_enqueue_script( 'voiceqwen-core', plugins_url( 'assets/js/core.js', __FILE__ ), array( 'jquery' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-generation', plugins_url( 'assets/js/generation.js', __FILE__ ), array( 'voiceqwen-core' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-waveform-logic', plugins_url( 'assets/js/waveform-logic.js', __FILE__ ), array( 'jquery' ), '1.1', true );
    wp_enqueue_script( 'voiceqwen-file-manager', plugins_url( 'assets/js/file-manager.js', __FILE__ ), array( 'voiceqwen-core' ), '1.1', true );
    
    $upload_dir = wp_upload_dir();
    $current_user = wp_get_current_user();
    
    wp_localize_script( 'voiceqwen-core', 'voiceqwen_ajax', array(
        'url'        => admin_url( 'admin-ajax.php' ),
        'nonce'      => wp_create_nonce( 'voiceqwen_nonce' ),
        'upload_url' => $upload_dir['baseurl'] . '/voiceqwen',
        'username'   => $current_user->user_login
    ) );
}
add_action( 'admin_enqueue_scripts', 'voiceqwen_admin_enqueue_assets' );

// 4. Main UI Shortcode
function voiceqwen_ui_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Debes iniciar sesión para usar este plugin.</p>';
    }

    $theme = get_option( 'voiceqwen_theme', '90ties' );
    $deco_text = ( $theme === '90ties' ) ? "90's" : "";

    ob_start();
    ?>
    <div class="voiceqwen-main-wrapper voiceqwen-theme-<?php echo esc_attr( $theme ); ?>">
        <div class="vapor-grid-bg"></div>
        <div class="vapor-container">
            <div class="vapor-header">
                <div class="vapor-dots"><span></span><span></span><span></span></div>
                <div class="vapor-title">LOCUTOR</div>
                <div class="vapor-nav">
                    <button class="nav-btn active" data-view="create">CREATE AUDIO</button>
                    <button class="nav-btn" data-view="dialogues">DIALOGUES</button>
                    <button class="nav-btn" data-view="waveform">WAVE VIEWER</button>
                    <button class="nav-btn" data-view="audiobook">AUDIOBOOK</button>
                    <button class="nav-btn" data-view="upload-voice">UPLOAD VOICE</button>
                    <button class="nav-btn" data-view="settings">CONFIG</button>
                </div>
            </div>
            
            <div class="vapor-body">
                <!-- Sidebar: File Viewer -->
                <?php include plugin_dir_path( __FILE__ ) . 'templates/views/sidebar-files.php'; ?>

                <!-- View Containers -->
                <?php include plugin_dir_path( __FILE__ ) . 'templates/views/view-create.php'; ?>
                <?php include plugin_dir_path( __FILE__ ) . 'templates/views/view-upload-voice.php'; ?>
                <?php include plugin_dir_path( __FILE__ ) . 'templates/views/view-dialogues.php'; ?>
                <?php voiceqwen_audiobook_render_ui(); ?>
                <?php include plugin_dir_path( __FILE__ ) . 'templates/views/view-analysis.php'; ?>
                <?php include plugin_dir_path( __FILE__ ) . 'templates/views/view-waveform.php'; ?>
                <?php include plugin_dir_path( __FILE__ ) . 'templates/views/view-settings.php'; ?>
            </div>

            <?php if ( ! empty( $deco_text ) ) : ?>
                <div class="vapor-deco-text"><?php echo esc_html( $deco_text ); ?></div>
            <?php endif; ?>
        </div>

        <?php include plugin_dir_path( __FILE__ ) . 'templates/views/mini-modal.php'; ?>
        
        <div id="voiceqwen-global-status"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'voiceqwen_ui', 'voiceqwen_ui_shortcode' );

// 5. Activation / Menu Helpers
function voiceqwen_add_to_menu( $page_id ) {
    $locations = get_nav_menu_locations();
    $menu_id = null;
    if ( isset( $locations['primary'] ) ) $menu_id = $locations['primary'];
    elseif ( isset( $locations['main'] ) ) $menu_id = $locations['main'];
    else {
        $menus = wp_get_nav_menus();
        if ( ! empty( $menus ) ) $menu_id = $menus[0]->term_id;
    }

    if ( $menu_id ) {
        wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-title'     => 'LOCUTOR',
            'menu-item-object-id' => $page_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ) );
    }
}

// 6. Ensure Pages Exist
function voiceqwen_ensure_pages() {
    $audi_title = get_option('voiceqwen_audiobook_page_name', 'Audi');
    $audi_slug = get_option('voiceqwen_audiobook_page_slug', 'audi');

    $pages = array(
        'voice'    => 'LOCUTOR',
        $audi_slug => $audi_title
    );

    foreach ( $pages as $slug => $title ) {
        $page = get_page_by_path( $slug );
        if ( ! $page ) {
            $page_id = wp_insert_post( array(
                'post_title'   => $title,
                'post_content' => ( $slug === 'voice' ) ? '[voiceqwen_ui]' : '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $slug,
            ) );
            if ( $slug === 'voice' ) {
                voiceqwen_add_to_menu( $page_id );
            }
        }
    }
}
add_action( 'admin_init', 'voiceqwen_ensure_pages' );

// 7. Admin Settings Page
function voiceqwen_add_admin_menu() {
    add_menu_page(
        'LOCUTOR Settings',
        'LOCUTOR',
        'manage_options',
        'voiceqwen-settings',
        'voiceqwen_render_settings_page',
        'dashicons-microphone',
        30
    );
}
add_action( 'admin_menu', 'voiceqwen_add_admin_menu' );

function voiceqwen_render_settings_page() {
    if (isset($_POST['voiceqwen_save_admin_settings'])) {
        check_admin_referer('voiceqwen_admin_nonce');
        
        $new_name = sanitize_text_field($_POST['audiobook_page_name']);
        $old_name = get_option('voiceqwen_audiobook_page_name');
        
        if ($new_name !== $old_name) {
            update_option('voiceqwen_audiobook_page_name', $new_name);
            update_option('voiceqwen_audiobook_page_slug', sanitize_title($new_name));
            
            // Re-run page ensure logic to create/update the page
            voiceqwen_ensure_pages();
        }

        echo '<div class="updated"><p>Settings saved!</p></div>';
    }

    $page_name = get_option('voiceqwen_audiobook_page_name', 'Audi');
    $storage_mode = get_option('voiceqwen_storage_mode', 'local');
    $r2_account_id = get_option('voiceqwen_r2_account_id', '');
    $r2_access_key = get_option('voiceqwen_r2_access_key', '');
    $r2_secret_key = get_option('voiceqwen_r2_secret_key', '');
    $r2_bucket_name = get_option('voiceqwen_r2_bucket_name', '');

    if (isset($_POST['voiceqwen_save_admin_settings'])) {
        check_admin_referer('voiceqwen_admin_nonce');
        
        $new_name = sanitize_text_field($_POST['audiobook_page_name']);
        $old_name = get_option('voiceqwen_audiobook_page_name');
        
        update_option('voiceqwen_storage_mode', sanitize_text_field($_POST['storage_mode']));
        update_option('voiceqwen_r2_account_id', sanitize_text_field($_POST['r2_account_id']));
        update_option('voiceqwen_r2_access_key', sanitize_text_field($_POST['r2_access_key']));
        update_option('voiceqwen_r2_secret_key', sanitize_text_field($_POST['r2_secret_key']));
        update_option('voiceqwen_r2_bucket_name', sanitize_text_field($_POST['r2_bucket_name']));

        if ($new_name !== $old_name) {
            update_option('voiceqwen_audiobook_page_name', $new_name);
            update_option('voiceqwen_audiobook_page_slug', sanitize_title($new_name));
            voiceqwen_ensure_pages();
        }

        echo '<div class="updated"><p>Settings saved!</p></div>';
        
        // Refresh values
        $page_name = $new_name;
        $storage_mode = sanitize_text_field($_POST['storage_mode']);
        $r2_account_id = sanitize_text_field($_POST['r2_account_id']);
        $r2_access_key = sanitize_text_field($_POST['r2_access_key']);
        $r2_secret_key = sanitize_text_field($_POST['r2_secret_key']);
        $r2_bucket_name = sanitize_text_field($_POST['r2_bucket_name']);
    }
    ?>
    <div class="wrap">
        <h1>LOCUTOR Settings</h1>
        <form method="post">
            <?php wp_nonce_field('voiceqwen_admin_nonce'); ?>
            
            <h2>General Settings</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="audiobook_page_name">Audiobook Manager Page Name</label></th>
                    <td>
                        <input name="audiobook_page_name" type="text" id="audiobook_page_name" value="<?php echo esc_attr($page_name); ?>" class="regular-text">
                        <p class="description">This name will be used to create the page and its URL slug.</p>
                    </td>
                </tr>
            </table>

            <h2>Cloudflare R2 Configuration</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="storage_mode">Storage Mode</label></th>
                    <td>
                        <select name="storage_mode" id="storage_mode">
                            <option value="local" <?php selected($storage_mode, 'local'); ?>>Local Only</option>
                            <option value="r2" <?php selected($storage_mode, 'r2'); ?>>Cloudflare R2 (Hybrid)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="r2_account_id">R2 Account ID</label></th>
                    <td>
                        <input name="r2_account_id" type="text" id="r2_account_id" value="<?php echo esc_attr($r2_account_id); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="r2_access_key">Access Key ID</label></th>
                    <td>
                        <input name="r2_access_key" type="text" id="r2_access_key" value="<?php echo esc_attr($r2_access_key); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="r2_secret_key">Secret Access Key</label></th>
                    <td>
                        <input name="r2_secret_key" type="password" id="r2_secret_key" value="<?php echo esc_attr($r2_secret_key); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="r2_bucket_name">Bucket Name</label></th>
                    <td>
                        <input name="r2_bucket_name" type="text" id="r2_bucket_name" value="<?php echo esc_attr($r2_bucket_name); ?>" class="regular-text">
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="voiceqwen_save_admin_settings" id="submit" class="button button-primary" value="Save All Changes">
            </p>
        </form>
    </div>
    <?php
}
