<?php
define('WP_USE_THEMES', false);
require_once('/Users/nicolas/Local Sites/voiceqwen/app/public/wp-load.php');

$post_id = 1111; // I'll search for the post ID first
$books = get_posts(['post_type' => 'audiobook', 'title' => 'Insurrección']);
if ($books) {
    $book = $books[0];
    echo "Post ID: " . $book->ID . "\n";
    echo "Folder Name: " . get_post_meta($book->ID, '_vq_folder_name', true) . "\n";
    echo "Playlist: " . print_r(get_post_meta($book->ID, '_vq_playlist', true), true) . "\n";
} else {
    echo "Book not found\n";
}
