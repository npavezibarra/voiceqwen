<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="vapor-window main view-pane hidden" id="view-settings">
    <div class="vapor-window-header">
        <div class="vapor-dots"><span></span><span></span><span></span></div>
        <div class="vapor-window-title">SYSTEM CONFIGURATION</div>
    </div>
    
    <div style="padding: 30px; color: #fff;">
        <h3 style="color: #00ffff; text-shadow: 2px 2px #ff00ff; margin-bottom: 25px;">CLOUDFLARE R2 STORAGE</h3>
        
        <div class="settings-grid" style="display: grid; gap: 20px; max-width: 600px;">
            <div class="form-group">
                <label style="color: #ff00ff; font-weight: bold; display: block; margin-bottom: 8px;">STORAGE MODE</label>
                <?php $mode = get_option('voiceqwen_storage_mode', 'local'); ?>
                <select id="vq-settings-mode" class="vapor-input" style="width: 100%; background: #000; border: 1px solid #00ffff; color: #fff; padding: 8px;">
                    <option value="local" <?php selected($mode, 'local'); ?>>LOCAL STORAGE</option>
                    <option value="r2" <?php selected($mode, 'r2'); ?>>CLOUDFLARE R2</option>
                </select>
            </div>

            <div class="r2-only-fields" style="<?php echo $mode === 'local' ? 'display:none;' : ''; ?>">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #ff00ff; font-weight: bold; display: block; margin-bottom: 8px;">ACCOUNT ID</label>
                    <input type="text" id="vq-settings-r2-account" value="<?php echo esc_attr(get_option('voiceqwen_r2_account_id')); ?>" class="vapor-input" style="width: 100%; background: #000; border: 1px solid #00ffff; color: #fff; padding: 8px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #ff00ff; font-weight: bold; display: block; margin-bottom: 8px;">ACCESS KEY</label>
                    <input type="text" id="vq-settings-r2-access" value="<?php echo esc_attr(get_option('voiceqwen_r2_access_key')); ?>" class="vapor-input" style="width: 100%; background: #000; border: 1px solid #00ffff; color: #fff; padding: 8px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #ff00ff; font-weight: bold; display: block; margin-bottom: 8px;">SECRET KEY</label>
                    <input type="password" id="vq-settings-r2-secret" value="<?php echo esc_attr(get_option('voiceqwen_r2_secret_key')); ?>" class="vapor-input" style="width: 100%; background: #000; border: 1px solid #00ffff; color: #fff; padding: 8px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #ff00ff; font-weight: bold; display: block; margin-bottom: 8px;">BUCKET NAME</label>
                    <input type="text" id="vq-settings-r2-bucket" value="<?php echo esc_attr(get_option('voiceqwen_r2_bucket_name')); ?>" class="vapor-input" style="width: 100%; background: #000; border: 1px solid #00ffff; color: #fff; padding: 8px;">
                </div>
            </div>

            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button id="vq-save-settings-btn" class="vapor-btn-main" style="flex-grow: 1;">SAVE CONFIGURATION</button>
                <button id="vq-test-r2-btn" class="nav-btn" style="width: auto; padding: 0 20px; <?php echo $mode === 'local' ? 'display:none;' : ''; ?>">TEST CONNECTION</button>
            </div>
            <div id="vq-settings-status" style="margin-top: 10px; font-size: 12px;"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#vq-settings-mode').on('change', function() {
        if ($(this).val() === 'r2') {
            $('.r2-only-fields').slideDown();
            $('#vq-test-r2-btn').fadeIn();
        } else {
            $('.r2-only-fields').slideUp();
            $('#vq-test-r2-btn').fadeOut();
        }
    });

    $('#vq-save-settings-btn').on('click', function() {
        const btn = $(this);
        const status = $('#vq-settings-status');
        btn.prop('disabled', true).text('SAVING...');
        
        const data = {
            action: 'voiceqwen_save_settings',
            nonce: voiceqwen_ajax.nonce,
            mode: $('#vq-settings-mode').val(),
            account_id: $('#vq-settings-r2-account').val(),
            access_key: $('#vq-settings-r2-access').val(),
            secret_key: $('#vq-settings-r2-secret').val(),
            bucket_name: $('#vq-settings-r2-bucket').val()
        };

        $.post(voiceqwen_ajax.url, data, function(response) {
            btn.prop('disabled', false).text('SAVE CONFIGURATION');
            if (response.success) {
                status.text('✓ Configuración guardada correctamente.').css('color', '#00ffff');
            } else {
                status.text('✗ Error: ' + response.data).css('color', '#ff00ff');
            }
        });
    });

    $('#vq-test-r2-btn').on('click', function() {
        const btn = $(this);
        const status = $('#vq-settings-status');
        btn.prop('disabled', true).text('TESTING...');
        
        $.post(voiceqwen_ajax.url, {
            action: 'vq_test_r2_connection',
            nonce: '<?php echo wp_create_nonce("vq_test_r2"); ?>'
        }, function(response) {
            btn.prop('disabled', false).text('TEST CONNECTION');
            if (response.success) {
                status.text('✓ ' + response.data).css('color', '#00ffff');
            } else {
                status.text('✗ Error: ' + response.data).css('color', '#ff00ff');
            }
        });
    });
});
</script>
