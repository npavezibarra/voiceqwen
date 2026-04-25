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
require_once plugin_dir_path( __FILE__ ) . 'modules/audiobook-shop/audiobook-shop.php';

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

    // Dynamic Shop Page
    $shop_slug = get_option('voiceqwen_shop_page_slug', 'audiobook-shop');
    if ( is_page( $shop_slug ) ) {
        $custom_template = plugin_dir_path( __FILE__ ) . 'templates/shop-template.php';
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
    
    // Shop Assets
    wp_enqueue_style( 'voiceqwen-shop', plugins_url( 'modules/audiobook-shop/assets/css/shop.css', __FILE__ ) );
    wp_enqueue_script( 'voiceqwen-shop', plugins_url( 'modules/audiobook-shop/assets/js/shop.js', __FILE__ ), array( 'voiceqwen-core' ), '1.0', true );
    
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
        // First, check if it's already there to avoid duplicates
        $items = wp_get_nav_menu_items($menu_id);
        if ($items) {
            foreach ($items as $item) {
                if ($item->object_id == $page_id) return; 
            }
        }

        wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-title'     => get_the_title($page_id),
            'menu-item-object-id' => $page_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ) );
    }
}

function voiceqwen_remove_from_menu( $page_id_or_title ) {
    // 1. Classic Menus (Aggressive)
    $menus = wp_get_nav_menus();
    if ($menus) {
        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            if ($items) {
                foreach ($items as $item) {
                    $title = trim($item->title);
                    if (strcasecmp($title, 'Audi') === 0 || strcasecmp($title, 'Temu') === 0 || strcasecmp($title, 'Audiobook') === 0) {
                        $current_page_meta = get_posts(array('post_type' => 'page', 'meta_key' => '_vq_page_type', 'meta_value' => 'audiobook', 'posts_per_page' => 1));
                        $current_id = $current_page_meta ? $current_page_meta[0]->ID : 0;
                        if ($item->object_id == $current_id && get_option('voiceqwen_audiobook_show_in_menu', 'yes') === 'yes') continue;
                        
                        $shop_page_meta = get_posts(array('post_type' => 'page', 'meta_key' => '_vq_page_type', 'meta_value' => 'audiobook_shop', 'posts_per_page' => 1));
                        $shop_id = $shop_page_meta ? $shop_page_meta[0]->ID : 0;
                        if ($item->object_id == $shop_id && get_option('voiceqwen_shop_show_in_menu', 'yes') === 'yes') continue;

                        wp_delete_post($item->ID, true);
                    }
                }
            }
        }
    }

    // 2. Block Themes (FSE) - Aggressive Regex Brute Force
    $nav_related_posts = get_posts(array(
        'post_type' => array('wp_navigation', 'wp_template_part', 'wp_template'),
        'post_status' => 'any',
        'posts_per_page' => -1
    ));

    $current_page_meta = get_posts(array('post_type' => 'page', 'meta_key' => '_vq_page_type', 'meta_value' => 'audiobook', 'posts_per_page' => 1));
    $current_id = $current_page_meta ? $current_page_meta[0]->ID : 0;
    $show_in_menu = get_option('voiceqwen_audiobook_show_in_menu', 'yes');

    foreach ($nav_related_posts as $nav_post) {
        $content = $nav_post->post_content;
        $original_content = $content;

        // This regex finds core/navigation-link blocks with labels Audi or Temu
        // We use a callback to decide whether to keep or kill
        $pattern = '/<!-- wp:navigation-link {[^}]+} \/-->/s';
        
        $content = preg_replace_callback($pattern, function($matches) use ($current_id, $show_in_menu) {
            $block_json = '';
            if (preg_match('/{.+}/', $matches[0], $json_match)) {
                $block_json = $json_match[0];
            }
            
            $attrs = json_decode($block_json, true);
            $label = isset($attrs['label']) ? $attrs['label'] : '';
            $id = isset($attrs['id']) ? $attrs['id'] : 0;

            if (strcasecmp($label, 'Audi') === 0 || strcasecmp($label, 'Temu') === 0 || strcasecmp($label, 'Audiobook') === 0 || strcasecmp($label, 'Shop') === 0 || strcasecmp($label, 'Librería') === 0) {
                // Keep ONLY if it's our current official ID AND we want it shown
                $shop_page_meta = get_posts(array('post_type' => 'page', 'meta_key' => '_vq_page_type', 'meta_value' => 'audiobook_shop', 'posts_per_page' => 1));
                $shop_id = $shop_page_meta ? $shop_page_meta[0]->ID : 0;
                $show_shop_in_menu = get_option('voiceqwen_shop_show_in_menu', 'yes');

                if (($id == $current_id && $show_in_menu === 'yes') || ($id == $shop_id && $show_shop_in_menu === 'yes')) {
                    return $matches[0];
                }
                return ''; // KILL
            }
            return $matches[0];
        }, $content);

        if ($content !== $original_content) {
            wp_update_post(array(
                'ID' => $nav_post->ID,
                'post_content' => $content
            ));
        }
    }
}

