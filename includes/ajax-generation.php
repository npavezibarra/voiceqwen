<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
        $data = json_decode( file_get_contents( $status_file ), true );

        $pid_file = $user_dir . '/process.pid';
        if ( file_exists( $pid_file ) ) {
            $pid = trim( file_get_contents( $pid_file ) );
            if ( ! empty( $pid ) && is_numeric( $pid ) ) {
                exec( "pkill -9 -P $pid" ); 
                exec( "kill -9 $pid" );     
            }
            unlink( $pid_file );
        }

        $cmd_to_kill = sprintf( 'pkill -9 -f "status_file %s"', escapeshellarg( $status_file ) );
        exec( $cmd_to_kill );

        if ( isset( $data['filename'] ) ) {
            $chunks_dir = $user_dir . '/' . $data['filename'] . '.chunks';
            if ( file_exists( $chunks_dir ) ) {
                voiceqwen_recursive_delete( $chunks_dir );
            }
        }
        unlink( $status_file );
        wp_send_json_success( 'Proceso detenido y estado reiniciado' );
    } else {
        $cmd_generic = sprintf( 'pkill -f "voiceqwen/%s"', escapeshellarg( $username ) );
        exec( $cmd_generic );
        wp_send_json_error( 'No se detectó proceso activo, pero se forzó limpieza' );
    }
}
add_action( 'wp_ajax_voiceqwen_reset_status', 'voiceqwen_reset_status' );

/**
 * AJAX Handler to generate audio.
 */
function voiceqwen_generate_audio() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Usuario no identificado.' );

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
    $max_words = isset( $_POST['max_words'] ) ? intval( $_POST['max_words'] ) : 30;
    $pause_time = isset( $_POST['pause_time'] ) ? floatval( $_POST['pause_time'] ) : 0.5;

    if ( empty( $text ) ) wp_send_json_error( 'El texto está vacío.' );

    $text_clean = str_replace("\n", " ", $text);
    $sentences = preg_split('/(?<=[.!?])\s+/', $text_clean, -1, PREG_SPLIT_NO_EMPTY);
    $total_frags = 1;
    if (!empty($sentences)) {
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
    $folder = isset( $_POST['folder'] ) ? sanitize_text_field( $_POST['folder'] ) : '';
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    
    if ( ! empty( $folder ) ) {
        $folder = trim( $folder, '/' );
        if ( strpos( $folder, '..' ) !== false ) wp_send_json_error( 'Ruta inválida' );
        $target_dir = $user_dir . '/' . $folder;
    } else {
        $target_dir = $user_dir;
    }

    if ( ! file_exists( $target_dir ) ) mkdir( $target_dir, 0755, true );

    if ( voiceqwen_is_job_running( $user_dir ) ) {
        wp_send_json_error( 'YA HAY UN PROCESO EN CURSO. Por favor espera a que termine.' );
    }

    $audiobook_title = isset( $_POST['audiobook_title'] ) ? sanitize_file_name( $_POST['audiobook_title'] ) : '';
    $source = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
    
    if ( ! empty( $audiobook_title ) ) {
        $filename = $audiobook_title . '.wav';
    } else {
        $prefix = ($source === 'mini') ? 'clip-' : 'm-';
        $date_suffix = date( 'Ymd_His' );
        $filename = $prefix . sanitize_file_name( $voice ) . '-' . $date_suffix . '.wav';
    }

    $output_path = $target_dir . '/' . $filename;
    $log_path = $user_dir . '/last_job.log';
    $python_path = '/Users/nicolas/Local Sites/voiceqwen/app/public/qwet_test/.venv/bin/python3';
    if ( ! file_exists( $python_path ) ) $python_path = 'python3';
    $script_path = plugin_dir_path( dirname( __FILE__ ) ) . 'tts_cli.py';
    $status_file = $user_dir . '/status.json';

    $cmd = sprintf(
        '%s %s --text %s --voice %s --stability %s --max_words %s --pause_time %s --output %s --status_file %s',
        escapeshellarg( $python_path ), escapeshellarg( $script_path ),
        escapeshellarg( $text ), escapeshellarg( $voice ),
        escapeshellarg( $stability ), escapeshellarg( $max_words ), escapeshellarg( $pause_time ),
        escapeshellarg( $output_path ), escapeshellarg( $status_file )
    );

    file_put_contents( $status_file, json_encode( array( 
        'status'   => 'processing', 'filename' => $filename, 'time' => time(),
        'current'  => 1, 'total' => $total_frags, 'folder' => $folder,
        'message'  => 'Iniciando sistema y cargando modelo...'
    ) ) );

    $script_lines = array(
        '#!/bin/bash',
        'export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"',
        'export PYTHONIOENCODING=utf-8',
        'export TORCH_NUM_THREADS=4',
        sprintf( '%s > %s 2>&1', $cmd, escapeshellarg( $log_path ) )
    );
    
    $job_script = $user_dir . '/run_job.sh';
    file_put_contents( $job_script, implode("\n", $script_lines) . "\n" );
    chmod( $job_script, 0755 );

    $nohup_path = '/usr/bin/nohup';
    $bash_path = '/bin/bash';
    if (!file_exists($nohup_path)) $nohup_path = 'nohup';
    if (!file_exists($bash_path)) $bash_path = 'bash';

    $final_cmd = sprintf( "%s %s %s > /dev/null 2>&1 & echo $!", $nohup_path, $bash_path, escapeshellarg( $job_script ) );
    $pid = shell_exec( $final_cmd );
    
    if ( ! empty( $pid ) ) file_put_contents( $user_dir . '/process.pid', trim( $pid ) );

    wp_send_json_success( array(
        'status'   => 'processing',
        'message'  => 'Generando en segundo plano...',
        'filename' => $filename,
        'file_url' => $upload_dir['baseurl'] . '/voiceqwen/' . $username . '/' . $filename
    ) );
}
add_action( 'wp_ajax_voiceqwen_generate_audio', 'voiceqwen_generate_audio' );

