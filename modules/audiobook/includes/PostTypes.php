<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles custom post type registration.
 */
class PostTypes
{
    /**
     * Initialize post types.
     */
    public static function init(): void
    {
        add_action('init', array(self::class, 'register_audiobook_post_type'));
    }

    /**
     * Register the 'audiobook' post type.
     * Note: We use 'audiobook' to maintain compatibility with existing VoiceQwen setup if any.
     */
    public static function register_audiobook_post_type(): void
    {
        $labels = array(
            'name'                  => _x('Audiobooks', 'Post type general name', 'voiceqwen'),
            'singular_name'         => _x('Audiobook', 'Post type singular name', 'voiceqwen'),
            'menu_name'             => _x('Audiobooks', 'Admin Menu text', 'voiceqwen'),
            'name_admin_bar'        => _x('Audiobook', 'Add New on Toolbar', 'voiceqwen'),
            'add_new'               => __('Add New', 'voiceqwen'),
            'add_new_item'          => __('Add New Audiobook', 'voiceqwen'),
            'new_item'              => __('New Audiobook', 'voiceqwen'),
            'edit_item'             => __('Edit Audiobook', 'voiceqwen'),
            'view_item'             => __('View Audiobook', 'voiceqwen'),
            'all_items'             => __('All Audiobooks', 'voiceqwen'),
            'search_items'          => __('Search Audiobooks', 'voiceqwen'),
            'not_found'             => __('No audiobooks found.', 'voiceqwen'),
            'not_found_in_trash'    => __('No audiobooks found in Trash.', 'voiceqwen'),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'audiobook'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'thumbnail'),
            'show_in_rest'       => true,
        );

        register_post_type('audiobook', $args);
    }
}
