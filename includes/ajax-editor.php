<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Resolve a user-scoped relative path safely.
 *
 * - Allows subfolders (e.g. "book-folder/chapter.wav")
 * - Denies traversal and Windows separators
 * - Forces ".wav" extension for consistency
 *
 * @return array{0:string,1:string} [$rel_path, $abs_path]
 */
function voiceqwen_resolve_user_wav_path( $user_dir, $filename_fallback = '', $rel_path_input = '' ) {
    $rel = is_string( $rel_path_input ) ? trim( $rel_path_input ) : '';
    $rel = ltrim( $rel, '/' );

    if ( $rel === '' ) {
        $rel = sanitize_file_name( $filename_fallback );
    }

    if ( $rel === '' ) {
        return array( '', '' );
    }

    if ( strpos( $rel, '..' ) !== false ) {
        return array( '', '' );
    }

    if ( strpos( $rel, '\\' ) !== false ) {
        return array( '', '' );
    }

    // Normalize accidental double slashes.
    $rel = preg_replace( '#/+#', '/', $rel );

    if ( ! str_ends_with( strtolower( $rel ), '.wav' ) ) {
        $rel .= '.wav';
    }

    $abs = rtrim( $user_dir, '/' ) . '/' . $rel;
    return array( $rel, $abs );
}

/**
 * AJAX Handler: Save edited audio Blob.
 */
function voiceqwen_save_edited_audio() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() || ! isset( $_FILES['audio'] ) ) wp_send_json_error( 'Navegador o usuario no autorizado' );

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    $rel_path_input = isset( $_POST['rel_path'] ) ? sanitize_text_field( $_POST['rel_path'] ) : '';
    list( $rel_path, $file_path ) = voiceqwen_resolve_user_wav_path( $user_dir, $filename, $rel_path_input );
    if ( empty( $file_path ) || empty( $rel_path ) ) wp_send_json_error( 'Ruta de archivo inválida' );

    $target_dir = dirname( $file_path );
    if ( ! file_exists( $target_dir ) ) {
        mkdir( $target_dir, 0755, true );
    }
    $backup_path = $file_path . '.original.wav';

    if ( file_exists( $file_path ) && ! file_exists( $backup_path ) ) copy( $file_path, $backup_path );

    if ( move_uploaded_file( $_FILES['audio']['tmp_name'], $file_path ) ) {
        $cleanup_files = isset( $_POST['cleanup_files'] ) ? json_decode( stripslashes( $_POST['cleanup_files'] ), true ) : array();
        if ( is_array( $cleanup_files ) ) {
            $target_base = basename( $file_path );
            foreach ( $cleanup_files as $cf ) {
                $cf = is_string( $cf ) ? trim( $cf ) : '';
                if ( $cf === '' ) continue;

                // Only allow deleting files that live alongside the edited target.
                $cf_base = basename( sanitize_text_field( $cf ) );
                if ( $cf_base === '' ) continue;
                if ( $cf_base === $target_base ) continue;

                $cf_path = $target_dir . '/' . $cf_base;
                if ( file_exists( $cf_path ) && is_file( $cf_path ) ) unlink( $cf_path );
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

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    $rel_path_input = isset( $_POST['rel_path'] ) ? sanitize_text_field( $_POST['rel_path'] ) : '';
    list( $rel_path, $file_path ) = voiceqwen_resolve_user_wav_path( $user_dir, $filename, $rel_path_input );
    if ( empty( $file_path ) || empty( $rel_path ) ) wp_send_json_error( 'Ruta de archivo inválida' );

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

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    $rel_path_input = isset( $_POST['rel_path'] ) ? sanitize_text_field( $_POST['rel_path'] ) : '';
    list( $rel_path, $file_path ) = voiceqwen_resolve_user_wav_path( $user_dir, $filename, $rel_path_input );
    if ( empty( $file_path ) || empty( $rel_path ) ) wp_send_json_error( 'Ruta de archivo inválida' );

    $target_dir = dirname( $file_path );
    if ( ! file_exists( $target_dir ) ) {
        mkdir( $target_dir, 0755, true );
    }

    $autosave_path = $file_path . '-autosave.wav';

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

    $filename = isset( $_POST['filename'] ) ? sanitize_file_name( $_POST['filename'] ) : '';

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    $rel_path_input = isset( $_POST['rel_path'] ) ? sanitize_text_field( $_POST['rel_path'] ) : '';
    list( $rel_path, $file_path ) = voiceqwen_resolve_user_wav_path( $user_dir, $filename, $rel_path_input );
    if ( empty( $file_path ) || empty( $rel_path ) ) wp_send_json_error( 'Ruta de archivo inválida' );

    $autosave_path = $file_path . '-autosave.wav';

    if ( file_exists( $autosave_path ) ) {
        unlink( $autosave_path );
        wp_send_json_success( 'Auto-save eliminado' );
    } else {
        wp_send_json_error( 'No existe auto-save' );
    }
}
add_action( 'wp_ajax_voiceqwen_delete_autosave', 'voiceqwen_delete_autosave' );
