/**
 * VoiceQwen Waveform - Core Engine
 */
window.VoiceQwen = window.VoiceQwen || {};

(function($) {
    let wavesurfer = null;
    let activeFileName = '', activeFileRelPath = '', activeFileUrl = '';
    let currentWaveUrl = '', activePostId = 0;
    let sessionTempClips = [];

    // Exports
    window.VoiceQwen.loadWaveform = loadWaveform;
    window.VoiceQwen.updateWaveformPreview = updateWaveformPreview;
    window.VoiceQwen.getWavesurfer = () => wavesurfer;

    function withCacheBuster(url, key = 't') {
        try {
            const u = new URL(url, window.location.href);
            u.searchParams.set(key, String(Date.now()));
            return u.toString();
        } catch (_) {
            const join = url.includes('?') ? '&' : '?';
            return url + join + key + '=' + Date.now();
        }
    }

    async function loadWaveform(url, filename, hasBackup = false, hasAutosave = false, autosaveUrl = '', relPath = '', postId = 0, textKey = '') {
        if (window.VoiceQwen.isLoadingWaveform) return;
        window.VoiceQwen.isLoadingWaveform = true;

        if (hasAutosave && autosaveUrl && confirm("¿Recuperar cambios automáticos?")) url = autosaveUrl;

        activeFileName = filename;
        activeFileRelPath = relPath || filename;
        activeFileUrl = url;
        activePostId = postId || 0;
        window.VoiceQwen.activePostId = activePostId;
        window.VoiceQwen.activeFileRelPath = activeFileRelPath;
        sessionTempClips = [];

        $('#wave-viewer-empty, #wave-viewer-container').addClass('hidden');
        $('#wave-viewer-loading').removeClass('hidden');
        $('#waveform-title').text(filename);

        // UI Setup
        $('#wave-sync-r2').toggleClass('hidden', !relPath);
        if (textKey) {
            $('#wave-view-text-btn').removeClass('hidden').attr('data-text-key', textKey).attr('data-post-id', postId);
        } else {
            $('#wave-view-text-btn').addClass('hidden').removeAttr('data-text-key').removeAttr('data-post-id');
        }

        $('#wave-viewer-container').removeClass('hidden').css({ visibility: 'hidden' });
        $('#waveform, #wave-timeline').empty();

        try {
            const ctx = window.VoiceQwen.getAudioCtx();
            const response = await fetch(withCacheBuster(url), { cache: 'no-store' });
            const arrayBuffer = await response.arrayBuffer();
            window.VoiceQwen.activeAudioBuffer = await ctx.decodeAudioData(arrayBuffer);
        } catch (e) {
            window.VoiceQwen.isLoadingWaveform = false;
            alert("Error cargando audio: " + e.message);
            $('#wave-viewer-container').addClass('hidden').css({ visibility: '' });
            return;
        }

        if (wavesurfer) wavesurfer.destroy();
        
        const wsRegions = WaveSurfer.Regions.create();
        window.VoiceQwen.wsRegions = wsRegions; // Export for regions module

        wavesurfer = WaveSurfer.create({
            container: '#waveform', waveColor: '#00ffff', progressColor: '#ff00ff', height: 150,
            plugins: [
                wsRegions, 
                WaveSurfer.Timeline.create({ 
                    container: '#wave-timeline',
                    height: 20,
                    style: { fontSize: '10px', color: '#888' }
                })
            ]
        });
        window.VoiceQwen.wavesurferInstance = wavesurfer;

        wavesurfer.on('ready', () => {
            window.VoiceQwen.isLoadingWaveform = false;
            $('#wave-viewer-loading').addClass('hidden');
            $('#wave-viewer-container').removeClass('hidden').css({ visibility: '' });
            setTimeout(() => wavesurfer && wavesurfer.zoom(Number($('#wave-zoom').val() || 10)), 0);
            $(document).trigger('voiceqwen_waveform_ready');
        });

        wavesurfer.load(withCacheBuster(url));
    }

    function updateWaveformPreview() {
        if (!wavesurfer) return;
        const blob = window.VoiceQwen.audioBufferToWav(window.VoiceQwen.activeAudioBuffer);
        currentWaveUrl = URL.createObjectURL(blob);
        if (window.VoiceQwen.wsRegions) window.VoiceQwen.wsRegions.clearRegions();
        
        wavesurfer.load(currentWaveUrl);
        $('#wave-save, #wave-undo').removeClass('hidden');
        if (activeFileRelPath) $('#wave-sync-r2').removeClass('hidden');
    }

    // Transport Listeners
    $(document).on('click', '#wave-play-pause', () => wavesurfer && wavesurfer.playPause());
    
    $(document).on('click', '#wave-undo', function() {
        if (window.VoiceQwen.waveUndoStack && window.VoiceQwen.waveUndoStack.length) {
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
            fd.append('rel_path', activeFileRelPath || activeFileName);
            fd.append('audio', blob, activeFileName);

            $.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: fd, processData: false, contentType: false, success: (res) => {
                if (res.success) { 
                    alert("¡Cambios guardados!"); 
                    $btn.addClass('hidden'); 
                } else alert("Error: " + res.data);
                $btn.prop('disabled', false).text('SAVE EDITS');
            }});
        } catch (e) {
            $btn.prop('disabled', false).text('SAVE EDITS');
        }
    });

})(jQuery);
