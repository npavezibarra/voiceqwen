/**
 * VoiceQwen Waveform - Layout & UI Module
 */
jQuery(document).ready(function ($) {
    let isDraggingMini = false, miniGrabX, miniGrabY;
    let isResizingRight = false, isResizingBottom = false, isResizingBoth = false;
    let startWidth, startHeight, resizeStartX, resizeStartY;

    // Zoom Slider
    $(document).on('input', '#wave-zoom', function() {
        const ws = window.VoiceQwen.getWavesurfer();
        if (ws) ws.zoom(Number($(this).val()));
    });

    // Top Bar Buttons
    $(document).on('click', '#wave-open-voice-btn', function() {
        const ws = window.VoiceQwen.getWavesurfer();
        if (!ws) return;
        const t = ws.getCurrentTime() || 0;
        const rect = ws.getWrapper().getBoundingClientRect();
        window.VoiceQwen.openAddSpeechAt(t, rect.left + rect.width/2 - 200, rect.top + 50);
    });

    // Draggable Mini Modal
    $(document).on('mousedown', '#wave-mini-modal .mini-header', function(e) {
        if ($(e.target).closest('button').length) return;
        isDraggingMini = true;
        const rect = $('#wave-mini-modal')[0].getBoundingClientRect();
        miniGrabX = e.clientX - rect.left;
        miniGrabY = e.clientY - rect.top;
        $('#wave-mini-modal').css('cursor', 'grabbing');
        e.preventDefault();
    });

    $(document).on('mousemove', function(e) {
        if (isDraggingMini) {
            $('#wave-mini-modal').css({
                left: (e.clientX - miniGrabX) + 'px',
                top: (e.clientY - miniGrabY) + 'px'
            });
        }
        // Resizing logic
        if (isResizingRight || isResizingBoth) {
            $('#wave-mini-modal').css('width', Math.max(340, startWidth + (e.pageX - resizeStartX)) + 'px');
        }
        if (isResizingBottom || isResizingBoth) {
            $('#wave-mini-modal').css('height', Math.max(350, startHeight + (e.pageY - resizeStartY)) + 'px');
        }
    });

    $(document).on('mouseup', function() {
        isDraggingMini = false;
        isResizingRight = isResizingBottom = isResizingBoth = false;
        $('#wave-mini-modal').css('cursor', '');
    });

    // Resize Handles
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
});
