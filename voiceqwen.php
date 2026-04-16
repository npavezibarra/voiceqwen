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
    wp_enqueue_script( 'wavesurfer', 'https://unpkg.com/wavesurfer.js@7', array(), null, true );
    wp_enqueue_script( 'wavesurfer-regions', 'https://unpkg.com/wavesurfer.js@7/dist/plugins/regions.min.js', array( 'wavesurfer' ), null, true );
    wp_enqueue_script( 'wavesurfer-timeline', 'https://unpkg.com/wavesurfer.js@7/dist/plugins/timeline.min.js', array( 'wavesurfer' ), null, true );
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

    $theme = get_option( 'voiceqwen_theme', '90ties' );
    $deco_text = ( $theme === '90ties' ) ? "90's" : "";

    ob_start();
    ?>
    <div class="voiceqwen-main-wrapper voiceqwen-theme-<?php echo esc_attr( $theme ); ?>">
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
                    <button class="nav-btn" data-view="waveform">WAVE VIEWER</button>
                    <button class="nav-btn" data-view="upload-voice">UPLOAD VOICE</button>
                </div>
            </div>
            
            <div class="vapor-body">
                <!-- Sidebar: File Viewer -->
                <div class="vapor-window sidebar">
                    <div class="vapor-window-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 10px;">
                        <div style="display: flex; align-items: center;">
                            <div class="vapor-dots"><span></span><span></span><span></span></div>
                            <div class="vapor-window-title">Mis Archivos</div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 5px;">
                            <button id="sidebar-new-folder-btn" class="nav-btn" style="background:#fff; border:2px solid #000; color:#000; padding: 2px 8px; font-size: 10px; height: 18px; line-height: 1;" title="Nueva Carpeta">📁+</button>
                            <button id="frontend-analyze-btn" class="nav-btn" style="width: auto; margin: 0; padding: 2px 8px; font-size: 10px; height: 18px; line-height: 1;">ANALYZE</button>
                        </div>
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

                    <div class="stability-control" style="margin: 15px 0; padding: 10px; background: rgba(255,0,255,0.05); border: 1px solid #ff00ff;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #ff00ff;">ESTABILIDAD VOCAL (TIMBRE): <span id="stability-val">0.7</span></label>
                        <input type="range" id="tts-stability" min="0.1" max="1.0" step="0.1" value="0.7" style="width: 100%; cursor: pointer;">
                        <div style="display: flex; justify-content: space-between; font-size: 11px; margin-top: 5px;">
                            <span>EXPRESIVO</span>
                            <span>ESTABLE (RECOMENDADO)</span>
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
                        <!-- Dialogue Help Container -->
                        <div class="vapor-window help-box" style="margin-bottom: 20px; border-style: dashed; background: rgba(0,0,255,0.02);">
                            <div class="vapor-window-header" style="height: 30px; padding: 5px 10px; background: rgba(0,0,255,0.1); border-bottom: 1px dashed #0000ff;">
                                <div class="vapor-window-title" style="font-size: 14px;">📖 GUÍA DE DIÁLOGOS</div>
                            </div>
                            <div style="padding: 15px; font-size: 16px; line-height: 1.4;">
                                <div style="margin-bottom: 10px;">
                                    <strong>FORMATO:</strong> Envuelve cada fragmento con el nombre del personaje. 
                                    <br><code style="background: #fff; border: 1px solid #0000ff; padding: 2px 5px; font-size: 14px;">[Nombre]Texto del diálogo...[/Nombre]</code>
                                </div>
                                <div style="margin-bottom: 15px;">
                                    <strong>TIP:</strong> Puedes hacer clic en los nombres de abajo para insertar la etiqueta automáticamente.
                                </div>
                                <div id="dialogue-voice-chips" style="display: flex; flex-wrap: wrap; gap: 8px;">
                                    <!-- Cargado dinámicamente -->
                                    <span style="opacity: 0.5;">Cargando personajes disponibles...</span>
                                </div>
                            </div>
                        </div>
                        
                        <textarea id="dialogue-text" placeholder="[Fernando]Hola Alodia, ¿cómo estás?[/Fernando] [Alodia Corral]¡Muy bien Fernando! Estamos al aire...[/Alodia Corral]" style="height: 200px;"></textarea>
                        
                        <div class="stability-control" style="margin: 15px 0; padding: 10px; background: rgba(255,0,255,0.05); border: 1px solid #ff00ff;">
                            <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #ff00ff;">ESTABILIDAD VOCAL (TIMBRE): <span id="dialogue-stability-val">0.7</span></label>
                            <input type="range" id="dialogue-stability" min="0.1" max="1.0" step="0.1" value="0.7" style="width: 100%; cursor: pointer;">
                            <div style="display: flex; justify-content: space-between; font-size: 11px; margin-top: 5px;">
                                <span>EXPRESIVO</span>
                                <span>ESTABLE</span>
                            </div>
                        </div>
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

                <!-- View 5: Waveform Viewer -->
                <div class="vapor-window main view-pane hidden" id="view-waveform">
                    <div class="vapor-window-header">
                        <div class="vapor-dots"><span></span><span></span><span></span></div>
                        <div class="vapor-window-title">WAVEFORM VISUALIZER</div>
                    </div>
                    <div class="vapor-pane">
                        <div id="wave-viewer-empty" style="text-align: center; padding: 50px; color: #0000ff; border: 2px dashed #0000ff; background: rgba(0,0,255,0.05);">
                            <div style="font-size: 40px; margin-bottom: 10px;">📡</div>
                            Selecciona un archivo del panel izquierdo para visualizar su frecuencia.
                        </div>
                        <div id="wave-viewer-loading" class="hidden" style="text-align: center; padding: 50px; color: #ff00ff;">
                            <div class="vapor-dots" style="justify-content: center; margin-bottom: 10px;"><span></span><span></span><span></span></div>
                            CALCULANDO ONDAS...
                        </div>
                        <div id="wave-viewer-container" class="hidden">
                            <div id="waveform-title" style="margin-bottom: 10px; font-weight: bold; color: #ff00ff; font-size: 20px;"></div>
                            <div id="waveform" style="background: #0d0d2b; border: 3px solid #0000ff; margin-bottom: 5px;"></div>
                            <div id="wave-timeline" style="margin-bottom: 15px; font-size: 10px; color: #888;"></div>
                            <div id="wave-controls" style="display: flex; gap: 15px; align-items: center; justify-content: center; padding: 10px; background: rgba(0,0,255,0.05); border: 2px solid #0000ff; flex-wrap: wrap;">
                                <button id="wave-play" type="button" class="nav-btn wave-control-btn" style="width: auto; margin: 0; min-width: 80px;">PLAY</button>
                                <button id="wave-pause" type="button" class="nav-btn wave-control-btn" style="width: auto; margin: 0; min-width: 80px;">PAUSE</button>
                                <button id="wave-stop" type="button" class="nav-btn wave-control-btn" style="width: auto; margin: 0; min-width: 80px; background: #888; color: #fff;">STOP</button>
                                <button id="wave-region-delete" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #ff00ff; color: #fff; font-weight: bold; border: 2px solid #000;">DELETE SELECTION</button>
                                <button id="wave-undo" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #ffaa00; color: #000; font-weight: bold; border: 2px solid #000;">UNDO (-1)</button>
                                <button id="wave-restore" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #ff4444; color: #fff; font-weight: bold; border: 2px solid #000;">RESTORE ORIGINAL</button>
                                <button id="wave-save" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #00ffff; color: #000; font-weight: bold; border: 2px solid #000;">SAVE EDITS</button>
                            </div>
                            <div style="margin-top: 15px; text-align: center; padding-bottom: 20px;">
                                <span style="font-size: 12px; font-weight: bold; margin-right: 15px;">ZOOM</span>
                                <input type="range" id="wave-zoom" min="10" max="1000" value="10" style="width: 80%; display: inline-block; vertical-align: middle;">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <?php if ( ! empty( $deco_text ) ) : ?>
                <div class="vapor-deco-text"><?php echo esc_html( $deco_text ); ?></div>
            <?php endif; ?>

        </div>
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
        // Also cleanup chunks if we are forcing a reset
        $data = json_decode( file_get_contents( $status_file ), true );
        if ( isset( $data['filename'] ) ) {
            $chunks_dir = $user_dir . '/' . $data['filename'] . '.chunks';
            if ( file_exists( $chunks_dir ) ) {
                voiceqwen_recursive_delete( $chunks_dir );
            }
        }
        unlink( $status_file );
        wp_send_json_success( 'Estado y fragmentos temporales reiniciados' );
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
    $stability = isset( $_POST['stability'] ) ? floatval( $_POST['stability'] ) : 0.7;

    if ( empty( $text ) ) {
        wp_send_json_error( 'El texto está vacío.' );
    }

    // Helper to calculate total fragments (sync logic with Python)
    $text_clean = str_replace("\n", " ", $text);
    $sentences = preg_split('/(?<=[.!?])\s+/', $text_clean, -1, PREG_SPLIT_NO_EMPTY);
    $total_frags = 1;
    if (!empty($sentences)) {
        $max_words = 15;
        $current_chunk_words = 0;
        $frag_count = 0;
        foreach ($sentences as $s) {
            $word_count = count(explode(' ', trim($s)));
            if ($current_chunk_words + $word_count <= $max_words || $current_chunk_words == 0) {
                $current_chunk_words += $word_count;
            } else {
                $frag_count++;
                $current_chunk_words = $word_count;
            }
        }
        $total_frags = $frag_count + 1;
    }

    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    if ( ! file_exists( $user_dir ) ) {
        mkdir( $user_dir, 0755, true );
    }

    if ( voiceqwen_is_job_running( $user_dir ) ) {
        wp_send_json_error( 'YA HAY UN PROCESO EN CURSO. Por favor espera a que termine o usa "CANCELAR / RESET" para limpiar el estado.' );
    }

    // Deterministic filename based on text hash to allow resumption
    $text_hash = md5( $text . $voice );
    $filename = 'tts_' . $text_hash . '.wav';
    $output_path = $user_dir . '/' . $filename;
    $log_path = $user_dir . '/last_job.log';

    // Use the absolute path to the venv python
    $python_path = '/Users/nicolas/Local Sites/voiceqwen/app/public/qwet_test/.venv/bin/python3';
    if ( ! file_exists( $python_path ) ) $python_path = 'python3';

    $script_path = plugin_dir_path( __FILE__ ) . 'tts_cli.py';
    
    $status_file = $user_dir . '/status.json';
    $cmd = sprintf(
        '%s %s --text %s --voice %s --stability %s --output %s --status_file %s',
        escapeshellarg( $python_path ),
        escapeshellarg( $script_path ),
        escapeshellarg( $text ),
        escapeshellarg( $voice ),
        escapeshellarg( $stability ),
        escapeshellarg( $output_path ),
        escapeshellarg( $status_file )
    );

    // Write status file to track background job
    file_put_contents( $status_file, json_encode( array( 
        'status'   => 'processing', 
        'filename' => $filename, 
        'time'     => time(),
        'current'  => 1,
        'total'    => $total_frags,
        'message'  => 'Iniciando sistema y cargando modelo (esto puede tardar 1 min)...'
    ) ) );

    // Build the background job script
    $script_lines = array(
        '#!/bin/bash',
        'export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"',
        'export PYTHONIOENCODING=utf-8',
        'export TORCH_NUM_THREADS=4',
        sprintf( '%s > %s 2>&1', $cmd, escapeshellarg( $log_path ) )
        // status.json managed by Python script
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
    $stability = isset( $_POST['stability'] ) ? floatval( $_POST['stability'] ) : 0.7;
    if ( empty( $text ) ) {
        wp_send_json_error( 'El texto está vacío.' );
    }

    // Helper to calculate total fragments (sync logic with Python)
    $text_clean = str_replace("\n", " ", $text);
    $sentences = preg_split('/(?<=[.!?])\s+/', $text_clean, -1, PREG_SPLIT_NO_EMPTY);
    $total_frags = 1;
    if (!empty($sentences)) {
        $max_words = 15;
        $current_chunk_words = 0;
        $frag_count = 0;
        foreach ($sentences as $s) {
            $word_count = count(explode(' ', trim($s)));
            if ($current_chunk_words + $word_count <= $max_words || $current_chunk_words == 0) {
                $current_chunk_words += $word_count;
            } else {
                $frag_count++;
                $current_chunk_words = $word_count;
            }
        }
        $total_frags = $frag_count + 1;
    }

    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    if ( ! file_exists( $user_dir ) ) {
        mkdir( $user_dir, 0755, true );
    }

    if ( voiceqwen_is_job_running( $user_dir ) ) {
        wp_send_json_error( 'YA HAY UN PROCESO EN CURSO. Por favor espera a que termine o usa "CANCELAR / RESET" para limpiar el estado.' );
    }

    $text_hash = md5( $text );
    $filename = 'dialogue_' . $text_hash . '.wav';
    $output_path = $user_dir . '/' . $filename;
    $log_path = $user_dir . '/last_job.log';

    $python_path = '/Users/nicolas/Local Sites/voiceqwen/app/public/qwet_test/.venv/bin/python3';
    if ( ! file_exists( $python_path ) ) $python_path = 'python3';

    $script_path = plugin_dir_path( __FILE__ ) . 'tts_dialogue.py';
    
    $status_file = $user_dir . '/status.json';
    file_put_contents( $status_file, json_encode( array( 
        'status'   => 'processing', 
        'filename' => $filename, 
        'time'     => time(),
        'current'  => 1,
        'total'    => $total_frags,
        'message'  => 'Iniciando sistema y preparando diálogo...'
    ) ) );

    $cmd = sprintf(
        '%s %s --text %s --stability %s --output %s --status_file %s',
        escapeshellarg( $python_path ),
        escapeshellarg( $script_path ),
        escapeshellarg( $text ),
        escapeshellarg( $stability ),
        escapeshellarg( $output_path ),
        escapeshellarg( $status_file )
    );

    $script_lines = array(
        '#!/bin/bash',
        'export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"',
        'export PYTHONIOENCODING=utf-8',
        'export TORCH_NUM_THREADS=4',
        sprintf( '%s > %s 2>&1', $cmd, escapeshellarg( $log_path ) )
        // status.json managed by Python script
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
        
        $is_completed = ( isset( $status_data['status'] ) && $status_data['status'] === 'completed' );
        
        wp_send_json_success( array( 
            'status'  => $is_completed ? 'completed' : 'processing', 
            'details' => $status_data 
        ) );

        // If completed, we could unlink but maybe wait for one last poll from frontend
        if ( $is_completed ) {
            // unlink( $status_file ); 
        }
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

    $old_rel = sanitize_text_field( $_POST['old_name'] ); // Actually a relative path now
    $new_name = sanitize_file_name( $_POST['new_name'] );

    if ( empty( $old_rel ) || empty( $new_name ) ) wp_send_json_error( 'Nombres inválidos' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    // Security
    if ( str_contains( $old_rel, '..' ) || str_contains( $new_name, '..' ) ) {
        wp_send_json_error( 'Acceso denegado' );
    }

    $old_path = $user_dir . '/' . $old_rel;
    
    // Construct new path: same directory as old path, but new filename
    $dir = dirname( $old_path );
    
    // Ensure extension for files
    if ( ! is_dir( $old_path ) && ! str_ends_with( strtolower( $new_name ), '.wav' ) ) {
        $new_name .= '.wav';
    }
    
    $new_path = $dir . '/' . $new_name;

    if ( file_exists( $old_path ) && ! file_exists( $new_path ) ) {
        if ( rename( $old_path, $new_path ) ) {
            wp_send_json_success( 'Renombrado correctamente' );
        } else {
            wp_send_json_error( 'Error al renombrar' );
        }
    } else {
        wp_send_json_error( 'El origen no existe o el destino ya existe' );
    }
}
add_action( 'wp_ajax_voiceqwen_rename_file', 'voiceqwen_rename_file' );

/**
 * Recursive File Listing
 */
function voiceqwen_get_file_tree( $dir, $base_url, $relative_path = '' ) {
    $tree = array();
    if ( ! file_exists( $dir ) || ! is_dir( $dir ) ) return $tree;
    
    $items = scandir( $dir );

    $order_file = $dir . '/.order.json';
    $order = array();
    if ( file_exists( $order_file ) ) {
        $order = json_decode( file_get_contents( $order_file ), true );
    }

    foreach ( $items as $item ) {
        if ( $item === '.' || $item === '..' || str_starts_with( $item, '.' ) ) continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        $rel = $relative_path ? $relative_path . '/' . $item : $item;

        if ( is_dir( $path ) ) {
            $tree[] = array(
                'type'     => 'folder',
                'name'     => $item,
                'rel_path' => $rel,
                'children' => voiceqwen_get_file_tree( $path, $base_url, $rel )
            );
        } elseif ( str_ends_with( strtolower( $item ), '.wav' ) ) {
            // Hide backup files from the list
            if ( str_ends_with( strtolower( $item ), '.original.wav' ) ) continue;

            $has_backup = file_exists( $path . '.original.wav' );
            $tree[] = array(
                'type'       => 'file',
                'name'       => $item,
                'rel_path'   => $rel,
                'url'        => $base_url . $rel,
                'has_backup' => $has_backup
            );
        }
    }

    // Sort by custom order
    if ( ! empty( $order ) ) {
        usort( $tree, function( $a, $b ) use ( $order ) {
            $idxA = array_search( $a['name'], $order );
            $idxB = array_search( $b['name'], $order );
            
            if ( $idxA === false && $idxB === false ) return 0;
            if ( $idxA === false ) return 1;
            if ( $idxB === false ) return -1;
            return $idxA - $idxB;
        });
    }

    return $tree;
}

function voiceqwen_save_order() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $rel_path = sanitize_text_field( $_POST['rel_path'] ); // Current folder rel path
    $order = $_POST['order']; // Array of names

    if ( ! is_array( $order ) ) wp_send_json_error( 'Invalid order format' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    $target_dir = empty( $rel_path ) ? $user_dir : $user_dir . '/' . $rel_path;
    
    if ( ! file_exists( $target_dir ) ) {
        wp_send_json_error( 'Directory not found' );
    }

    $order_file = $target_dir . '/.order.json';
    if ( file_put_contents( $order_file, json_encode( $order ) ) ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( 'Failed to save order' );
    }
}
add_action( 'wp_ajax_voiceqwen_save_order', 'voiceqwen_save_order' );

function voiceqwen_list_files() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $base_url = $upload_dir['baseurl'] . '/voiceqwen/' . $username . '/';

    if ( ! file_exists( $user_dir ) ) {
        mkdir( $user_dir, 0755, true );
    }
    
    $tree = voiceqwen_get_file_tree( $user_dir, $base_url );
    wp_send_json_success( $tree );
}
add_action( 'wp_ajax_voiceqwen_list_files', 'voiceqwen_list_files' );

/**
 * AJAX: Create Folder
 */
function voiceqwen_create_folder() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $folder = sanitize_file_name( $_POST['folder'] );
    if ( empty( $folder ) ) wp_send_json_error( 'Nombre inválido' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $new_dir = $user_dir . '/' . $folder;

    if ( ! file_exists( $new_dir ) ) {
        if ( mkdir( $new_dir, 0755, true ) ) {
            wp_send_json_success( 'Carpeta creada' );
        } else {
            wp_send_json_error( 'Error al crear carpeta' );
        }
    } else {
        wp_send_json_error( 'Ya existe' );
    }
}
add_action( 'wp_ajax_voiceqwen_create_folder', 'voiceqwen_create_folder' );

/**
 * AJAX: Move Item (File or Folder)
 */
function voiceqwen_move_item() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $item_rel = sanitize_text_field( $_POST['item_rel'] );
    $target_folder = sanitize_text_field( $_POST['target_folder'] ); // can be empty for root

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    // Security check: no path traversal
    if ( str_contains( $item_rel, '..' ) || str_contains( $target_folder, '..' ) ) {
        wp_send_json_error( 'Acceso denegado' );
    }

    $source_path = $user_dir . '/' . $item_rel;
    $filename = basename( $source_path );
    
    if ( empty( $target_folder ) ) {
        $dest_path = $user_dir . '/' . $filename;
    } else {
        $dest_path = $user_dir . '/' . $target_folder . '/' . $filename;
    }

    if ( file_exists( $source_path ) ) {
        if ( rename( $source_path, $dest_path ) ) {
            wp_send_json_success( 'Movido correctamente' );
        } else {
            wp_send_json_error( 'Error al mover' );
        }
    } else {
        wp_send_json_error( 'Archivo no encontrado' );
    }
}
add_action( 'wp_ajax_voiceqwen_move_item', 'voiceqwen_move_item' );

