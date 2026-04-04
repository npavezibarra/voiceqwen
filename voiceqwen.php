<?php
/**
 * Plugin Name: ARCHETYPICAL CHILEAN
 * Description: Creates a "Voice" page and adds it to the main menu.
 * Version: 1.0
 * Author: Antigravity
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Include required classes
require_once plugin_dir_path( __FILE__ ) . 'includes/class-voiceqwen-audio-analyzer.php';


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Load custom template for the "Voice" page.
 */
function voiceqwen_custom_template( $template ) {
    if ( is_page( 'voice' ) ) {
        $custom_template = plugin_dir_path( __FILE__ ) . 'templates/voice-template.php';
        if ( file_exists( $custom_template ) ) {
            return $custom_template;
        }
    }
    return $template;
}
add_filter( 'template_include', 'voiceqwen_custom_template', 99 );

/**
 * Enqueue scripts and styles.
 */
function voiceqwen_enqueue_assets() {
    wp_enqueue_style( 'voiceqwen-style', plugins_url( 'assets/style.css', __FILE__ ) );
    wp_enqueue_script( 'voiceqwen-script', plugins_url( 'assets/script.js', __FILE__ ), array( 'jquery' ), '1.0', true );
    wp_localize_script( 'voiceqwen-script', 'voiceqwen_ajax', array(
        'url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'voiceqwen_nonce' )
    ) );
}
add_action( 'wp_enqueue_scripts', 'voiceqwen_enqueue_assets' );

/**
 * Enqueue scripts and styles for admin.
 */
function voiceqwen_admin_enqueue_assets( $hook ) {
    if ( 'toplevel_page_audio-analysis' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'voiceqwen-admin-style', plugins_url( 'assets/style.css', __FILE__ ) );
    wp_enqueue_script( 'voiceqwen-admin-script', plugins_url( 'assets/script.js', __FILE__ ), array( 'jquery' ), '1.0', true );
    wp_localize_script( 'voiceqwen-admin-script', 'voiceqwen_ajax', array(
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'voiceqwen_nonce' )
    ) );
}
add_action( 'admin_enqueue_scripts', 'voiceqwen_admin_enqueue_assets' );


/**
 * Get avatar URL for a voice (custom or default).
 */
function voiceqwen_get_avatar_url( $voice ) {
    $upload_dir = wp_upload_dir();
    $custom_file = $upload_dir['basedir'] . '/voiceqwen/avatars/' . $voice . '_custom.png';
    if ( file_exists( $custom_file ) ) {
        return $upload_dir['baseurl'] . '/voiceqwen/avatars/' . $voice . '_custom.png?v=' . filemtime( $custom_file );
    }
    return plugin_dir_url( __FILE__ ) . 'assets/images/' . $voice . '.png';
}

/**
 * UI Shortcode.
 */
