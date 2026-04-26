/**
 * VoiceQwen Waveform - Panels & Modals Module
 */
(function($) {
    let pendingReplaceRange = null;
    let lastInsertTime = 0;

    window.VoiceQwen.openAddSpeechAt = openAddSpeechAt;
    window.VoiceQwen.handleInsertion = handleInsertion;

    function openAddSpeechAt(timeSeconds, clientX, clientY, options = {}) {
        const wavesurfer = window.VoiceQwen.getWavesurfer();
        if (!wavesurfer) return;
        
        lastInsertTime = Math.max(0, Math.min(wavesurfer.getDuration(), Number(timeSeconds) || 0));

        if (options && options.replaceRange) {
            pendingReplaceRange = options.replaceRange;
            lastInsertTime = pendingReplaceRange.start;
        } else if (window.VoiceQwen.activeRegion) {
            pendingReplaceRange = { start: window.VoiceQwen.activeRegion.start, end: window.VoiceQwen.activeRegion.end };
            lastInsertTime = pendingReplaceRange.start;
        } else {
            pendingReplaceRange = null;
        }

        const $modal = $('#wave-mini-modal');
        $modal.removeClass('hidden').css({ 
            left: `${clientX}px`, top: `${clientY}px`, transform: 'none', position: 'fixed', display: 'flex'
        }).show();
        
        // Ensure mode is set for insertion
        $modal.find('#mini-generate-btn').data('mode', 'insert');
        window.VoiceQwen.currentJobSource = 'mini';
    }

    async function handleInsertion(url) {
        try {
            const ctx = window.VoiceQwen.getAudioCtx();
            const res = await fetch(url);
            const arrayBuf = await res.arrayBuffer();
            const buf = await ctx.decodeAudioData(arrayBuf);

            let baseBuffer = window.VoiceQwen.activeAudioBuffer;
            let insertTime = lastInsertTime;

            if (pendingReplaceRange && pendingReplaceRange.end > pendingReplaceRange.start) {
                baseBuffer = window.VoiceQwen.deleteSegment(baseBuffer, pendingReplaceRange.start, pendingReplaceRange.end);
                insertTime = pendingReplaceRange.start;
            }

            const newBuf = await window.VoiceQwen.insertAudioAt(baseBuffer, buf, insertTime);
            window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
            window.VoiceQwen.activeAudioBuffer = newBuf;
            pendingReplaceRange = null;
            
            window.VoiceQwen.updateWaveformPreview();
            $('#wave-mini-modal').addClass('hidden');
        } catch (e) {
            alert("Error al insertar: " + e.message);
        }
    }

    // --- TEXT PANEL LOGIC ---

    $(document).on('click', '#wave-view-text-btn, .vq-badge-text', function(e) {
        e.preventDefault(); 
        e.stopImmediatePropagation(); 
        
        var $btn = $(this);
        var textKey = $btn.attr('data-text-key') || $btn.data('text-key');
        
        if (!textKey) {
            var $item = $btn.closest('.vq-chapter-item');
            textKey = $item.attr('data-text-key') || $item.data('text-key');
        }

        if (!textKey) {
            textKey = $btn.siblings('.vq-chapter-voice').attr('data-text-key');
        }

        var postId = window.VoiceQwen.activePostId || $btn.attr('data-post-id') || $btn.data('post-id');
        if (!postId) {
            var $container = $btn.closest('.vq-chapters-list, .vq-card');
            postId = $container.attr('data-id') || $container.data('id');
        }
        
        if (!textKey || textKey === "") {
            console.warn("Waveform: textKey is empty or missing.");
            return;
        }

        var $panel = $('#wave-text-panel');
        var $content = $('#wave-text-panel-content');
        
        $panel.attr('data-active-text-key', textKey);
        $panel.attr('data-active-post-id', postId);

        $panel.removeClass('hidden').show();
        $content.val("Cargando...");

        $.post(voiceqwen_ajax.url, {
            action: 'vq_get_chapter_text',
            nonce: voiceqwen_ajax.nonce,
            post_id: postId,
            text_key: textKey
        }, function(res) {
            if (res.success) {
                $content.val(res.data);
            } else {
                $content.val("Error: " + res.data);
            }
        });
    });

    $(document).on('click', '#wave-text-panel-save', function() {
        var $panel = $('#wave-text-panel');
        var $btn = $(this);
        var textKey = $panel.attr('data-active-text-key');
        var postId = $panel.attr('data-active-post-id');
        var content = $('#wave-text-panel-content').val();

        if (!textKey || !postId) {
            alert("Error: Missing keys for saving.");
            return;
        }

        var originalText = $btn.text();
        $btn.text("Guardando...").prop('disabled', true);

        $.post(voiceqwen_ajax.url, {
            action: 'vq_save_chapter_text',
            nonce: voiceqwen_ajax.nonce,
            post_id: postId,
            text_key: textKey,
            content: content
        }, function(res) {
            $btn.text(originalText).prop('disabled', false);
            if (res.success) {
                alert("Texto guardado correctamente.");
            } else {
                alert("Error al guardar: " + res.data);
            }
        });
    });

    $(document).on('click', '#wave-text-panel-close', function() {
        $('#wave-text-panel').addClass('hidden').hide();
    });

    // Simple Dragging for Text Panel
    var isDraggingText = false, textStartX, textStartY, textInitialX, textInitialY;
    $(document).on('mousedown', '#wave-text-panel .vapor-window-header', function(e) {
        if ($(e.target).hasClass('mini-close')) return;
        isDraggingText = true;
        textStartX = e.pageX;
        textStartY = e.pageY;
        var offset = $('#wave-text-panel').offset();
        textInitialX = offset.left - $(window).scrollLeft();
        textInitialY = offset.top - $(window).scrollTop();
        e.preventDefault();
    });

    $(document).on('mousemove', function(e) {
        if (!isDraggingText) return;
        var dx = e.pageX - textStartX;
        var dy = e.pageY - textStartY;
        $('#wave-text-panel').css({
            left: (textInitialX + dx) + 'px',
            top: (textInitialY + dy) + 'px',
            right: 'auto',
            bottom: 'auto'
        });
    });

    $(document).on('mouseup', function() {
        isDraggingText = false;
    });

    $(document).on('click', '#mini-generate-btn', async function() {
        const $btn = $(this);
        const text = $('#mini-text').val();
        if (!text && $btn.data('mode') !== 'chapter') return alert("Escribe un texto");

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_audio');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('voice', $('input[name="voice"]:checked', '#wave-mini-modal').val() || $('input[name="voice"]:checked').val());
        formData.append('text', text);
        formData.append('text_key', $btn.data('text-key') || '');
        formData.append('book_id', $btn.data('book-id') || '');
        formData.append('book_title', $btn.data('book-title') || '');
        formData.append('chapter_title', $btn.data('chapter-title') || '');
        formData.append('source', 'mini');
        formData.append('stability', $('#mini-stability').val());
        formData.append('max_words', $('#mini-max-words').val());
        formData.append('pause_time', $('#mini-pause-time').val());

        $btn.prop('disabled', true).text('GENERATING...');
        
        $.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: formData, processData: false, contentType: false, success: (res) => {
            if (res.success) {
                window.VoiceQwen.startPolling();
                $('#wave-mini-modal').addClass('hidden').hide();
            } else {
                alert("Error: " + res.data);
                $btn.prop('disabled', false).text('GENERATE');
            }
        }});
    });

    $(document).on('click', '#mini-modal-close', () => $('#wave-mini-modal').addClass('hidden'));

})(jQuery);