/**
 * AJAX Handler to generate dialogue audio.
 */
function voiceqwen_generate_dialogue() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Usuario no identificado.' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;

    $text = sanitize_textarea_field( $_POST['text'] );
    $stability = isset( $_POST['stability'] ) ? floatval( $_POST['stability'] ) : 0.7;
    $max_words = isset( $_POST['max_words'] ) ? intval( $_POST['max_words'] ) : 30;
    $pause_time = isset( $_POST['pause_time'] ) ? floatval( $_POST['pause_time'] ) : 0.5;
    if ( empty( $text ) ) wp_send_json_error( 'El texto está vacío.' );

    $text_clean = str_replace("\n", " ", $text);
    $sentences = preg_split('/(?<=[.!?])\s+/', $text_clean, -1, PREG_SPLIT_NO_EMPTY);
    $total_frags = 1;
    if (!empty($sentences)) {
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
    if ( ! file_exists( $user_dir ) ) mkdir( $user_dir, 0755, true );

    if ( voiceqwen_is_job_running( $user_dir ) ) {
        wp_send_json_error( 'YA HAY UN PROCESO EN CURSO. Por favor espera a que termine o usa "CANCELAR / RESET" para limpiar el estado.' );
    }

    preg_match_all( '/\[(.*?)\]/', $text, $matches );
    $speakers = array_unique( $matches[1] );
    $initials = '';
    foreach ( $speakers as $speaker ) {
        if ( empty( trim( $speaker ) ) || strpos( $speaker, '/' ) === 0 ) continue;
        $clean_name = str_replace( ' ', '', $speaker );
        $initials .= strtolower( substr( $clean_name, 0, 2 ) );
    }
    if ( empty( $initials ) ) $initials = 'dlg';

    $date_suffix = date( 'Ymd_His' );
    $filename = 'd-' . sanitize_file_name( $initials ) . '-' . $date_suffix . '.wav';
    $output_path = $user_dir . '/' . $filename;
    $log_path = $user_dir . '/last_job.log';
    $python_path = '/Users/nicolas/Local Sites/voiceqwen/app/public/qwet_test/.venv/bin/python3';
    if ( ! file_exists( $python_path ) ) $python_path = 'python3';
    $script_path = plugin_dir_path( dirname( __FILE__ ) ) . 'tts_dialogue.py';
    $status_file = $user_dir . '/status.json';

    file_put_contents( $status_file, json_encode( array( 
        'status' => 'processing', 'filename' => $filename, 'time' => time(),
        'current' => 1, 'total' => $total_frags,
        'message' => 'Iniciando sistema y preparando diálogo...'
    ) ) );

    $cmd = sprintf(
        '%s %s --text %s --stability %s --max_words %s --pause_time %s --output %s --status_file %s',
        escapeshellarg( $python_path ), escapeshellarg( $script_path ),
        escapeshellarg( $text ), escapeshellarg( $stability ),
        escapeshellarg( $max_words ), escapeshellarg( $pause_time ),
        escapeshellarg( $output_path ), escapeshellarg( $status_file )
    );

    $script_lines = array(
        '#!/bin/bash',
        'export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"',
        'export PYTHONIOENCODING=utf-8',
        'export TORCH_NUM_THREADS=4',
        sprintf( '%s > %s 2>&1', $cmd, escapeshellarg( $log_path ) )
    );
    
    $job_script = $user_dir . '/run_job.sh';
    file_put_contents( $job_script, implode("\n", $script_lines) . "\n" );
    chmod( $job_script, 0755 );

    $nohup_path = '/usr/bin/nohup';
    $bash_path = '/bin/bash';
    if (!file_exists($nohup_path)) $nohup_path = 'nohup';
    if (!file_exists($bash_path)) $bash_path = 'bash';

    $final_cmd = sprintf( "%s %s %s > /dev/null 2>&1 & echo $!", $nohup_path, $bash_path, escapeshellarg( $job_script ) );
    $pid = shell_exec( $final_cmd );

    if ( ! empty( $pid ) ) file_put_contents( $user_dir . '/process.pid', trim( $pid ) );

    wp_send_json_success( array(
        'status' => 'processing',
        'message' => 'Generando diálogo...',
        'filename' => $filename,
        'file_url' => $upload_dir['baseurl'] . '/voiceqwen/' . $username . '/' . $filename
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
        $status = isset( $status_data['status'] ) ? $status_data['status'] : 'processing';
        wp_send_json_success( array( 'status' => $status, 'details' => $status_data ) );
    } else {
        wp_send_json_success( array( 'status' => 'idle' ) );
    }
}
add_action( 'wp_ajax_voiceqwen_check_status', 'voiceqwen_check_status' );