// 6. Ensure Pages Exist
function voiceqwen_ensure_pages() {
    $audi_title = get_option('voiceqwen_audiobook_page_name', 'Audi');
    $audi_slug = get_option('voiceqwen_audiobook_page_slug', 'audi');
    $show_in_menu = get_option('voiceqwen_audiobook_show_in_menu', 'yes');

    // 1. CLEANUP DUPLICATES AND GHOST PAGES BY TITLE
    $ghost_titles = array('Audi', 'Temu', 'Audiobook');
    foreach ($ghost_titles as $g_title) {
        $ghosts = get_posts(array(
            'post_type' => 'page',
            'title' => $g_title,
            'post_status' => 'any',
            'posts_per_page' => -1
        ));
        
        if ($ghosts) {
            foreach ($ghosts as $ghost) {
                // Keep ONLY if it's the official one with the meta tag
                $is_official = get_post_meta($ghost->ID, '_vq_page_type', true) === 'audiobook';
                // If it's the official one but the title has changed, we still keep it (logic below handles update)
                // BUT if we have multiple "officials", we only keep the first one found
                static $official_kept = false;
                if ($is_official && !$official_kept) {
                    $official_kept = true;
                    continue;
                }
                
                voiceqwen_remove_from_menu($ghost->ID);
                wp_delete_post($ghost->ID, true);
            }
        }
    }

    // Re-verify main page after cleanup
    $existing_audiobook_pages = get_posts(array(
        'post_type' => 'page', 
        'meta_key' => '_vq_page_type', 
        'meta_value' => 'audiobook', 
        'posts_per_page' => 1,
        'post_status' => 'publish'
    ));
    $main_page = $existing_audiobook_pages ? $existing_audiobook_pages[0] : null;

    $existing_shop_pages = get_posts(array(
        'post_type' => 'page', 
        'meta_key' => '_vq_page_type', 
        'meta_value' => 'audiobook_shop', 
        'posts_per_page' => 1,
        'post_status' => 'publish'
    ));
    $shop_page = $existing_shop_pages ? $existing_shop_pages[0] : null;

    $shop_title = get_option('voiceqwen_shop_page_name', 'Audiobook Shop');
    $shop_slug = get_option('voiceqwen_shop_page_slug', 'audiobook-shop');
    $show_shop_in_menu = get_option('voiceqwen_shop_show_in_menu', 'yes');

    $pages = array(
        'voice'    => 'LOCUTOR',
        'audi_dyn' => $audi_title,
        'shop_dyn' => $shop_title
    );

    foreach ( $pages as $key => $title ) {
        $slug = ($key === 'voice') ? 'voice' : (($key === 'audi_dyn') ? $audi_slug : $shop_slug);
        $page = ($key === 'voice') ? get_page_by_path('voice') : (($key === 'audi_dyn') ? $main_page : $shop_page);

        if ( ! $page ) {
            $page_id = wp_insert_post( array(
                'post_title'   => $title,
                'post_content' => ( $key === 'voice' ) ? '[voiceqwen_ui]' : '',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $slug,
            ) );
            if ($key === 'audi_dyn') {
                update_post_meta($page_id, '_vq_page_type', 'audiobook');
            } elseif ($key === 'shop_dyn') {
                update_post_meta($page_id, '_vq_page_type', 'audiobook_shop');
            }
            if ( $key === 'voice' || ($key === 'audi_dyn' && $show_in_menu === 'yes') || ($key === 'shop_dyn' && $show_shop_in_menu === 'yes') ) {
                voiceqwen_add_to_menu( $page_id );
            }
        } else {
            // Update existing
            if ($page->post_title !== $title || $page->post_name !== $slug) {
                wp_update_post(array(
                    'ID' => $page->ID,
                    'post_title' => $title,
                    'post_name' => $slug
                ));
            }
            
            if ($key === 'audi_dyn') {
                if ($show_in_menu === 'yes') {
                    voiceqwen_add_to_menu($page->ID);
                } else {
                    voiceqwen_remove_from_menu($page->ID);
                }
            } elseif ($key === 'shop_dyn') {
                if ($show_shop_in_menu === 'yes') {
                    voiceqwen_add_to_menu($page->ID);
                } else {
                    voiceqwen_remove_from_menu($page->ID);
                }
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
    if (isset($_POST['voiceqwen_save_admin_settings']) && check_admin_referer('voiceqwen_admin_nonce')) {
        $new_name = sanitize_text_field($_POST['audiobook_page_name']);
        $old_name = get_option('voiceqwen_audiobook_page_name', 'Audi');
        
        $new_shop_name = sanitize_text_field($_POST['shop_page_name']);
        $old_shop_name = get_option('voiceqwen_shop_page_name', 'Audiobook Shop');

        update_option('voiceqwen_storage_mode', sanitize_text_field($_POST['storage_mode']));
        update_option('voiceqwen_r2_account_id', sanitize_text_field($_POST['r2_account_id']));
        update_option('voiceqwen_r2_access_key', sanitize_text_field($_POST['r2_access_key']));
        update_option('voiceqwen_r2_secret_key', sanitize_text_field($_POST['r2_secret_key']));
        update_option('voiceqwen_r2_bucket_name', sanitize_text_field($_POST['r2_bucket_name']));
        
        $show_in_menu = isset($_POST['show_in_menu']) ? 'yes' : 'no';
        update_option('voiceqwen_audiobook_show_in_menu', $show_in_menu);

        $show_shop_in_menu = isset($_POST['show_shop_in_menu']) ? 'yes' : 'no';
        update_option('voiceqwen_shop_show_in_menu', $show_shop_in_menu);

        if ($new_name !== $old_name) {
            update_option('voiceqwen_audiobook_page_name', $new_name);
            update_option('voiceqwen_audiobook_page_slug', sanitize_title($new_name));
        }

        if ($new_shop_name !== $old_shop_name) {
            update_option('voiceqwen_shop_page_name', $new_shop_name);
            update_option('voiceqwen_shop_page_slug', sanitize_title($new_shop_name));
        }
        
        voiceqwen_ensure_pages();
        echo '<div class="updated"><p>Settings saved successfully!</p></div>';
    }

    $page_name = get_option('voiceqwen_audiobook_page_name', 'Audi');
    $storage_mode = get_option('voiceqwen_storage_mode', 'local');
    $r2_account_id = get_option('voiceqwen_r2_account_id', '');
    $r2_access_key = get_option('voiceqwen_r2_access_key', '');
    $r2_secret_key = get_option('voiceqwen_r2_secret_key', '');
    $r2_bucket_name = get_option('voiceqwen_r2_bucket_name', '');
    $show_in_menu = get_option('voiceqwen_audiobook_show_in_menu', 'yes');
    ?>
    <div class="wrap">
        <h1>LOCUTOR Settings</h1>
        <form method="post">
            <?php wp_nonce_field('voiceqwen_admin_nonce'); ?>
            
            <h2>General Settings</h2>
            <?php 
            $page_name = get_option('voiceqwen_audiobook_page_name', 'Audi');
            $existing = get_posts(array('post_type' => 'page', 'meta_key' => '_vq_page_type', 'meta_value' => 'audiobook', 'posts_per_page' => 1));
            $page_url = $existing ? get_permalink($existing[0]->ID) : home_url(get_option('voiceqwen_audiobook_page_slug', 'audi'));
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="audiobook_page_name">Audiobook Manager Page Name</label></th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input name="audiobook_page_name" type="text" id="audiobook_page_name" value="<?php echo esc_attr($page_name); ?>" class="regular-text">
                            <a href="<?php echo esc_url($page_url); ?>" class="button button-primary" target="_blank">GO TO PAGE</a>
                        </div>
                        <p class="description">This name will be used to create the page and its URL slug.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Menu Visibility (Manager)</th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_in_menu" value="yes" <?php checked(get_option('voiceqwen_audiobook_show_in_menu', 'yes'), 'yes'); ?>>
                            Show in main menu
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="shop_page_name">Audiobook Shop Page Name</label></th>
                    <td>
                        <?php 
                        $shop_name = get_option('voiceqwen_shop_page_name', 'Audiobook Shop');
                        $existing_shop = get_posts(array('post_type' => 'page', 'meta_key' => '_vq_page_type', 'meta_value' => 'audiobook_shop', 'posts_per_page' => 1));
                        $shop_url = $existing_shop ? get_permalink($existing_shop[0]->ID) : home_url(get_option('voiceqwen_shop_page_slug', 'audiobook-shop'));
                        ?>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input name="shop_page_name" type="text" id="shop_page_name" value="<?php echo esc_attr($shop_name); ?>" class="regular-text">
                            <a href="<?php echo esc_url($shop_url); ?>" class="button button-primary" target="_blank">GO TO PAGE</a>
                        </div>
                        <p class="description">This name will be used to create the Shop page and its URL slug.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Menu Visibility (Shop)</th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_shop_in_menu" value="yes" <?php checked(get_option('voiceqwen_shop_show_in_menu', 'yes'), 'yes'); ?>>
                            Show in main menu
                        </label>
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
