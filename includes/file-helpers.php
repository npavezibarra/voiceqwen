<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Get avatar URL for a voice (custom or default).
 */
function voiceqwen_get_avatar_url( $voice ) {
    $upload_dir = wp_upload_dir();
    $custom_file = $upload_dir['basedir'] . '/voiceqwen/avatars/' . $voice . '_custom.png';
    if ( file_exists( $custom_file ) ) {
        return $upload_dir['baseurl'] . '/voiceqwen/avatars/' . $voice . '_custom.png?v=' . filemtime( $custom_file );
    }
    return plugin_dir_url( dirname( __FILE__ ) ) . 'assets/images/' . $voice . '.png';
}

/**
 * Recursive delete directory.
 */
function voiceqwen_recursive_delete( $dir ) {
    if ( ! file_exists( $dir ) ) return;
    $files = array_diff( scandir( $dir ), array( '.', '..' ) );
    foreach ( $files as $file ) {
        ( is_dir( "$dir/$file" ) ) ? voiceqwen_recursive_delete( "$dir/$file" ) : unlink( "$dir/$file" );
    }
    return rmdir( $dir );
}

/**
 * Get file tree.
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
            // Hide backup and autosave files from the list
            if ( str_ends_with( strtolower( $item ), '.original.wav' ) ) continue;
            if ( str_ends_with( strtolower( $item ), '-autosave.wav' ) ) continue;

            $has_backup = file_exists( $path . '.original.wav' );
            $has_autosave = file_exists( $path . '-autosave.wav' );
            
            // Encode the relative path correctly
            $rel_parts = explode('/', $rel);
            $encoded_rel = implode('/', array_map('rawurlencode', $rel_parts));
            $final_base_url = set_url_scheme($base_url);
            
            $autosave_url = $has_autosave ? $final_base_url . $encoded_rel . '-autosave.wav' : '';

            $tree[] = array(
                'type'         => 'file',
                'name'         => $item,
                'rel_path'     => $rel,
                'url'          => $final_base_url . $encoded_rel,
                'has_backup'   => $has_backup,
                'has_autosave' => $has_autosave,
                'autosave_url' => $autosave_url
            );
        }
    }

    // Sort by custom order
    if ( ! empty( $order ) ) {
        usort( $tree, function( $a, $b ) use ( $order ) {
            $idxA = array_search( $a['name'], $order );
            $idxB = array_search( $b['name'], $order );
            if ( $idxA === false ) $idxA = 999;
            if ( $idxB === false ) $idxB = 999;
            return $idxA - $idxB;
        } );
    }

    return $tree;
}

/**
 * Check if a background job is already running for the user.
 */
function voiceqwen_is_job_running( $user_dir ) {
    $status_file = $user_dir . '/status.json';
    if ( ! file_exists( $status_file ) ) return false;
    
    $data = json_decode( file_get_contents( $status_file ), true );
    return ( isset( $data['status'] ) && $data['status'] === 'processing' );
}
