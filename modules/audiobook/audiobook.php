<?php
/**
 * Audiobook Module Entry Point (Updated)
 */

if (!defined('ABSPATH')) {
    exit;
}

// 1. Load Composer Autoloader
$composer_autoload = plugin_dir_path(__FILE__) . '../../vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// 2. Load Internal Classes
require_once plugin_dir_path(__FILE__) . 'includes/R2Client.php';
require_once plugin_dir_path(__FILE__) . 'includes/PostTypes.php';
require_once plugin_dir_path(__FILE__) . 'includes/Settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/CoverOptimizer.php';
require_once plugin_dir_path(__FILE__) . 'includes/AudiobookManager.php';

// 3. Initialize Module
\VoiceQwen\Audiobook\PostTypes::init();
\VoiceQwen\Audiobook\Settings::init();
\VoiceQwen\Audiobook\AudiobookManager::init();

/**
 * Render the Audiobook UI view.
 * This function is called from the main voiceqwen shortcode.
 */
function voiceqwen_audiobook_render_ui() {
    \VoiceQwen\Audiobook\AudiobookManager::render_ui();
}

// --- Keep existing AJAX for compatibility if needed, or redirect them ---
// For now, we will use the new namespaced actions in our new JS.
