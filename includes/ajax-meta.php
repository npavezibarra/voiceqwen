<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX: Update custom avatar.
 */
function voiceqwen_update_avatar() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'No autorizado' );

    $voice = sanitize_text_field( $_POST['voice'] );
    $image_data = $_POST['image']; 

    if ( empty( $voice ) || empty( $image_data ) ) wp_send_json_error( 'Datos incompletos' );

    if ( preg_match( '/^data:image\/(\w+);base64,/', $image_data, $type ) ) {
        $image_data = substr( $image_data, strpos( $image_data, ',' ) + 1 );
        $image_data = base64_decode( $image_data );
    } else {
        wp_send_json_error( 'Formato de imagen no válido' );
    }

    $upload_dir = wp_upload_dir();
    $avatar_dir = $upload_dir['basedir'] . '/voiceqwen/avatars';
    if ( ! file_exists( $avatar_dir ) ) mkdir( $avatar_dir, 0755, true );

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
    
    $voices_dir = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/voices';
    $voices = array();
    
    if ( file_exists( $voices_dir ) ) {
        $files = scandir( $voices_dir );
        $found_ids = array();
        foreach ( $files as $file ) {
            if ( strpos( $file, '-referencia.txt' ) !== false ) {
                $id = str_replace( '-referencia.txt', '', $file );
                if ( in_array( $id, $found_ids ) ) continue;
                $found_ids[] = $id;
                $name = ucwords( str_replace( '-', ' ', $id ) );
                $avatar_url = voiceqwen_get_avatar_url( $id );
                $voices[] = array( 'id' => $id, 'name' => $name, 'avatar' => $avatar_url );
            }
        }
    }
    wp_send_json_success($voices);
}
add_action( 'wp_ajax_voiceqwen_get_voices', 'voiceqwen_get_voices' );

/**
 * AJAX: Run Audio Analysis.
 */
function voiceqwen_analyze_audio() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'Unauthorized' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    if ( ! file_exists( $user_dir ) ) wp_send_json_error( 'No audio files found for this user.' );

    $analyzer = new VoiceQwen_Audio_Analyzer();
    $files = scandir( $user_dir );
    $results = array();
    foreach ( $files as $file ) {
        if ( strpos( $file, '.wav' ) !== false && !str_contains($file, '.original') && !str_contains($file, '-autosave')) {
            $results[] = $analyzer->analyze_file( $user_dir . '/' . $file );
        }
    }
    if ( empty( $results ) ) wp_send_json_error( 'No WAV files found in your folder.' );
    $summary = $analyzer->calculate_batch_summary( $results );
    wp_send_json_success( array( 'results' => $results, 'summary' => $summary ) );
}
add_action( 'wp_ajax_voiceqwen_analyze_audio', 'voiceqwen_analyze_audio' );
