<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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

    if ( file_exists( $file_path ) && ! file_exists( $backup_path ) ) copy( $file_path, $backup_path );

    if ( move_uploaded_file( $_FILES['audio']['tmp_name'], $file_path ) ) {
        $cleanup_files = isset($_POST['cleanup_files']) ? json_decode(stripslashes($_POST['cleanup_files']), true) : array();
        if (is_array($cleanup_files)) {
            foreach ($cleanup_files as $cf) {
                $cf = sanitize_file_name($cf);
                if (empty($cf)) continue;
                $cf_path = $user_dir . '/' . $cf;
                if ($cf !== $filename && file_exists($cf_path) && is_file($cf_path)) unlink($cf_path);
            }
        }
        wp_send_json_success( array( 'message' => 'Ediciones guardadas correctamente', 'has_backup' => true ) );
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

/**
 * AJAX Handler: Save Auto-save Blob.
 */
function voiceqwen_save_autosave() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() || ! isset( $_FILES['audio'] ) ) wp_send_json_error( 'Error de sesión o archivo vacío' );

    $filename = sanitize_file_name( $_POST['filename'] );
    if ( empty( $filename ) ) wp_send_json_error( 'Nombre inválido' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    
    if ( ! str_ends_with( strtolower( $filename ), '.wav' ) ) $filename .= '.wav';
    $autosave_path = $user_dir . '/' . $filename . '-autosave.wav';

    if ( move_uploaded_file( $_FILES['audio']['tmp_name'], $autosave_path ) ) {
        wp_send_json_success( 'Auto-save guardado' );
    } else {
        wp_send_json_error( 'Error al guardar auto-save' );
    }
}
add_action( 'wp_ajax_voiceqwen_save_autosave', 'voiceqwen_save_autosave' );

/**
 * AJAX Handler: Delete Auto-save.
 */
function voiceqwen_delete_autosave() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $filename = sanitize_file_name( $_POST['filename'] );
    if ( empty( $filename ) ) wp_send_json_error();

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $autosave_path = $user_dir . '/' . $filename . '-autosave.wav';

    if ( file_exists( $autosave_path ) ) {
        unlink( $autosave_path );
        wp_send_json_success( 'Auto-save eliminado' );
    } else {
        wp_send_json_error( 'No existe auto-save' );
    }
}
add_action( 'wp_ajax_voiceqwen_delete_autosave', 'voiceqwen_delete_autosave' );
