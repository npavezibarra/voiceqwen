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
function voiceqwen_custom_template( $template ) {
    if ( is_page( 'voice' ) ) {
        $custom_template = plugin_dir_path( __FILE__ ) . 'templates/voice-template.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    if ( is_page( 'audi' ) ) {
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
    $pages = array(
        'voice' => 'LOCUTOR',
        'audi'  => 'Audi'
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