function voiceqwen_ui_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Debes iniciar sesión para usar este plugin.</p>';
    }

    ob_start();
    ?>
    <div class="vapor-grid-bg"></div>
    <div class="vapor-container">
        <div class="vapor-header">
            <div class="vapor-dots">
                <span></span><span></span><span></span>
            </div>
            <div class="vapor-title">ARCHETYPICAL CHILEAN</div>
            <div class="vapor-nav">
                <button class="nav-btn active" data-view="create">CREATE AUDIO</button>
                <button class="nav-btn" data-view="dialogues">DIALOGUES</button>
                <button class="nav-btn" data-view="upload-voice">UPLOAD VOICE</button>
            </div>
        </div>
        
        <div class="vapor-body">
            <!-- Sidebar: File Viewer -->
            <div class="vapor-window sidebar">
                <div class="vapor-window-header">
                    <div class="vapor-dots"><span></span><span></span><span></span></div>
                    <div class="vapor-window-title">Mis Archivos</div>
                </div>
                <div class="sidebar-controls" style="padding: 10px; border-bottom: 2px solid #0000ff; background: rgba(0,0,255,0.05);">
                    <button id="frontend-analyze-btn" class="nav-btn" style="width: 100%; margin: 0; padding: 5px;">ANALYZE FILES</button>
                </div>
                <ul id="file-list" class="vapor-list">
                    <li class="loading">Cargando...</li>
                </ul>
                <div id="sidebar-player"></div>
            </div>

            <!-- View 1: Create Audio -->
            <div class="vapor-window main view-pane" id="view-create">
                <div class="vapor-window-header">
                    <div class="vapor-dots"><span></span><span></span><span></span></div>
                    <div class="vapor-window-title">SELECCIONA TU CHILENO FAVORITO</div>
                </div>
                
                <div class="voice-selector" id="dynamic-voice-selector">
                    <!-- Loaded dynamically via JS/PHP -->
                    <p>Cargando voces...</p>
                </div>
                
                <div class="vapor-tabs">
                    <button class="vapor-tab active" data-tab="textarea">Texto</button>
                    <button class="vapor-tab" data-tab="upload">Archivo .txt</button>
                </div>

                <div class="vapor-pane" id="pane-textarea">
                    <textarea id="tts-text" placeholder="Escribe el texto aquí..."></textarea>
                </div>

                <div class="vapor-pane hidden" id="pane-upload">
                    <div class="upload-box">
                        <label for="tts-file">Seleccionar archivo .txt:</label>
                        <input type="file" id="tts-file" accept=".txt">
                    </div>
                </div>

                <div class="controls">
                    <button id="generate-btn" class="vapor-btn-main">Generar Audio</button>
                    <button id="reset-status-btn" class="vapor-btn-main hidden" style="background: #ff0000; margin-top: 5px; font-size: 18px;">Cancelar / Reset</button>
                </div>

                <div id="status-msg"></div>
                <div id="audio-container"></div>
            </div>

            <!-- View 2: Upload Voice -->
            <div class="vapor-window main view-pane hidden" id="view-upload-voice">
                <div class="vapor-window-header">
                    <div class="vapor-dots"><span></span><span></span><span></span></div>
                    <div class="vapor-window-title">NUEVO CHILENO FAVORITO</div>
                </div>
                
                <div class="vapor-pane">
                    <form id="upload-voice-form">
                        <div class="form-group">
                            <label>Nombre del Personaje:</label>
                            <input type="text" id="new-voice-name" placeholder="Ej: Condorito" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Audio de Muestra (.wav):</label>
                            <input type="file" id="new-voice-audio" accept=".wav" required>
                            <small>Muestra de voz clara, idealmente 10-20 segundos.</small>
                        </div>

                        <div class="form-group">
                            <label>Transcripción Exacta:</label>
                            <textarea id="new-voice-text" placeholder="Escribe exactamente lo que dice el audio de arriba..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label>Foto de Avatar:</label>
                            <input type="file" id="new-voice-avatar" accept="image/*" required>
                        </div>

                        <button type="submit" class="vapor-btn-main" style="margin: 20px 0 0 0; width: 100%;">GUARDAR CHILENO</button>
                    </form>
                    <div id="upload-status" style="margin-top: 15px;"></div>
                </div>
            </div>

            <!-- View 4: Dialogues -->
            <div class="vapor-window main view-pane hidden" id="view-dialogues">
                <div class="vapor-window-header">
                    <div class="vapor-dots"><span></span><span></span><span></span></div>
                    <div class="vapor-window-title">MULTI-VOICE DIALOGUES</div>
                </div>
                
                <div class="vapor-pane">
                    <div style="background: rgba(0,0,255,0.05); padding: 10px; border: 1px dashed #0000ff; margin-bottom: 10px; font-size: 14px;">
                        <strong>Cómo usar:</strong> Envuelve cada diálogo con etiquetas como <code>[Fernando]Hola![/Fernando]</code>. 
                        Los nombres deben coincidir con los personajes creados.
                    </div>
                    <textarea id="dialogue-text" placeholder="[Fernando]Hola Mary Rose, ¿cómo estás?[/Fernando] [Mary Rose]¡Muy bien Fernando! ¿Y tú?[/Mary Rose]" style="height: 200px;"></textarea>
                </div>

                <div class="controls">
                    <button id="generate-dialogue-btn" class="vapor-btn-main">Generar Diálogo</button>
                </div>
                <div id="dialogue-status-msg"></div>
            </div>

            <!-- View 3: Analysis -->
            <div class="vapor-window main view-pane hidden" id="id-view-analysis">
                <div class="vapor-window-header">
                    <div class="vapor-dots"><span></span><span></span><span></span></div>
                    <div class="vapor-window-title">AUDIO QUALITY REPORT</div>
                </div>
                
                <div id="fn-analysis-loading" class="hidden" style="text-align: center; padding: 40px;">
                    <div class="vapor-dots" style="justify-content: center; margin-bottom: 15px;"><span></span><span></span><span></span></div>
                    <p style="font-size: 24px;">RUNNING QC ENGINE...</p>
                </div>

                <div id="fn-analysis-results" class="hidden">
                    <div class="vapor-pane" style="max-height: 400px; overflow-y: auto;">
                        <table class="fn-report-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 3px solid #000; text-align: left; color: #0000ff;">
                                    <th style="padding: 5px;">File</th>
                                    <th style="padding: 5px;">Peak</th>
                                    <th style="padding: 5px;">RMS</th>
                                    <th style="padding: 5px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="fn-analysis-body"></tbody>
                        </table>
                    </div>
                    
                    <div class="vapor-pane" style="border-top: 3px solid #0000ff; background: rgba(255,0,255,0.05);">
                        <div id="fn-analysis-summary"></div>
                        <div id="fn-analysis-recommendation" style="margin-top: 15px; padding: 10px; border: 2px dashed #ff00ff;"></div>
                    </div>
                </div>

                <div class="controls" style="padding: 10px;">
                    <button class="nav-btn-back" data-view="create" style="background:#fff; border:2px solid #000; color:#000; padding:5px 10px; cursor:pointer;">← BACK TO CREATE</button>
                </div>
            </div>

        </div>
        <div class="vapor-deco-text">90's</div>

    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'voiceqwen_ui', 'voiceqwen_ui_shortcode' );

