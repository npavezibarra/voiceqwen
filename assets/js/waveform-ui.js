window.VoiceQwen = window.VoiceQwen || {};

jQuery(document).ready(function ($) {
    let wavesurfer = null, wsRegions = null, activeFileName = '', activeFileUrl = '', currentWaveUrl = '';
    let isDraggingMini = false, dragStartX, dragStartY, modalStartX, modalStartY;
    let autoSaveTimer = null, lastInsertTime = 0;

    window.VoiceQwen.loadWaveform = loadWaveform;
    window.VoiceQwen.handleInsertion = handleInsertion;

    function getAudioCtx() { return window.VoiceQwen.getAudioCtx(); }

    async function loadWaveform(url, filename, hasBackup = false, hasAutosave = false, autosaveUrl = '') {
        if (window.VoiceQwen.isLoadingWaveform) return;
        window.VoiceQwen.isLoadingWaveform = true;

        if (hasAutosave && autosaveUrl && confirm("¿Recuperar cambios automáticos?")) url = autosaveUrl;

        activeFileName = filename; activeFileUrl = url;
        $('#wave-viewer-empty, #wave-viewer-container').addClass('hidden');
        $('#wave-viewer-loading').removeClass('hidden');
        $('#waveform-title').text(filename);

        try {
            const ctx = getAudioCtx();
            const response = await fetch(url);
            const arrayBuffer = await response.arrayBuffer();
            window.VoiceQwen.activeAudioBuffer = await ctx.decodeAudioData(arrayBuffer);
        } catch (e) {
            window.VoiceQwen.isLoadingWaveform = false;
            alert("Error cargando audio: " + e.message);
            return;
        }

        if (wavesurfer) wavesurfer.destroy();
        wsRegions = WaveSurfer.Regions.create();
        wavesurfer = WaveSurfer.create({
            container: '#waveform', waveColor: '#00ffff', progressColor: '#ff00ff', height: 150,
            plugins: [wsRegions, WaveSurfer.Timeline.create({ container: '#wave-timeline' })]
        });

        wsRegions.enableDragSelection({ color: 'rgba(255, 0, 255, 0.2)' });
        wsRegions.on('region-updated', (region) => {
            $('#wave-region-delete').removeClass('hidden').off('click').on('click', () => {
                const newBuf = window.VoiceQwen.deleteSegment(window.VoiceQwen.activeAudioBuffer, region.start, region.end);
                if (newBuf) {
                    window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
                    window.VoiceQwen.activeAudioBuffer = newBuf;
                    updateWaveformPreview();
                }
            });
        });

        wavesurfer.on('ready', () => {
            window.VoiceQwen.isLoadingWaveform = false;
            $('#wave-viewer-loading').addClass('hidden');
            $('#wave-viewer-container').removeClass('hidden');
        });

        wavesurfer.load(url);
    }

    function updateWaveformPreview() {
        const blob = window.VoiceQwen.audioBufferToWav(window.VoiceQwen.activeAudioBuffer);
        currentWaveUrl = URL.createObjectURL(blob);
        wavesurfer.load(currentWaveUrl);
        $('#wave-save, #wave-undo').removeClass('hidden');
        requestAutoSave();
    }

    $(document).on('contextmenu', '#waveform', function(e) {
        e.preventDefault();
        if (!wavesurfer) return;
        
        const wrapper = wavesurfer.getWrapper();
        const rect = wrapper.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const duration = wavesurfer.getDuration();
        
        // Accurate time calculation regardless of zoom
        lastInsertTime = (x / rect.width) * duration;
        
        console.log("Waveform: Context menu at time", lastInsertTime);
        $('#wave-mini-modal').removeClass('hidden').css({ left: e.pageX + 'px', top: e.pageY + 'px' });
        
        // Populate mini-voices if empty
        const $miniSelector = $('#mini-voice-selector');
        if ($miniSelector.children().length === 0) {
            $.post(voiceqwen_ajax.url, { action: 'voiceqwen_get_voices', nonce: voiceqwen_ajax.nonce }, function(res) {
                if (res.success) {
                    $miniSelector.empty();
                    res.data.forEach((v, i) => {
                        $miniSelector.append(`
                            <label class="avatar-radio mini">
                                <input type="radio" name="mini-voice" value="${v.id}" ${i===0?'checked':''}>
                                <div class="avatar-circle" style="background-image: url('${v.avatar}');"></div>
                                <span class="avatar-name">${v.name}</span>
                            </label>
                        `);
                    });
                }
            });
        }
    });

    $(document).on('input', '#wave-zoom', function() {
        if (wavesurfer) wavesurfer.zoom(Number($(this).val()));
    });

    $(document).on('click', '#mini-generate-btn', async function() {
        const text = $('#mini-text').val();
        if (!text) return alert("Escribe un texto");

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_audio');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('voice', $('input[name="mini-voice"]:checked').val());
        formData.append('text', text);
        formData.append('source', 'mini');
        
        if (typeof window.VoiceQwen.getPath === 'function') {
            formData.append('folder', window.VoiceQwen.getPath());
        }
        
        $(this).prop('disabled', true).text('GENERATING...');
        $.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: formData, processData: false, contentType: false, success: (res) => {
            if (res.success) {
                window.VoiceQwen.startPolling();
                $('#mini-status').text('🚀 Generando...').css('color', 'blue');
            } else {
                alert("Error: " + res.data);
                $(this).prop('disabled', false).text('GENERATE & INSERT');
            }
        }});
    });

    async function handleInsertion(url) {
        try {
            console.log("Waveform: Loading insertion buffer from", url);
            const ctx = getAudioCtx();
            const res = await fetch(url + '?t=' + Date.now());
            const arrayBuf = await res.arrayBuffer();
            const buf = await ctx.decodeAudioData(arrayBuf);
            
            console.log("Waveform: Inserting clip at", lastInsertTime);
            const newBuf = await window.VoiceQwen.insertAudioAt(window.VoiceQwen.activeAudioBuffer, buf, lastInsertTime);
            
            window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
            window.VoiceQwen.activeAudioBuffer = newBuf;
            
            updateWaveformPreview();
            $('#wave-mini-modal').addClass('hidden');
            $('#mini-generate-btn').prop('disabled', false).text('GENERATE & INSERT');
            $('#mini-text').val('');
        } catch (e) { 
            console.error("Insertion error:", e);
            alert("Error al insertar: " + e.message); 
            $('#mini-generate-btn').prop('disabled', false).text('GENERATE & INSERT');
        }
    }

    $(document).on('click', '#wave-play-pause', () => wavesurfer && wavesurfer.playPause());
    $(document).on('click', '#wave-undo', function() {
        if (window.VoiceQwen.waveUndoStack.length) {
            window.VoiceQwen.activeAudioBuffer = window.VoiceQwen.waveUndoStack.pop();
            updateWaveformPreview();
            if (!window.VoiceQwen.waveUndoStack.length) $(this).addClass('hidden');
        }
    });

    $(document).on('click', '#wave-save', async function() {
        const $btn = $(this);
        $btn.prop('disabled', true).text('SAVING...');
        
        try {
            const blob = window.VoiceQwen.audioBufferToWav(window.VoiceQwen.activeAudioBuffer);
            const fd = new FormData();
            fd.append('action', 'voiceqwen_save_edited_audio');
            fd.append('nonce', voiceqwen_ajax.nonce);
            fd.append('filename', activeFileName);
            fd.append('audio', blob, activeFileName);

            $.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: fd, processData: false, contentType: false, success: (res) => {
                if (res.success) { 
                    alert("¡Cambios guardados con éxito!"); 
                    $btn.addClass('hidden'); 
                    deleteAutosave(activeFileName); 
                } else {
                    alert("Error: " + res.data);
                }
                $btn.prop('disabled', false).text('SAVE EDITS');
            }, error: () => {
                alert("Error de red.");
                $btn.prop('disabled', false).text('SAVE EDITS');
            }});
        } catch (e) {
            alert("Error procesando audio.");
            $btn.prop('disabled', false).text('SAVE EDITS');
        }
    });

    function requestAutoSave() {
        if (autoSaveTimer) clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(performAutoSave, 5000);
    }

    function performAutoSave() {
        if (!window.VoiceQwen.activeAudioBuffer || !activeFileName) return;
        try {
            const blob = window.VoiceQwen.audioBufferToWav(window.VoiceQwen.activeAudioBuffer);
            const fd = new FormData();
            fd.append('action', 'voiceqwen_save_autosave');
            fd.append('nonce', voiceqwen_ajax.nonce);
            fd.append('filename', activeFileName);
            fd.append('audio', blob, activeFileName + '-autosave.wav');
            $.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: fd, processData: false, contentType: false });
        } catch(e) {}
    }

    function deleteAutosave(filename) {
        $.post(voiceqwen_ajax.url, { action: 'voiceqwen_delete_autosave', nonce: voiceqwen_ajax.nonce, filename: filename });
    }

    $(document).on('click', '#mini-modal-close', () => $('#wave-mini-modal').addClass('hidden'));

    // Draggable Mini Modal
    let miniGrabX, miniGrabY;

    $(document).on('mousedown', '#wave-mini-modal .mini-header', function(e) {
        if ($(e.target).closest('button').length) return;
        isDraggingMini = true;
        const $modal = $('#wave-mini-modal');
        const offset = $modal.offset();
        miniGrabX = e.pageX - offset.left;
        miniGrabY = e.pageY - offset.top;
        $modal.css('cursor', 'grabbing');
        e.preventDefault();
    });

    $(document).on('mousemove', function(e) {
        if (isDraggingMini) {
            const $modal = $('#wave-mini-modal');
            $('#wave-mini-modal').css({
                left: (e.pageX - miniGrabX) + 'px',
                top: (e.pageY - miniGrabY) + 'px'
            });
        }
    });

    $(document).on('mouseup', function() {
        if (isDraggingMini) {
            isDraggingMini = false;
            $('#wave-mini-modal').css('cursor', '');
        }
    });
});
