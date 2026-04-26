<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX Handler to rename a file.
 */
function voiceqwen_rename_file() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $old_rel = sanitize_text_field( $_POST['old_name'] );
    $new_name = sanitize_file_name( $_POST['new_name'] );

    if ( empty( $old_rel ) || empty( $new_name ) ) wp_send_json_error( 'Nombres inválidos' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    if ( strpos( $old_rel, '..' ) !== false || strpos( $new_name, '..' ) !== false ) {
        wp_send_json_error( 'Acceso denegado' );
    }

    $old_path = $user_dir . '/' . $old_rel;
    $dir = dirname( $old_path );
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
 * AJAX: Save Order
 */
function voiceqwen_save_order() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $rel_path = sanitize_text_field( $_POST['rel_path'] );
    $order = $_POST['order'];

    if ( ! is_array( $order ) ) wp_send_json_error( 'Invalid order format' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    $target_dir = empty( $rel_path ) ? $user_dir : $user_dir . '/' . $rel_path;
    
    if ( ! file_exists( $target_dir ) ) wp_send_json_error( 'Directory not found' );

    $order_file = $target_dir . '/.order.json';
    if ( file_put_contents( $order_file, json_encode( $order ) ) ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( 'Failed to save order' );
    }
}
add_action( 'wp_ajax_voiceqwen_save_order', 'voiceqwen_save_order' );

/**
 * AJAX: List Files
 */
function voiceqwen_list_files() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $base_url = $upload_dir['baseurl'] . '/voiceqwen/' . $username . '/';

    if ( ! file_exists( $user_dir ) ) mkdir( $user_dir, 0755, true );
    
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
 * AJAX: Move Item
 */
function voiceqwen_move_item() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $item_rel = sanitize_text_field( $_POST['item_rel'] );
    $target_folder = sanitize_text_field( $_POST['target_folder'] );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;

    if ( strpos( $item_rel, '..' ) !== false || strpos( $target_folder, '..' ) !== false ) {
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
 * AJAX: Upload OS File
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
        if ( strpos( $target_folder, '..' ) !== false ) wp_send_json_error( 'Ruta inválida' );
        $user_dir .= '/' . ltrim( $target_folder, '/' );
    }

    if ( ! file_exists( $user_dir ) ) mkdir( $user_dir, 0755, true );

    if ( isset( $_FILES['file'] ) ) {
        $file = $_FILES['file'];
        $filename = sanitize_file_name( $file['name'] );
        if ( ! str_ends_with( strtolower( $filename ), '.wav' ) ) wp_send_json_error( 'Solo se permiten archivos .wav' );
        if ( $file['error'] !== UPLOAD_ERR_OK ) wp_send_json_error( 'Error durante la subida' );
        
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
 * AJAX: Delete file
 */
function voiceqwen_delete_file() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $filename = sanitize_text_field( $_POST['filename'] );
    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $file_path = $user_dir . '/' . $filename;

    if ( file_exists( $file_path ) && strpos( $filename, '..' ) === false ) {
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
add_action( 'wp_ajax_voiceqwen_delete_file', 'voiceqwen_delete_file' );

/**
 * AJAX: Send a local file to an Audiobook (Uploads to R2 and adds to playlist)
 */
function voiceqwen_send_to_audiobook() {
    check_ajax_referer( 'voiceqwen_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) wp_send_json_error();

    $item_rel = sanitize_text_field( $_POST['item_rel'] );
    $book_id = intval( $_POST['book_id'] );

    if ( ! $book_id ) wp_send_json_error( 'ID de libro inválido' );

    $current_user = wp_get_current_user();
    $username = $current_user->user_login;
    $upload_dir = wp_upload_dir();
    $user_dir = $upload_dir['basedir'] . '/voiceqwen/' . $username;
    $file_path = $user_dir . '/' . $item_rel;

    if ( ! file_exists( $file_path ) ) wp_send_json_error( 'Archivo no encontrado' );

    // Prepare for R2 upload
    $r2 = new \VoiceQwen\Audiobook\R2Client();
    $book = get_post( $book_id );
    if ( ! $book ) wp_send_json_error( 'Libro no encontrado' );

    $book_title = sanitize_file_name( $book->post_title );
    $filename = basename( $file_path );
    $r2_key = $book_title . '/' . $filename;

    $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    $mime_type = ( $ext === 'mp3' ) ? 'audio/mpeg' : 'audio/wav';

    if ( $r2->upload_object( $file_path, $r2_key, $mime_type ) ) {
        // Add to playlist
        $playlist = get_post_meta( $book_id, '_vq_playlist', true );
        $playlist = is_array( $playlist ) ? $playlist : ( json_decode( $playlist, true ) ?: [] );
        
        $duration = '00:00';
        if (class_exists('\VoiceQwen\Audiobook\AudiobookManager')) {
            $duration = \VoiceQwen\Audiobook\AudiobookManager::get_wav_duration_formatted($file_path);
        }

        $playlist[] = array(
            'id'       => uniqid(),
            'title'    => pathinfo( $filename, PATHINFO_FILENAME ),
            'key'      => $r2_key,
            'duration' => $duration
        );
        
        update_post_meta( $book_id, '_vq_playlist', $playlist );
        wp_send_json_success( 'Archivo enviado al audiobook y subido a R2' );
    } else {
        wp_send_json_error( 'Error al subir a Cloudflare R2. Verifica la configuración.' );
    }
}
add_action( 'wp_ajax_voiceqwen_send_to_audiobook', 'voiceqwen_send_to_audiobook' );