/**
 * AJAX Handler: Reset status manually.
 */
function voiceqwen_reset_status() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $status_file = $user_dir . '/status.json';

    if ( file_exists( $status_file ) ) {
        unlink( $status_file );
        wp_send_json_success( 'Estado reiniciado y desbloqueado' );
    } else {
        wp_send_json_error( 'No hay proceso activo' );
    }
}
add_action( 'wp_ajax_voiceqwen_reset_status', 'voiceqwen_reset_status' );

/**
 * Check if a background job is already running for the user.
 */
function voiceqwen_is_job_running( $user_dir ) {
    $status_file = $user_dir . '/status.json';
    if ( ! file_exists( $status_file ) ) return false;
    
    $data = json_decode( file_get_contents( $status_file ), true );
    return ( isset( $data['status'] ) && $data['status'] === 'processing' );
}

/**
 * AJAX Handler to generate audio.
 */
function voiceqwen_generate_audio() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Usuario no identificado.' );
    }

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;

    $text = '';
    if ( isset( $_POST['text'] ) ) {
        $text = sanitize_textarea_field( $_POST['text'] );
    } elseif ( isset( $_FILES['file'] ) ) {
        $file = $_FILES['file'];
        if ( $file['type'] !== 'text/plain' ) {
             wp_send_json_error( 'Formato de archivo no válido. Solo .txt' );
        }
        $text = file_get_contents( $file['tmp_name'] );
        $text = sanitize_textarea_field( $text );
    }

    $voice = sanitize_text_field( $_POST['voice'] );

    if ( empty( $text ) ) {
        wp_send_json_error( 'El texto está vacío.' );
    }

    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    if ( ! file_exists( $user_dir ) ) {
        mkdir( $user_dir, 0755, true );
    }

    if ( voiceqwen_is_job_running( $user_dir ) ) {
        wp_send_json_error( 'YA HAY UN PROCESO EN CURSO. Por favor espera a que termine o usa "CANCELAR / RESET" para limpiar el estado.' );
    }

    $filename = 'tts_' . time() . '_' . $voice . '.wav';
    $output_path = $user_dir . '/' . $filename;
    $log_path = $user_dir . '/last_job.log';

    // Use the absolute path to the venv python
    $python_path = '/Users/nicolas/Local Sites/voiceqwen/app/public/qwet_test/.venv/bin/python3';
    if ( ! file_exists( $python_path ) ) $python_path = 'python3';

    $script_path = plugin_dir_path( __FILE__ ) . 'tts_cli.py';
    
    $status_file = $user_dir . '/status.json';
    $cmd = sprintf(
        '%s %s --text %s --voice %s --output %s --status_file %s',
        escapeshellarg( $python_path ),
        escapeshellarg( $script_path ),
        escapeshellarg( $text ),
        escapeshellarg( $voice ),
        escapeshellarg( $output_path ),
        escapeshellarg( $status_file )
    );

    // Write status file to track background job
    file_put_contents( $status_file, json_encode( array( 'status' => 'processing', 'filename' => $filename, 'time' => time() ) ) );

    // Build the background job script
    $script_lines = array(
        '#!/bin/bash',
        'export PYTHONIOENCODING=utf-8',
        sprintf( '%s > %s 2>&1', $cmd, escapeshellarg( $log_path ) ),
        'rm -f ' . escapeshellarg( $status_file )
    );
    
    $job_script = $user_dir . '/run_job.sh';
    file_put_contents( $job_script, implode("\n", $script_lines) . "\n" );
    chmod( $job_script, 0755 );

    // Debug log
    file_put_contents( $user_dir . '/debug_exec.log', "Script: " . $job_script . "\nTime: " . date('Y-m-d H:i:s') . "\n" );

    // Run in background explicitly with absolute paths for Mac environment
    $nohup_path = '/usr/bin/nohup';
    $bash_path = '/bin/bash';
    if (!file_exists($nohup_path)) $nohup_path = 'nohup';
    if (!file_exists($bash_path)) $bash_path = 'bash';

    $final_cmd = sprintf( "%s %s %s > /dev/null 2>&1 &", $nohup_path, $bash_path, escapeshellarg( $job_script ) );
    exec( $final_cmd );

    wp_send_json_success( array(
        'status'   => 'processing',
        'message'  => 'Generando en segundo plano...',
        'filename' => $filename
    ) );
}
add_action( 'wp_ajax_voiceqwen_generate_audio', 'voiceqwen_generate_audio' );

