jQuery(document).ready(function ($) {
    let pollingInterval = null;
    let currentJobSource = ''; 
    let currentJobFileUrl = '';

    window.VoiceQwen = window.VoiceQwen || {};

    // Expose functions
    window.VoiceQwen.startPolling = startPolling;
    window.VoiceQwen.checkBackgroundStatus = checkBackgroundStatus;
    window.VoiceQwen.loadVoices = loadVoices;

    // Generate Audio
    $('#generate-btn').on('click', function () {
        const activeTab = $('.vapor-tab.active').data('tab');
        const voice = $('input[name="voice"]:checked').val();
        const $btn = $(this);
        const $status = $('#status-msg');
        const stability = $('#tts-stability').val();

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_audio');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('voice', voice);
        formData.append('stability', stability);

        if (activeTab === 'textarea') {
            const text = $('#tts-text').val();
            if (!text) {
                $status.text('Error: Texto vacío').css('color', 'red');
                return;
            }
            formData.append('text', text);
        } else {
            const fileInput = $('#tts-file')[0];
            if (fileInput.files.length === 0) {
                $status.text('Error: Selecciona un archivo').css('color', 'red');
                return;
            }
            formData.append('file', fileInput.files[0]);
        }

        $btn.prop('disabled', true).text('Procesando...');
        $status.text('Iniciando proceso en segundo plano...').css('color', '#000');
        currentJobSource = 'create';
        window.VoiceQwen.currentJobSource = 'create';

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success && response.data.status === 'processing') {
                    $status.show().text('Generando audio...').css('color', '#0000ff');
                    $('#reset-status-btn').removeClass('hidden');
                    startPolling();
                } else {
                    $status.text('Error: ' + response.data).css('color', 'red');
                    $btn.prop('disabled', false).text('Generar Audio');
                }
            },
            error: function () {
                $status.text('Error de red').css('color', 'red');
                $btn.prop('disabled', false).text('Generar Audio');
            }
        });
    });

    // Generate Dialogue
    $('#generate-dialogue-btn').on('click', function () {
        const text = $('#dialogue-text').val();
        const $btn = $(this);
        const $status = $('#dialogue-status-msg');
        const stability = $('#dialogue-stability').val();

        if (!text) {
            $status.text('Error: Texto vacío').css('color', 'red');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_dialogue');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('text', text);
        formData.append('stability', stability);

        $btn.prop('disabled', true).text('Procesando...');
        $status.text('Iniciando diálogo en segundo plano...').css('color', '#000');
        currentJobSource = 'dialogue';
        window.VoiceQwen.currentJobSource = 'dialogue';

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success && response.data.status === 'processing') {
                    $status.show().html('<div style="color:blue; font-weight:bold;">🚀 SE HA INICIADO LA GENERACIÓN...</div>');
                    $('#reset-status-btn').removeClass('hidden');
                    startPolling();
                } else {
                    $status.show().html(`<div style="color:red; font-weight:bold;">⚠️ ERROR: ${response.data}</div>`);
                    $btn.prop('disabled', false).text('Generar Diálogo');
                }
            },
            error: function () {
                $status.show().html('<div style="color:red; font-weight:bold;">⚠️ Error de red.</div>');
                $btn.prop('disabled', false).text('Generar Diálogo');
            }
        });
    });

    function loadVoices() {
        const $selector = $('#dynamic-voice-selector');
        if (!$selector.length) return;

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_get_voices',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            if (response.success) {
                $selector.empty();
                const $chips = $('#dialogue-voice-chips');
                if ($chips.length) $chips.empty();

                response.data.forEach(function(voice, index) {
                    const checked = index === 0 ? 'checked' : '';
                    $selector.append(`
                        <label class="avatar-radio">
                            <input type="radio" name="voice" value="${voice.id}" ${checked}>
                            <div class="avatar-circle" data-voice="${voice.id}" style="background-image: url('${voice.avatar}');"></div>
                            <span class="avatar-name">${voice.name}</span>
                        </label>
                    `);

                    if ($chips.length) {
                        const $chip = $(`<button type="button" class="nav-btn" style="font-size: 14px; padding: 4px 10px; border-style: dashed; background: #fff; cursor: pointer;">[${voice.name}]</button>`);
                        $chip.on('click', function() {
                            const $textarea = $('#dialogue-text');
                            const val = $textarea.val();
                            const tagStart = `[${voice.name}]`, tagEnd = `[/${voice.name}]`;
                            const pos = $textarea[0].selectionStart, end = $textarea[0].selectionEnd;
                            $textarea.val(val.substring(0, pos) + tagStart + val.substring(pos, end) + tagEnd + val.substring(end));
                            $textarea.focus();
                            if (pos === end) $textarea[0].setSelectionRange(pos + tagStart.length, pos + tagStart.length);
                        });
                        $chips.append($chip);
                    }
                });

                $(document).trigger('voiceqwen_voices_loaded', [response.data]);
            }
        });
    }

    function startPolling() {
        currentJobSource = window.VoiceQwen.currentJobSource || '';
        currentJobFileUrl = ''; // Reset for new job
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(checkBackgroundStatus, 5000);
    }

    function checkBackgroundStatus() {
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_check_status',
            nonce: voiceqwen_ajax.nonce
        }, function (response) {
            let $btn = $('#generate-btn');
            let $reset = $('#reset-status-btn');
            
            if (window.VoiceQwen && window.VoiceQwen.currentJobSource) currentJobSource = window.VoiceQwen.currentJobSource;

            if (response.success && response.data.status === 'processing') {
                const details = response.data.details;
                const filename = details.filename || '';

                if (!currentJobSource) {
                    if (filename.startsWith('d-')) currentJobSource = 'dialogue';
                    else if (filename.startsWith('clip-')) currentJobSource = 'mini';
                    else if (filename.startsWith('m-')) currentJobSource = 'create';
                    else if (filename.includes('-')) currentJobSource = 'audiobook'; 
                    window.VoiceQwen.currentJobSource = currentJobSource;
                }

                if (filename && !currentJobFileUrl) {
                    currentJobFileUrl = voiceqwen_ajax.upload_url + '/' + voiceqwen_ajax.username + '/' + (details.folder ? details.folder + '/' : '') + filename;
                }

                renderStatusOverlay(details);
                $reset.removeClass('hidden');
                if (!pollingInterval) startPolling();
            } else {
                if (pollingInterval) { clearInterval(pollingInterval); pollingInterval = null; }
                handleJobCompletion(response);
            }
        });
    }

    function renderStatusOverlay(details) {
        const $targetStatus = $('#voiceqwen-global-status');
        const elapsed = Math.floor((Date.now() / 1000) - details.time);
        const timeStr = `${Math.floor(elapsed / 60)}m ${elapsed % 60}s`;
        
        let progressStr = "", statusColor = "#0000ff", statusEmoji = "⚡";

        if (details.stage === 'resting') {
            statusColor = "#00aa00"; statusEmoji = "🧘";
            progressStr = `<div style="color:${statusColor}; font-weight:bold;">${statusEmoji} SISTEMA DESCANSANDO...</div>`;
        } else if (details.stage === 'generating') {
            statusColor = "#ff00ff"; statusEmoji = "🚀";
            progressStr = `<div style="color:${statusColor}; font-weight:bold;">${statusEmoji} PROCESANDO SEGMENTO ${details.current} DE ${details.total}...</div>`;
        } else if (details.stage === 'concatenating') {
            statusColor = "#ffaa00"; statusEmoji = "📦";
            progressStr = `<div style="color:${statusColor}; font-weight:bold;">${statusEmoji} CONCATENANDO...</div>`;
        }

        const richHtml = `
            <div class="status-overlay-bg">
                <div class="status-overlay-content">
                    <div style="background:#0000ff; color:#fff; padding:5px 10px; font-weight:bold;">${statusEmoji} ESTADO DEL PROCESO</div>
                    <div style="border:2px solid #0000ff; padding:15px;">
                        <div style="color:${statusColor}; font-weight:bold;">${statusEmoji} ${(details.message || "").toUpperCase()}</div>
                        <div>${progressStr}</div>
                        <div style="margin-top:10px;"><b>Tiempo:</b> ${timeStr}</div>
                    </div>
                    <div id="overlay-controls" style="margin-top:20px;"></div>
                </div>
            </div>`;
        $targetStatus.show().html(richHtml);
        $('#reset-status-btn').detach().appendTo('#overlay-controls').removeClass('hidden');
    }

    function handleJobCompletion(response) {
        const $finalStatus = $('#voiceqwen-global-status');
        const details = response.data.details || {};
        const filename = details.filename || '';

        if (response.data.status === 'completed') {
            // Ensure we have the correct URL for the completed file
            if (filename && !currentJobFileUrl) {
                currentJobFileUrl = voiceqwen_ajax.upload_url + '/' + voiceqwen_ajax.username + '/' + (details.folder ? details.folder + '/' : '') + filename;
            }

            $finalStatus.show().html(`
                <div class="status-overlay-bg">
                    <div class="status-overlay-content" style="text-align:center; padding:20px;">
                        <div style="font-size:40px;">✅</div>
                        <div>¡Proceso finalizado!</div>
                        <button class="vapor-btn-main status-overlay-close-btn" style="width:100%; margin-top:15px;">CERRAR</button>
                    </div>
                </div>`);
            
            if (currentJobSource === 'mini' && typeof window.VoiceQwen.handleInsertion === 'function') {
                console.log("Generation: Triggering insertion for", currentJobFileUrl);
                window.VoiceQwen.handleInsertion(currentJobFileUrl);
            }
            if (typeof window.VoiceQwen.loadFiles === 'function') window.VoiceQwen.loadFiles();
        }
        $('.nav-btn, #generate-btn').prop('disabled', false);
        
        // Reset state for next job
        setTimeout(() => {
            currentJobFileUrl = '';
            currentJobSource = '';
        }, 100);
    }

    $(document).on('click', '#reset-status-btn', function () {
        if (!confirm('¿Cancelar proceso actual?')) return;
        $.post(voiceqwen_ajax.url, { action: 'voiceqwen_reset_status', nonce: voiceqwen_ajax.nonce }, function () {
            location.reload(); 
        });
    });

    $(document).on('click', '.status-overlay-close-btn', function() {
        $('#voiceqwen-global-status').hide().empty();
        $.post(voiceqwen_ajax.url, { action: 'voiceqwen_reset_status', nonce: voiceqwen_ajax.nonce });
    });

    $(document).on('input', '#tts-stability, #dialogue-stability', function() {
        $(this).next('.stability-val').text($(this).val());
    });
});
