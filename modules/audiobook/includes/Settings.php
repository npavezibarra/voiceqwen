<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the plugin settings page for Audiobooks.
 */
class Settings
{
    /**
     * Initialize the settings.
     */
    public static function init(): void
    {
        add_action('admin_init', array(self::class, 'register_settings'));
        add_action('wp_ajax_vq_test_r2_connection', array(self::class, 'ajax_test_connection'));
        add_action('wp_ajax_voiceqwen_save_settings', array(self::class, 'ajax_save_settings'));
    }

    /**
     * AJAX handler to save settings from the frontend.
     */
    public static function ajax_save_settings(): void
    {
        check_ajax_referer('voiceqwen_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        update_option('voiceqwen_storage_mode', sanitize_text_field($_POST['mode']));
        update_option('voiceqwen_r2_account_id', sanitize_text_field($_POST['account_id']));
        update_option('voiceqwen_r2_access_key', sanitize_text_field($_POST['access_key']));
        update_option('voiceqwen_r2_secret_key', sanitize_text_field($_POST['secret_key']));
        update_option('voiceqwen_r2_bucket_name', sanitize_text_field($_POST['bucket_name']));

        wp_send_json_success('Settings saved');
    }

    /**
     * Register settings.
     */
    public static function register_settings(): void
    {
        register_setting('voiceqwen_settings_group', 'voiceqwen_storage_mode');
        register_setting('voiceqwen_settings_group', 'voiceqwen_r2_account_id');
        register_setting('voiceqwen_settings_group', 'voiceqwen_r2_access_key');
        register_setting('voiceqwen_settings_group', 'voiceqwen_r2_secret_key');
        register_setting('voiceqwen_settings_group', 'voiceqwen_r2_bucket_name');
    }

    /**
     * AJAX handler to test R2 connection.
     */
    public static function ajax_test_connection(): void
    {
        check_ajax_referer('vq_test_r2', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $r2 = new R2Client();
        $result = $r2->test_connection();

        if ($result === true) {
            wp_send_json_success('Connection successful!');
        } else {
            wp_send_json_error($result);
        }
    }
}