/**
 * AJAX Handler to generate dialogue audio.
 */
function voiceqwen_generate_dialogue() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Usuario no identificado.' );
    }

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;

    $text = sanitize_textarea_field( $_POST['text'] );
    if ( empty( $text ) ) {
        wp_send_json_error( 'El texto está vacío.' );
    }

    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    if ( ! file_exists( $user_dir ) ) {
        mkdir( $user_dir, 0755, true );
    }

    if ( voiceqwen_is_job_running( $user_dir ) ) {
        wp_send_json_error( 'YA HAY UN PROCESO EN CURSO. Por favor espera a que termine o usa "CANCELAR / RESET" para limpiar el estado.' );
    }

    $filename = 'dialogue_' . time() . '.wav';
    $output_path = $user_dir . '/' . $filename;
    $log_path = $user_dir . '/last_job.log';

    $python_path = '/Users/nicolas/Local Sites/voiceqwen/app/public/qwet_test/.venv/bin/python3';
    if ( ! file_exists( $python_path ) ) $python_path = 'python3';

    $script_path = plugin_dir_path( __FILE__ ) . 'tts_dialogue.py';
    
    $status_file = $user_dir . '/status.json';
    $cmd = sprintf(
        '%s %s --text %s --output %s --status_file %s',
        escapeshellarg( $python_path ),
        escapeshellarg( $script_path ),
        escapeshellarg( $text ),
        escapeshellarg( $output_path ),
        escapeshellarg( $status_file )
    );

    file_put_contents( $status_file, json_encode( array( 'status' => 'processing', 'filename' => $filename, 'time' => time() ) ) );

    $script_lines = array(
        '#!/bin/bash',
        'export PYTHONIOENCODING=utf-8',
        sprintf( '%s > %s 2>&1', $cmd, escapeshellarg( $log_path ) ),
        'rm -f ' . escapeshellarg( $status_file )
    );
    
    $job_script = $user_dir . '/run_job.sh';
    file_put_contents( $job_script, implode("\n", $script_lines) . "\n" );
    chmod( $job_script, 0755 );

    $nohup_path = '/usr/bin/nohup';
    $bash_path = '/bin/bash';
    if (!file_exists($nohup_path)) $nohup_path = 'nohup';
    if (!file_exists($bash_path)) $bash_path = 'bash';

    $final_cmd = sprintf( "%s %s %s > /dev/null 2>&1 &", $nohup_path, $bash_path, escapeshellarg( $job_script ) );
    exec( $final_cmd );

    wp_send_json_success( array(
        'status'   => 'processing',
        'message'  => 'Generando diálogo...',
        'filename' => $filename
    ) );
}
add_action( 'wp_ajax_voiceqwen_generate_dialogue', 'voiceqwen_generate_dialogue' );

