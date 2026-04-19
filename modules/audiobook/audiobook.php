<?php
/**
 * Audiobook Module Entry Point
 */

// Register Custom Post Types
function voiceqwen_audiobook_register_cpts() {
    register_post_type('audiobook', array(
        'labels' => array('name' => 'Audiobooks', 'singular_name' => 'Audiobook'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'thumbnail'),
        'hierarchical' => false,
    ));

    register_post_type('audiobook_chapter', array(
        'labels' => array('name' => 'Capítulos', 'singular_name' => 'Capítulo'),
        'public' => false,
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'hierarchical' => false,
    ));
}
add_action('init', 'voiceqwen_audiobook_register_cpts');

/**
 * Render the Audiobook UI view.
 */
function voiceqwen_audiobook_render_ui() {
    $template = plugin_dir_path( __FILE__ ) . 'audiobook-ui.php';
    if ( file_exists( $template ) ) {
        include $template;
    }
}

// --- AJAX Handlers ---

function voiceqwen_audiobook_get_books() {
    check_ajax_referer('voiceqwen_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error();

    $books = get_posts(array(
        'post_type' => 'audiobook',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));

    $data = array();
    foreach ($books as $book) {
        $data[] = array(
            'id' => $book->ID,
            'title' => $book->post_title,
            'author' => get_post_meta($book->ID, '_audiobook_author', true)
        );
    }
    wp_send_json_success($data);
}
add_action('wp_ajax_voiceqwen_audiobook_get_books', 'voiceqwen_audiobook_get_books');

function voiceqwen_audiobook_create_book() {
    check_ajax_referer('voiceqwen_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error();

    $title = sanitize_text_field($_POST['title']);
    $author = sanitize_text_field($_POST['author']);

    if (empty($title)) wp_send_json_error('Título vacío');

    $book_id = wp_insert_post(array(
        'post_type' => 'audiobook',
        'post_title' => $title,
        'post_status' => 'publish'
    ));

    if ($book_id && !is_wp_error($book_id)) {
        update_post_meta($book_id, '_audiobook_author', $author);
        wp_send_json_success(array('id' => $book_id, 'title' => $title, 'author' => $author));
    } else {
        wp_send_json_error('Error al crear el libro');
    }
}
add_action('wp_ajax_voiceqwen_audiobook_create_book', 'voiceqwen_audiobook_create_book');

function voiceqwen_audiobook_get_chapters() {
    check_ajax_referer('voiceqwen_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error();

    $book_id = intval($_POST['book_id']);
    if (!$book_id) wp_send_json_error('ID de libro inválido');

    $chapters = get_posts(array(
        'post_type' => 'audiobook_chapter',
        'post_parent' => $book_id,
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ));

    $data = array();
    foreach ($chapters as $ch) {
        $data[] = array(
            'id' => $ch->ID,
            'title' => $ch->post_title,
            'content' => $ch->post_content
        );
    }
    wp_send_json_success($data);
}
add_action('wp_ajax_voiceqwen_audiobook_get_chapters', 'voiceqwen_audiobook_get_chapters');

function voiceqwen_audiobook_create_chapter() {
    check_ajax_referer('voiceqwen_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error();

    $book_id = intval($_POST['book_id']);
    $title = sanitize_text_field($_POST['title']);

    if (!$book_id || empty($title)) wp_send_json_error('Datos incompletos');

    $ch_id = wp_insert_post(array(
        'post_type' => 'audiobook_chapter',
        'post_parent' => $book_id,
        'post_title' => $title,
        'post_status' => 'publish'
    ));

    if ($ch_id) {
        wp_send_json_success(array('id' => $ch_id, 'title' => $title));
    } else {
        wp_send_json_error('Error al crear capítulo');
    }
}
add_action('wp_ajax_voiceqwen_audiobook_create_chapter', 'voiceqwen_audiobook_create_chapter');

function voiceqwen_audiobook_save_chapter() {
    check_ajax_referer('voiceqwen_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error();

    $ch_id = intval($_POST['chapter_id']);
    $content = wp_kses_post($_POST['content']);

    if (!$ch_id) wp_send_json_error('ID inválido');

    $updated = wp_update_post(array(
        'ID' => $ch_id,
        'post_content' => $content
    ));

    if ($updated) {
        wp_send_json_success('Capítulo guardado');
    } else {
        wp_send_json_error('Error al guardar');
    }
}
add_action('wp_ajax_voiceqwen_audiobook_save_chapter', 'voiceqwen_audiobook_save_chapter');