/**
 * AJAX: Upload OS File (Drag & Drop)
 */
function voiceqwen_upload_os_file() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    $target_folder = sanitize_text_field( $_POST['target_folder'] );
    if ( ! empty( $target_folder ) ) {
        // Prevent path traversal on target folder
        if ( str_contains( $target_folder, '..' ) ) {
            wp_send_json_error( 'Ruta inválida' );
        }
        $user_dir .= '/' . ltrim( $target_folder, '/' );
    }

    if ( ! file_exists( $user_dir ) ) {
        mkdir( $user_dir, 0755, true );
    }

    if ( isset( $_FILES['file'] ) ) {
        $file = $_FILES['file'];
        $filename = sanitize_file_name( $file['name'] );
        
        // Ensure it's a wav file
        if ( ! str_ends_with( strtolower( $filename ), '.wav' ) ) {
             wp_send_json_error( 'Solo se permiten archivos .wav' );
        }

        if ( $file['error'] !== UPLOAD_ERR_OK ) {
             wp_send_json_error( 'Error durante la subida' );
        }
        
        if ( move_uploaded_file( $file['tmp_name'], $user_dir . '/' . $filename ) ) {
             wp_send_json_success( 'Archivo subido' );
        } else {
             wp_send_json_error( 'Error al guardar el archivo' );
        }
    } else {
        wp_send_json_error( 'No se recibió ningún archivo' );
    }
}
add_action( 'wp_ajax_voiceqwen_upload_os_file', 'voiceqwen_upload_os_file' );

