window.VoiceQwen = window.VoiceQwen || {};

jQuery(document).ready(function ($) {
    let wavesurfer = null, wsRegions = null, activeFileName = '', activeFileRelPath = '', activeFileUrl = '', currentWaveUrl = '', activePostId = 0;
    let isDraggingMini = false, dragStartX, dragStartY, modalStartX, modalStartY;
    let autoSaveTimer = null, lastInsertTime = 0;
    let sessionTempClips = [];
    let lastPointer = { clientX: 0, clientY: 0 };
    let activeRegion = null;
    let pendingReplaceRange = null;

    window.VoiceQwen.loadWaveform = loadWaveform;
    window.VoiceQwen.handleInsertion = handleInsertion;
    window.VoiceQwen.openAddSpeechAt = openAddSpeechAt;
    window.VoiceQwen.updateWaveformPreview = updateWaveformPreview;

    function getAudioCtx() { return window.VoiceQwen.getAudioCtx(); }

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

    async function loadWaveform(url, filename, hasBackup = false, hasAutosave = false, autosaveUrl = '', relPath = '', postId = 0) {
        if (window.VoiceQwen.isLoadingWaveform) return;
        window.VoiceQwen.isLoadingWaveform = true;

        if (hasAutosave && autosaveUrl && confirm("¿Recuperar cambios automáticos?")) url = autosaveUrl;

        activeFileRelPath = relPath || filename;
        activeFileUrl = url;
        activePostId = postId || 0;
        window.VoiceQwen.activeFileRelPath = activeFileRelPath;
        sessionTempClips = [];
        $('#wave-viewer-empty, #wave-viewer-container').addClass('hidden');
        $('#wave-viewer-loading').removeClass('hidden');
        $('#waveform-title').text(filename);

        if (relPath) {
            $('#wave-sync-r2').removeClass('hidden');
        } else {
            $('#wave-sync-r2').addClass('hidden');
        }
        // Timeline needs real layout width at init time. Keep the container in the flow but hidden.
        $('#wave-viewer-container').removeClass('hidden').css({ visibility: 'hidden' });
        $('#waveform').empty();
        $('#wave-timeline').empty();

        try {
            const ctx = getAudioCtx();
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
        wsRegions = WaveSurfer.Regions.create();
        wavesurfer = WaveSurfer.create({
            container: '#waveform', waveColor: '#00ffff', progressColor: '#ff00ff', height: 150,
            plugins: [
                wsRegions, 
                WaveSurfer.Timeline.create({ 
                    container: '#wave-timeline',
                    height: 20,
                    timeInterval: (pxPerSec) => {
                        if (pxPerSec >= 200) return 0.1;
                        if (pxPerSec >= 50) return 0.5;
                        if (pxPerSec >= 20) return 1;
                        return 5;
                    },
                    primaryLabelInterval: (pxPerSec) => {
                        if (pxPerSec >= 200) return 1;
                        if (pxPerSec >= 50) return 2.5;
                        if (pxPerSec >= 20) return 5;
                        return 10;
                    },
                    style: { fontSize: '10px', color: '#888' }
                })
            ]
        });
        window.VoiceQwen.wavesurferInstance = wavesurfer;

        wsRegions.enableDragSelection({ color: 'rgba(255, 0, 255, 0.2)' });
        
        // Enforce single region logic
        wsRegions.on('region-created', (region) => {
            activeRegion = region;
            showSegmentMenuAt(lastPointer.clientX || 0, lastPointer.clientY || 0);
            wsRegions.getRegions().forEach(r => {
                if (r !== region) r.remove();
            });
        });

        wsRegions.on('region-updated', (region) => {
            activeRegion = region;
            showSegmentMenuAt(lastPointer.clientX || 0, lastPointer.clientY || 0);
            $('#wave-region-delete').removeClass('hidden').off('click').on('click', () => {
                const newBuf = window.VoiceQwen.deleteSegment(window.VoiceQwen.activeAudioBuffer, region.start, region.end);
                if (newBuf) {
                    window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
                    window.VoiceQwen.activeAudioBuffer = newBuf;
                    updateWaveformPreview();
                    $('#wave-region-delete').addClass('hidden');
                }
            });
        });

        wavesurfer.on('ready', () => {
            window.VoiceQwen.isLoadingWaveform = false;
            $('#wave-viewer-loading').addClass('hidden');
            $('#wave-viewer-container').removeClass('hidden').css({ visibility: '' });
            // Force a redraw so the Timeline renders tick labels after becoming visible.
            setTimeout(() => {
                if (!wavesurfer) return;
                const z = Number($('#wave-zoom').val() || 10);
                wavesurfer.zoom(z);
            }, 0);
            $(document).trigger('voiceqwen_waveform_ready');
        });

        wavesurfer.on('click', () => {
            wsRegions.clearRegions();
            $('#wave-region-delete').addClass('hidden');
            activeRegion = null;
            pendingReplaceRange = null;
            hideSegmentMenu();
        });

        // Also bust cache for WaveSurfer internal fetch.
        wavesurfer.load(withCacheBuster(url));
    }

    function updateWaveformPreview() {
        const blob = window.VoiceQwen.audioBufferToWav(window.VoiceQwen.activeAudioBuffer);
        currentWaveUrl = URL.createObjectURL(blob);
        if (wsRegions) wsRegions.clearRegions();
        activeRegion = null;
        pendingReplaceRange = null;
        wavesurfer.load(currentWaveUrl);
        $('#wave-save, #wave-undo').removeClass('hidden');
        if (activeFileRelPath) {
            $('#wave-sync-r2').removeClass('hidden');
        }
        requestAutoSave();
    }

    function ensureSegmentMenu() {
        if (document.getElementById('wave-segment-menu')) return;
        const el = document.createElement('div');
        el.id = 'wave-segment-menu';
        el.className = 'vq-segment-menu hidden';
        el.innerHTML = `
            <button type="button" class="vq-seg-btn" data-action="copy">COPY</button>
            <button type="button" class="vq-seg-btn" data-action="delete">DELETE</button>
            <button type="button" class="vq-seg-btn" data-action="voice">VOICE</button>
        `;
        document.body.appendChild(el);
    }

    function hideSegmentMenu() {
        const el = document.getElementById('wave-segment-menu');
        if (el) el.classList.add('hidden');
    }

    function showSegmentMenuAt(clientX, clientY) {
        ensureSegmentMenu();
        const el = document.getElementById('wave-segment-menu');
        if (!el) return;
        el.style.left = `${clientX}px`;
        el.style.top = `${clientY}px`;
        el.classList.remove('hidden');
    }

    function openAddSpeechAt(timeSeconds, clientX, clientY, options = {}) {
        if (!wavesurfer) return;
        const duration = wavesurfer.getDuration() || 0;
        if (!duration) return;
        lastInsertTime = Math.max(0, Math.min(duration, Number(timeSeconds) || 0));
        pendingReplaceRange = options && options.replaceRange ? {
            start: Number(options.replaceRange.start) || 0,
            end: Number(options.replaceRange.end) || 0
        } : null;

        const $modal = $('#wave-mini-modal');
        $modal.removeClass('hidden').css({ left: `${clientX}px`, top: `${clientY}px` });

        // Reset defaults each time the panel opens (so it doesn't keep stale values).
        $('#mini-stability').val('0.5');
        $('#mini-stability-val').text('0.5');
        $('#mini-max-words').val('40');
        $('#mini-max-words-val').text('40');
        $('#mini-pause-time').val('0.1');
        $('#mini-pause-time-val').text('0.1');

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
    }

    function openAddSpeechModal(nativeEvent) {
        if (!wavesurfer) return;

        const wrapper = wavesurfer.getWrapper();
        const rect = wrapper.getBoundingClientRect();
        const x = nativeEvent.clientX - rect.left;
        const duration = wavesurfer.getDuration() || 0;
        if (!duration || rect.width <= 0) return;

        // Accurate time calculation regardless of zoom.
        const t = (x / rect.width) * duration;
        console.log("Waveform: Context menu at time", t);
        openAddSpeechAt(t, nativeEvent.clientX, nativeEvent.clientY);
    }

    // Right-click is handled by waveform-markers.js (point menu: VOICE / MARKER).

    // Track last pointer position inside the waveform, so region selection can spawn a small submenu under the cursor.
    $(document).on('mousemove touchmove', '#waveform', function(e) {
        const oe = e.originalEvent && e.originalEvent.touches ? e.originalEvent.touches[0] : (e.originalEvent || e);
        if (!oe) return;
        lastPointer = { clientX: oe.clientX || 0, clientY: oe.clientY || 0 };
    });

    // Segment submenu actions (DELETE / VOICE)
    $(document).on('click', '#wave-segment-menu .vq-seg-btn', function(e) {
        e.preventDefault();
        const action = $(this).data('action');
        if (!activeRegion || !window.VoiceQwen || !window.VoiceQwen.activeAudioBuffer) return;

        if (action === 'delete') {
            const newBuf = window.VoiceQwen.deleteSegment(window.VoiceQwen.activeAudioBuffer, activeRegion.start, activeRegion.end);
            if (newBuf) {
                window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
                window.VoiceQwen.activeAudioBuffer = newBuf;
                updateWaveformPreview();
                hideSegmentMenu();
            }
        } else if (action === 'copy') {
            const segment = window.VoiceQwen.extractSegment(window.VoiceQwen.activeAudioBuffer, activeRegion.start, activeRegion.end);
            if (segment) {
                window.VoiceQwen.copiedAudioBuffer = segment;
                console.log("Waveform: Segment copied to clipboard", segment.duration.toFixed(2), "s");
                // Visual feedback
                const $btn = $(this);
                const originalText = $btn.text();
                $btn.text('COPIED!').css('color', '#00ff00');
                setTimeout(() => {
                    $btn.text(originalText).css('color', '');
                    hideSegmentMenu();
                }, 800);
            }
        } else if (action === 'voice') {
            hideSegmentMenu();
            pendingReplaceRange = {
                start: activeRegion.start,
                end: activeRegion.end
            };
            openAddSpeechAt(activeRegion.start, lastPointer.clientX || 0, lastPointer.clientY || 0, {
                replaceRange: pendingReplaceRange
            });
        }
    });

    // Hide submenu when clicking elsewhere.
    $(document).on('mousedown', function(e) {
        const menu = document.getElementById('wave-segment-menu');
        if (!menu || menu.classList.contains('hidden')) return;
        if (menu.contains(e.target)) return;
        hideSegmentMenu();
    });

    // Zoom interactions are handled by waveform-ruler-controls.js to keep files small.
    $(document).on('input', '#wave-zoom', function() {
        if (wavesurfer) wavesurfer.zoom(Number($(this).val()));
    });

    $(document).on('input', '#mini-stability', function() { $('#mini-stability-val').text($(this).val()); });
    $(document).on('input', '#mini-max-words', function() { $('#mini-max-words-val').text($(this).val()); });
    $(document).on('input', '#mini-pause-time', function() { $('#mini-pause-time-val').text($(this).val()); });

    $(document).on('click', '#mini-generate-btn', async function() {
        const text = $('#mini-text').val();
        if (!text) return alert("Escribe un texto");

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_audio');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('voice', $('input[name="mini-voice"]:checked').val());
        formData.append('text', text);
        formData.append('stability', $('#mini-stability').val());
        formData.append('max_words', $('#mini-max-words').val());
        formData.append('pause_time', $('#mini-pause-time').val());
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
            try {
                const u = new URL(url, window.location.href);
                const clipName = decodeURIComponent(u.pathname.split('/').pop() || '');
                if (clipName && clipName.toLowerCase().startsWith('clip')) {
                    if (!sessionTempClips.includes(clipName)) sessionTempClips.push(clipName);
                }
            } catch (_) {}

            const ctx = getAudioCtx();
            const res = await fetch(withCacheBuster(url), { cache: 'no-store' });
            const arrayBuf = await res.arrayBuffer();
            const buf = await ctx.decodeAudioData(arrayBuf);

            let baseBuffer = window.VoiceQwen.activeAudioBuffer;
            let insertTime = lastInsertTime;
            if (pendingReplaceRange && pendingReplaceRange.end > pendingReplaceRange.start) {
                baseBuffer = window.VoiceQwen.deleteSegment(
                    window.VoiceQwen.activeAudioBuffer,
                    pendingReplaceRange.start,
                    pendingReplaceRange.end
                );
                insertTime = pendingReplaceRange.start;
            }

            console.log("Waveform: Inserting clip at", insertTime, pendingReplaceRange ? '(replace region)' : '(insert point)');
            const newBuf = await window.VoiceQwen.insertAudioAt(baseBuffer, buf, insertTime);
            
            window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
            window.VoiceQwen.activeAudioBuffer = newBuf;
            pendingReplaceRange = null;
            
            updateWaveformPreview();
            $('#wave-mini-modal').addClass('hidden');
            $('#mini-generate-btn').prop('disabled', false).text('GENERATE & INSERT');
            $('#mini-text').val('');
        } catch (e) { 
            console.error("Insertion error:", e);
            alert("Error al insertar: " + e.message); 
            pendingReplaceRange = null;
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

    $(document).on('click', '#wave-sync-r2', function() {
        if (!activeFileRelPath) return;
        const $btn = $(this);
        const originalHtml = $btn.html();
        
        $btn.prop('disabled', true).html('<span class="material-symbols-outlined" style="font-size: 18px;">sync</span> SYNCING...');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            timeout: 30000, // 30 seconds timeout
            data: {
                action: 'vq_sync_to_r2',
                nonce: voiceqwen_ajax.nonce,
                post_id: activePostId,
                key: activeFileRelPath
            },
            success: function(res) {
                if (res.success) {
                    alert("¡Archivo subido a R2 con éxito!");
                    $btn.addClass('hidden');
                    // Trigger a refresh of the audiobook list if needed
                    $(document).trigger('voiceqwen_audio_synced', [activeFileRelPath]);
                } else {
                    alert("Error al subir: " + (res.data || 'Respuesta desconocida'));
                    $btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                alert("Error de red o tiempo de espera agotado: " + error);
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
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
            if (sessionTempClips.length) fd.append('cleanup_files', JSON.stringify(sessionTempClips));
            fd.append('audio', blob, activeFileName);

            $.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: fd, processData: false, contentType: false, success: (res) => {
                if (res.success) { 
                    alert("¡Cambios guardados con éxito!"); 
                    $btn.addClass('hidden'); 
                    sessionTempClips = [];
                    deleteAutosave(activeFileName, activeFileRelPath); 
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
            fd.append('rel_path', activeFileRelPath || activeFileName);
            fd.append('audio', blob, activeFileName + '-autosave.wav');
            $.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: fd, processData: false, contentType: false });
        } catch(e) {}
    }

    function deleteAutosave(filename, relPath = '') {
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_delete_autosave',
            nonce: voiceqwen_ajax.nonce,
            filename: filename,
            rel_path: relPath || filename
        });
    }

    $(document).on('click', '#mini-modal-close', () => {
        pendingReplaceRange = null;
        $('#wave-mini-modal').addClass('hidden');
    });

    // Draggable Mini Modal
    let miniGrabX, miniGrabY;

    $(document).on('mousedown', '#wave-mini-modal .mini-header', function(e) {
        if ($(e.target).closest('button').length) return;
        isDraggingMini = true;
        const $modal = $('#wave-mini-modal');
        const rect = $modal[0].getBoundingClientRect();
        miniGrabX = e.clientX - rect.left;
        miniGrabY = e.clientY - rect.top;
        $modal.css('cursor', 'grabbing');
        e.preventDefault();
    });

    $(document).on('mousemove', function(e) {
        if (isDraggingMini) {
            const $modal = $('#wave-mini-modal');
            $modal.css({
                left: (e.clientX - miniGrabX) + 'px',
                top: (e.clientY - miniGrabY) + 'px'
            });
        }
    });

    $(document).on('mouseup', function() {
        if (isDraggingMini) {
            isDraggingMini = false;
            $('#wave-mini-modal').css('cursor', '');
        }
    });
    // Custom Resize Logic for Mini Modal
    let isResizingRight = false, isResizingBottom = false, isResizingBoth = false;
    let startWidth, startHeight, resizeStartX, resizeStartY;

    $(document).on('mousedown', '.resize-handle-e', function(e) {
        isResizingRight = true;
        startWidth = $('#wave-mini-modal').outerWidth();
        resizeStartX = e.pageX;
        e.preventDefault();
    });

    $(document).on('mousedown', '.resize-handle-s', function(e) {
        isResizingBottom = true;
        startHeight = $('#wave-mini-modal').outerHeight();
        resizeStartY = e.pageY;
        e.preventDefault();
    });

    $(document).on('mousedown', '.resize-handle-se', function(e) {
        isResizingBoth = true;
        startWidth = $('#wave-mini-modal').outerWidth();
        startHeight = $('#wave-mini-modal').outerHeight();
        resizeStartX = e.pageX;
        resizeStartY = e.pageY;
        e.preventDefault();
    });

    $(document).on('mousemove', function(e) {
        if (isResizingRight || isResizingBoth) {
            $('#wave-mini-modal').css('width', Math.max(340, startWidth + (e.pageX - resizeStartX)) + 'px');
        }
        if (isResizingBottom || isResizingBoth) {
            $('#wave-mini-modal').css('height', Math.max(350, startHeight + (e.pageY - resizeStartY)) + 'px');
        }
    });

    $(document).on('mouseup', function() {
        isResizingRight = false;
        isResizingBottom = false;
        isResizingBoth = false;
    });
});
