/**
 * VoiceQwen Waveform - Regions & Selection Module
 */
(function($) {
    let lastPointer = { clientX: 0, clientY: 0 };
    let activeRegion = null;

    $(document).on('voiceqwen_waveform_ready', function() {
        const wsRegions = window.VoiceQwen.wsRegions;
        if (!wsRegions) return;

        wsRegions.enableDragSelection({ color: 'rgba(255, 0, 255, 0.2)' });

        wsRegions.on('region-created', (region) => {
            activeRegion = region;
            window.VoiceQwen.activeRegion = region;
            showSegmentMenuAt(lastPointer.clientX, lastPointer.clientY);
            wsRegions.getRegions().forEach(r => { if (r !== region) r.remove(); });
        });

        wsRegions.on('region-updated', (region) => {
            activeRegion = region;
            window.VoiceQwen.activeRegion = region;
            showSegmentMenuAt(lastPointer.clientX, lastPointer.clientY);
        });
    });

    $(document).on('mousemove', '#waveform', function(e) {
        const oe = e.originalEvent || e;
        lastPointer = { clientX: oe.clientX || 0, clientY: oe.clientY || 0 };
    });

    function showSegmentMenuAt(x, y) {
        ensureMenu();
        $('#wave-segment-menu').css({ left: x, top: y }).removeClass('hidden');
    }

    function ensureMenu() {
        if ($('#wave-segment-menu').length) return;
        $('body').append(`
            <div id="wave-segment-menu" class="vq-segment-menu hidden">
                <button type="button" class="vq-seg-btn" data-action="copy">COPY</button>
                <button type="button" class="vq-seg-btn" data-action="delete">DELETE</button>
                <button type="button" class="vq-seg-btn" data-action="voice">VOICE</button>
            </div>
        `);
    }

    $(document).on('click', '#wave-segment-menu .vq-seg-btn', function() {
        const action = $(this).data('action');
        if (!activeRegion) return;

        if (action === 'delete') {
            const newBuf = window.VoiceQwen.deleteSegment(window.VoiceQwen.activeAudioBuffer, activeRegion.start, activeRegion.end);
            if (newBuf) {
                window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
                window.VoiceQwen.activeAudioBuffer = newBuf;
                window.VoiceQwen.updateWaveformPreview();
            }
        } else if (action === 'copy') {
            const segment = window.VoiceQwen.extractSegment(window.VoiceQwen.activeAudioBuffer, activeRegion.start, activeRegion.end);
            if (segment) {
                window.VoiceQwen.copiedAudioBuffer = segment;
                $(this).text('COPIED!').css('color', '#00ff00');
                setTimeout(() => { $(this).text('COPY').css('color', ''); $('#wave-segment-menu').addClass('hidden'); }, 800);
            }
        } else if (action === 'voice') {
            $('#wave-segment-menu').addClass('hidden');
            window.VoiceQwen.openAddSpeechAt(activeRegion.start, lastPointer.clientX, lastPointer.clientY, {
                replaceRange: { start: activeRegion.start, end: activeRegion.end }
            });
        }
        if (action !== 'copy') $('#wave-segment-menu').addClass('hidden');
    });

    $(document).on('mousedown', function(e) {
        if (!$(e.target).closest('#wave-segment-menu').length) $('#wave-segment-menu').addClass('hidden');
    });

})(jQuery);
