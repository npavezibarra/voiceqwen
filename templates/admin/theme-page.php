<?php
/**
 * Admin template for Theme Gallery / Selection.
 * Uses the $current_theme variable passed from the controller.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="wrap voiceqwen-admin-theme-page">
    <h1>VoiceQwen Theme Settings</h1>
    <p>Select the visual style for the VoiceQwen frontend interface.</p>

    <form method="post" action="">
        <?php wp_nonce_field( 'voiceqwen_theme_nonce' ); ?>
        
        <div class="theme-grid">
            <!-- Theme: 90ties -->
            <div class="theme-card <?php echo ($current_theme === '90ties') ? 'active' : ''; ?>">
                <div class="theme-preview theme-90ties-preview">
                    <div class="preview-header">
                        <div class="dots"><span></span><span></span><span></span></div>
                        <div class="title">VOICEQWEN</div>
                    </div>
                    <div class="preview-body">
                        <div class="preview-window">90'S DESIGN</div>
                    </div>
                </div>
                <div class="theme-info">
                    <h3>90ties (Default)</h3>
                    <p>A nostalgic, playful design inspired by early web and vaporwave aesthetics. Features neon colors and bold borders.</p>
                    <label class="theme-select-label">
                        <input type="radio" name="voiceqwen_theme" value="90ties" <?php checked( $current_theme, '90ties' ); ?>>
                        Select this theme
                    </label>
                </div>
            </div>

            <!-- Theme: Minimal -->
            <div class="theme-card <?php echo ($current_theme === 'minimal') ? 'active' : ''; ?>">
                <div class="theme-preview theme-minimal-preview">
                    <div class="preview-header">
                        <div class="title">VOICEQWEN</div>
                    </div>
                    <div class="preview-body">
                        <div class="preview-window">MINIMAL</div>
                    </div>
                </div>
                <div class="theme-info">
                    <h3>Minimal</h3>
                    <p>A clean, black-and-white aesthetic using Montserrat font, thin borders, and subtle drop shadows for a professional look.</p>
                    <label class="theme-select-label">
                        <input type="radio" name="voiceqwen_theme" value="minimal" <?php checked( $current_theme, 'minimal' ); ?>>
                        Select this theme
                    </label>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <input type="submit" name="voiceqwen_save_theme" class="button button-primary button-large" value="Save Changes">
        </div>
    </form>
</div>

<style>
.theme-grid {
    display: flex;
    gap: 30px;
    margin-top: 20px;
    flex-wrap: wrap;
}
.theme-card {
    width: 350px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    transition: all 0.2s;
    position: relative;
    border-radius: 4px;
    overflow: hidden;
}
.theme-card.active {
    border-color: #2271b1;
    box-shadow: 0 0 0 2px #2271b1;
}
.theme-card.disabled {
    opacity: 0.7;
    filter: grayscale(0.5);
}
.theme-preview {
    height: 180px;
    background: #f0f0f1;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    flex-direction: column;
}
.theme-90ties-preview {
    background: #ffeef2; /* Match the grid bg */
    padding: 15px;
}
.theme-90ties-preview .preview-header {
    background: #0000ff;
    padding: 5px 10px;
    display: flex;
    align-items: center;
    gap: 5px;
    border: 2px solid #000;
}
.theme-90ties-preview .dots span {
    width: 4px;
    height: 4px;
    background: #fff;
    border-radius: 50%;
    display: inline-block;
}
.theme-90ties-preview .title {
    color: #fff;
    font-size: 10px;
    font-weight: bold;
}
.theme-90ties-preview .preview-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.theme-90ties-preview .preview-window {
    background: #fff;
    border: 2px solid #0000ff;
    padding: 10px;
    color: #ff00ff;
    font-weight: bold;
    box-shadow: 4px 4px 0 rgba(0,0,255,0.2);
}
.theme-preview.placeholder {
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: #999;
}
.theme-info {
    padding: 20px;
}
.theme-info h3 {
    margin: 0 0 10px 0;
}
.theme-info p {
    color: #646970;
    font-size: 13px;
    margin-bottom: 20px;
}
.theme-select-label {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: bold;
    cursor: pointer;
}
.badge {
    background: #f0f0f1;
    color: #50575e;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: bold;
}
.theme-minimal-preview {
    background: #ffffff;
    padding: 15px;
    font-family: 'Montserrat', sans-serif;
}
.theme-minimal-preview .preview-header {
    background: #fff;
    padding: 5px 10px;
    display: flex;
    align-items: center;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}
.theme-minimal-preview .title {
    color: #000;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 1px;
}
.theme-minimal-preview .preview-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.theme-minimal-preview .preview-window {
    background: #fff;
    border: 1px solid #e2e8f0;
    padding: 10px;
    color: #000;
    font-weight: 500;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    border-radius: 4px;
}

</style>