/**
 * AJAX Handler to check background job status.
 */
function voiceqwen_check_status() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $status_file = $user_dir . '/status.json';

    if ( file_exists( $status_file ) ) {
        $status_data = json_decode( file_get_contents( $status_file ), true );
        
        // Fallback: If the expected output file already exists, the job might have finished but failed to delete status.json
        if ( isset( $status_data['filename'] ) && file_exists( $user_dir . '/' . $status_data['filename'] ) ) {
            unlink( $status_file );
            wp_send_json_success( array( 'status' => 'idle' ) );
            return;
        }

        wp_send_json_success( array( 
            'status' => $status_data['status'] === 'completed' ? 'completed' : 'processing', 
            'details' => $status_data 
        ) );
    } else {
        wp_send_json_success( array( 'status' => 'idle' ) );
    }
}
add_action( 'wp_ajax_voiceqwen_check_status', 'voiceqwen_check_status' );

/**
 * AJAX Handler to rename a file.
 */
function voiceqwen_rename_file() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $old_name = sanitize_file_name( $_POST['old_name'] );
    $new_name = sanitize_file_name( $_POST['new_name'] );

    if ( empty( $old_name ) || empty( $new_name ) ) wp_send_json_error( 'Nombres inválidos' );

    // Ensure .wav extension
    if ( substr( strtolower( $new_name ), -4 ) !== '.wav' ) {
        $new_name .= '.wav';
    }

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    
    $old_path = $user_dir . '/' . $old_name;
    $new_path = $user_dir . '/' . $new_name;

    if ( file_exists( $old_path ) && ! file_exists( $new_path ) && str_contains( $old_name, '..' ) === false ) {
        if ( rename( $old_path, $new_path ) ) {
            wp_send_json_success( 'Renombrado correctamente' );
        } else {
            wp_send_json_error( 'Error al renombrar' );
        }
    } else {
        wp_send_json_error( 'El archivo no existe o el nombre nuevo ya está en uso' );
    }
}
add_action( 'wp_ajax_voiceqwen_rename_file', 'voiceqwen_rename_file' );

/**
 * AJAX: List user files.
 */
