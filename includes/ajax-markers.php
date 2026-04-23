<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function voiceqwen_markers_resolve_wav_path( $user_dir, $rel_path_input ) {
    $rel = is_string( $rel_path_input ) ? trim( $rel_path_input ) : '';
    $rel = ltrim( $rel, '/' );
    if ( $rel === '' ) return array( '', '' );
    if ( strpos( $rel, '..' ) !== false ) return array( '', '' );
    if ( strpos( $rel, '\\' ) !== false ) return array( '', '' );
    $rel = preg_replace( '#/+#', '/', $rel );
    if ( ! str_ends_with( strtolower( $rel ), '.wav' ) ) $rel .= '.wav';
    $abs = rtrim( $user_dir, '/' ) . '/' . $rel;
    return array( $rel, $abs );
}

function voiceqwen_get_markers() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'No autorizado' );

    $rel_path_input = isset( $_POST['rel_path'] ) ? sanitize_text_field( $_POST['rel_path'] ) : '';
    if ( $rel_path_input === '' ) wp_send_json_error( 'Falta rel_path' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    list( $rel_path, $wav_path ) = voiceqwen_markers_resolve_wav_path( $user_dir, $rel_path_input );
    if ( $wav_path === '' ) wp_send_json_error( 'Ruta inválida' );

    $markers_path = $wav_path . '.markers.json';
    if ( ! file_exists( $markers_path ) ) {
        wp_send_json_success( array() );
    }

    $raw = file_get_contents( $markers_path );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) $data = array();

    wp_send_json_success( $data );
}
add_action( 'wp_ajax_voiceqwen_get_markers', 'voiceqwen_get_markers' );

function voiceqwen_save_markers() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error( 'No autorizado' );

    $rel_path_input = isset( $_POST['rel_path'] ) ? sanitize_text_field( $_POST['rel_path'] ) : '';
    if ( $rel_path_input === '' ) wp_send_json_error( 'Falta rel_path' );

    $markers_json = isset( $_POST['markers'] ) ? wp_unslash( $_POST['markers'] ) : '[]';
    $markers = json_decode( $markers_json, true );
    if ( ! is_array( $markers ) ) wp_send_json_error( 'Markers inválidos' );

    // Sanitize payload (limit size and shape).
    $sanitized = array();
    $count = 0;
    foreach ( $markers as $m ) {
        if ( $count >= 500 ) break;
        if ( ! is_array( $m ) ) continue;
        $id = isset( $m['id'] ) ? sanitize_text_field( (string) $m['id'] ) : '';
        $t = isset( $m['t'] ) ? floatval( $m['t'] ) : null;
        if ( $id === '' || $t === null || $t < 0 ) continue;
        $label = isset( $m['label'] ) ? sanitize_text_field( (string) $m['label'] ) : '';
        if ( strlen( $label ) > 80 ) $label = substr( $label, 0, 80 );

        $color = isset( $m['color'] ) ? sanitize_text_field( (string) $m['color'] ) : '';
        if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) $color = '';

        $row = array( 'id' => $id, 't' => $t );
        if ( $label !== '' ) $row['label'] = $label;
        if ( $color !== '' ) $row['color'] = $color;
        $sanitized[] = $row;
        $count++;
    }

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    list( $rel_path, $wav_path ) = voiceqwen_markers_resolve_wav_path( $user_dir, $rel_path_input );
    if ( $wav_path === '' ) wp_send_json_error( 'Ruta inválida' );

    $target_dir = dirname( $wav_path );
    if ( ! file_exists( $target_dir ) ) mkdir( $target_dir, 0755, true );

    $markers_path = $wav_path . '.markers.json';
    $ok = file_put_contents( $markers_path, json_encode( $sanitized ) );
    if ( $ok === false ) wp_send_json_error( 'No se pudo guardar' );

    wp_send_json_success( true );
}
add_action( 'wp_ajax_voiceqwen_save_markers', 'voiceqwen_save_markers' );