/**
 * AJAX: Delete file.
 */
function voiceqwen_delete_file() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );

    if ( ! is_user_logged_in() ) wp_send_json_error();

    $filename = sanitize_text_field( $_POST['filename'] ); // Can be a relative path
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $file_path = $user_dir . '/' . $filename;

    if ( file_exists( $file_path ) && str_contains( $filename, '..' ) === false ) {
        if ( is_dir( $file_path ) ) {
            voiceqwen_recursive_delete( $file_path );
            wp_send_json_success( 'Carpeta eliminada' );
        } else {
            unlink( $file_path );
            wp_send_json_success( 'Archivo eliminado' );
        }
    } else {
        wp_send_json_error( 'No se pudo encontrar el elemento' );
    }
}

function voiceqwen_recursive_delete($dir) {
    if (!file_exists($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? voiceqwen_recursive_delete("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
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
        'VoiceQwen',
        'VoiceQwen',
        'manage_options',
        'voiceqwen',
        'voiceqwen_render_theme_page',
        'dashicons-microphone',
        6
    );

    add_submenu_page(
        'voiceqwen',
        'Theme Settings',
        'Theme',
        'manage_options',
        'voiceqwen',
        'voiceqwen_render_theme_page'
    );

    add_submenu_page(
        'voiceqwen',
        'Audio Analysis',
        'Audio Analysis',
        'manage_options',
        'audio-analysis',
        'voiceqwen_render_analysis_page'
    );
}
add_action( 'admin_menu', 'voiceqwen_admin_menu' );

/**
 * Render Theme selection page.
 */
function voiceqwen_render_theme_page() {
    // Handle form submission
    if ( isset( $_POST['voiceqwen_save_theme'] ) && check_admin_referer( 'voiceqwen_theme_nonce' ) ) {
        $new_theme = sanitize_text_field( $_POST['voiceqwen_theme'] );
        update_option( 'voiceqwen_theme', $new_theme );
        echo '<div class="updated"><p>Theme updated successfully!</p></div>';
    }

    $current_theme = get_option( 'voiceqwen_theme', '90ties' );
    include plugin_dir_path( __FILE__ ) . 'templates/admin/theme-page.php';
}

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

/**
 * AJAX Handler: Save edited audio Blob.
 */
function voiceqwen_save_edited_audio() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() || ! isset( $_FILES['audio'] ) ) wp_send_json_error( 'Navegador o usuario no autorizado' );

    $filename = sanitize_file_name( $_POST['filename'] );
    if ( empty( $filename ) ) wp_send_json_error( 'Nombre de archivo inválido' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $file_path = $user_dir . '/' . $filename;
    $backup_path = $file_path . '.original.wav';

    // Create a backup only if it doesn't exist (preserve the VERY first generation)
    if ( file_exists( $file_path ) && ! file_exists( $backup_path ) ) {
        copy( $file_path, $backup_path );
    }

    if ( move_uploaded_file( $_FILES['audio']['tmp_name'], $file_path ) ) {
        wp_send_json_success( array(
            'message' => 'Ediciones guardadas correctamente',
            'has_backup' => true
        ) );
    } else {
        wp_send_json_error( 'Error al guardar las ediciones' );
    }
}
add_action( 'wp_ajax_voiceqwen_save_edited_audio', 'voiceqwen_save_edited_audio' );

/**
 * AJAX Handler: Restore original file.
 */
function voiceqwen_restore_original() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'No autorizado' );

    $filename = sanitize_file_name( $_POST['filename'] );
    if ( empty( $filename ) ) wp_send_json_error( 'Nombre inválido' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $file_path = $user_dir . '/' . $filename;
    $backup_path = $file_path . '.original.wav';

    if ( file_exists( $backup_path ) ) {
        if ( copy( $backup_path, $file_path ) ) {
            wp_send_json_success( 'Restaurado correctamente' );
        } else {
            wp_send_json_error( 'Error al copiar el backup' );
        }
    } else {
        wp_send_json_error( 'No existe backup para este archivo' );
    }
}
add_action( 'wp_ajax_voiceqwen_restore_original', 'voiceqwen_restore_original' );