function voiceqwen_list_files() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error();
    }

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $base_url = $upload_dir['baseurl'] . '/voiceqwen/' . $username . '/';

    $files = array();
    if ( file_exists( $user_dir ) ) {
        $scan = scandir( $user_dir );
        foreach ( $scan as $file ) {
            if ( str_contains( $file, '.wav' ) ) {
                $files[] = array(
                    'name' => $file,
                    'url'  => $base_url . $file
                );
            }
        }
    }
    
    wp_send_json_success( $files );
}
add_action( 'wp_ajax_voiceqwen_list_files', 'voiceqwen_list_files' );

/**
 * AJAX: Delete file.
 */
function voiceqwen_delete_file() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error();
    }

    $filename = sanitize_text_field( $_POST['filename'] );
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $file_path = $user_dir . '/' . $filename;

    if ( file_exists( $file_path ) && str_contains( $filename, '..' ) === false ) {
        unlink( $file_path );
        wp_send_json_success();
    } else {
        wp_send_json_error( 'Archivo no encontrado.' );
    }
}
add_action( 'wp_ajax_voiceqwen_delete_file', 'voiceqwen_delete_file' );

/**
 * Add the page to the primary menu.
 */
function voiceqwen_add_to_menu( $page_id ) {
    $menu_name = 'Primary Menu'; // Common menu name, but we should find the primary location
    $locations = get_nav_menu_locations();
    
    // Look for 'primary' or 'main' locations
    $menu_id = null;
    if ( isset( $locations['primary'] ) ) {
        $menu_id = $locations['primary'];
    } elseif ( isset( $locations['main'] ) ) {
        $menu_id = $locations['main'];
    } else {
        // Find any menu if primary/main not found
        $menus = wp_get_nav_menus();
        if ( ! empty( $menus ) ) {
            $menu_id = $menus[0]->term_id;
        }
    }

    if ( $menu_id ) {
        wp_update_nav_menu_item( $menu_id, 0, array(
            'menu-item-title'     => 'ARCHETYPICAL CHILEAN',
            'menu-item-object-id' => $page_id,
            'menu-item-object'    => 'page',
            'menu-item-type'      => 'post_type',
            'menu-item-status'    => 'publish',
        ) );
    }
}

/**
 * AJAX: Update custom avatar.
 */
function voiceqwen_update_avatar() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    
    if ( ! is_user_logged_in() ) {
         wp_send_json_error( 'No autorizado' );
    }

    $voice = sanitize_text_field( $_POST['voice'] );
    $image_data = $_POST['image']; // Base64 data

    if ( empty( $voice ) || empty( $image_data ) ) {
        wp_send_json_error( 'Datos incompletos' );
    }

    // Extract base64
    if ( preg_match( '/^data:image\/(\w+);base64,/', $image_data, $type ) ) {
        $image_data = substr( $image_data, strpos( $image_data, ',' ) + 1 );
        $image_data = base64_decode( $image_data );
    } else {
        wp_send_json_error( 'Formato de imagen no válido' );
    }

    $upload_dir = wp_upload_dir();
    $avatar_dir = $upload_dir['basedir'] . '/voiceqwen/avatars';
    if ( ! file_exists( $avatar_dir ) ) {
        mkdir( $avatar_dir, 0755, true );
    }

    $file_path = $avatar_dir . '/' . $voice . '_custom.png';
    if ( file_put_contents( $file_path, $image_data ) ) {
        wp_send_json_success( 'Imagen guardada' );
    } else {
        wp_send_json_error( 'Error al guardar el archivo' );
    }
}
add_action( 'wp_ajax_voiceqwen_update_avatar', 'voiceqwen_update_avatar' );

/**
 * AJAX: Get all available voices dynamically.
 */
