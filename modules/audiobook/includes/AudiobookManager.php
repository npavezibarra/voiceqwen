<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

use VoiceQwen\Audiobook\AudiobookUI;
use VoiceQwen\Audiobook\AudiobookAJAX;

/**
 * Main Entry Point for Audiobook Module.
 * This class now delegates logic to specialized components.
 */
class AudiobookManager
{
    /**
     * Initialize the manager and register all AJAX hooks.
     */
    public static function init(): void
    {
        $actions = [
            'vq_create_book',
            'vq_update_book_author',
            'vq_save_playlist',
            'vq_upload_chapter',
            'vq_get_book_editor',
            'vq_upload_cover',
            'vq_upload_background',
            'vq_get_track_url',
            'vq_get_books',
            'vq_upload_local_chapter',
            'vq_sync_to_r2',
            'vq_export_audiobook',
            'vq_import_audiobook',
            'vq_download_from_r2',
            'vq_upload_text_chapters',
            'vq_get_chapter_text',
            'vq_save_chapter_text'
        ];

        foreach ($actions as $action) {
            // All AJAX handlers are now in the AudiobookAJAX class
            add_action('wp_ajax_' . $action, [AudiobookAJAX::class, $action]);
            
            // Public access for track URLs
            if ($action === 'vq_get_track_url') {
                add_action('wp_ajax_nopriv_' . $action, [AudiobookAJAX::class, $action]);
            }
        }
    }

    /**
     * Render the Manager UI (delegated to AudiobookUI).
     */
    public static function render_ui(): void
    {
        AudiobookUI::render_ui();
    }
}