function voiceqwen_get_voices() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    
    $voices_dir = plugin_dir_path( __FILE__ ) . 'assets/voices';
    $voices_url = plugin_dir_url( __FILE__ ) . 'assets/voices/';
    
    $voices = array();
    
    if ( file_exists( $voices_dir ) ) {
        $files = scandir( $voices_dir );
        
        // Use an array to track found IDs to avoid duplicates
        $found_ids = array();

        foreach ( $files as $file ) {
            if ( str_contains( $file, '-referencia.txt' ) ) {
                $id = str_replace( '-referencia.txt', '', $file );
                if ( in_array( $id, $found_ids ) ) continue;
                $found_ids[] = $id;

                // Human readable name
                $name = str_replace( '-', ' ', $id );
                $name = ucwords( $name );
                
                // Prefer custom avatar from upload_dir if it exists, else use assets
                $avatar_url = voiceqwen_get_avatar_url( $id );
                
                $voices[] = array(
                    'id'     => $id,
                    'name'   => $name,
                    'avatar' => $avatar_url
                );
            }
        }
    }
    
    wp_send_json_success( $voices );
}
add_action( 'wp_ajax_voiceqwen_get_voices', 'voiceqwen_get_voices' );

/**
 * AJAX: Upload a brand new voice.
 */
function voiceqwen_upload_voice() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'No autorizado' );
    }

    $name = sanitize_text_field( $_POST['name'] );
    $text_content = sanitize_textarea_field( $_POST['text'] );
    $id = sanitize_title( $name ); // Slug format

    if ( empty( $id ) || empty( $text_content ) ) {
        wp_send_json_error( 'Faltan campos obligatorios.' );
    }

    $voices_dir = plugin_dir_path( __FILE__ ) . 'assets/voices';
    if ( ! file_exists( $voices_dir ) ) {
        mkdir( $voices_dir, 0755, true );
    }

    // 1. Save Text
    file_put_contents( $voices_dir . '/' . $id . '-referencia.txt', $text_content );

    // 2. Save Audio (.wav)
    if ( isset( $_FILES['audio'] ) ) {
        move_uploaded_file( $_FILES['audio']['tmp_name'], $voices_dir . '/' . $id . '-sample.wav' );
    }

    // 3. Save Avatar (Store in the plugin assets directory for consistency)
    if ( isset( $_FILES['avatar'] ) ) {
        $ext = pathinfo( $_FILES['avatar']['name'], PATHINFO_EXTENSION );
        // We force it to assets for simplicity in scan
        move_uploaded_file( $_FILES['avatar']['tmp_name'], $voices_dir . '/' . $id . '.' . $ext );
    }

    wp_send_json_success( 'Voz guardada exitosamente.' );
}
add_action( 'wp_ajax_voiceqwen_upload_voice', 'voiceqwen_upload_voice' );

/**
 * Admin Menu Page
 */
function voiceqwen_admin_menu() {
    add_menu_page(
        'Audio Analysis',
        'Audio Analysis',
        'manage_options',
        'audio-analysis',
        'voiceqwen_render_analysis_page',
        'dashicons-performance',
        6
    );
}
add_action( 'admin_menu', 'voiceqwen_admin_menu' );

/**
 * Render Audio Analysis page.
 */
function voiceqwen_render_analysis_page() {
    $deps = VoiceQwen_Audio_Analyzer::check_dependencies();
    include plugin_dir_path( __FILE__ ) . 'templates/admin/audio-analysis-page.php';
}

/**
 * AJAX: Run Audio Analysis.
 */
function voiceqwen_analyze_audio() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    if ( ! file_exists( $user_dir ) ) {
        wp_send_json_error( 'No audio files found for this user.' );
    }

    $analyzer = new VoiceQwen_Audio_Analyzer();
    $files = scandir( $user_dir );
    $results = array();

    foreach ( $files as $file ) {
        if ( str_contains( $file, '.wav' ) ) {
            $results[] = $analyzer->analyze_file( $user_dir . '/' . $file );
        }
    }

    if ( empty( $results ) ) {
        wp_send_json_error( 'No WAV files found in your folder.' );
    }

    $summary = $analyzer->calculate_batch_summary( $results );

    wp_send_json_success( array(
        'results' => $results,
        'summary' => $summary
    ) );
}
add_action( 'wp_ajax_voiceqwen_analyze_audio', 'voiceqwen_analyze_audio' );

