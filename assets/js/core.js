jQuery(document).ready(function ($) {
    console.log("VoiceQwen: Core initialization started.");

    // Shared state and namespace
    window.VoiceQwen = window.VoiceQwen || {};
    window.VoiceQwen.currentJobSource = '';
    window.VoiceQwen.isLoadingWaveform = false;
    window.VoiceQwen.waveUndoStack = [];
    
    // Lazy initialize AudioContext now handled by window.VoiceQwen.getAudioCtx
    window.VoiceQwen.getAudioCtx = window.VoiceQwen.getAudioCtx || function() {
        if (!window.VoiceQwen.audioCtx) {
            window.VoiceQwen.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        return window.VoiceQwen.audioCtx;
    };

    // Navigation Guard: Warn if unsaved changes exist
    window.onbeforeunload = function() {
        if (window.VoiceQwen.hasUnsavedChanges || (window.VoiceQwen.waveUndoStack && window.VoiceQwen.waveUndoStack.length > 0)) {
            return "Tienes cambios sin guardar. ¿Estás seguro de que quieres salir?";
        }
    };

    // Tab switching
    $('.vapor-tab').on('click', function () {
        const tab = $(this).data('tab');
        $('.vapor-tab').removeClass('active');
        $(this).addClass('active');
        $('.vapor-pane').addClass('hidden');
        $('#pane-' + tab).removeClass('hidden');
    });

    // Navigation (View Switching)
    console.log("VoiceQwen: Attaching menu listeners.");
    $('.vapor-nav .nav-btn').on('click', function() {
        const view = $(this).data('view');
        console.log("VoiceQwen: Navigating to view:", view);
        if (!view) return; 
        
        $('.vapor-nav .nav-btn').removeClass('active');
        $(this).addClass('active');
        
        $('.view-pane').addClass('hidden');
        if ($(`#view-${view}`).length) {
            $(`#view-${view}`).removeClass('hidden');
        }

        // Sidebar visibility and mode management
        const $sidebar = $('.vapor-window.sidebar');
        if (view === 'waveform') {
            $sidebar.addClass('overlay-mode hidden');
        } else {
            $sidebar.removeClass('overlay-mode hidden');
        }

        if (view === 'create' || view === 'audiobook') {
            if (typeof window.VoiceQwen.loadVoices === 'function') {
                window.VoiceQwen.loadVoices();
            }
        }
    });

    // Toggle Sidebar button in Waveform view
    if ($('#nav-audiobook').length) {
        // /audi template: this button should behave like "Back to Audiobook editor".
        $('#toggle-sidebar-btn')
            .text('VOLVER')
            .css({ background: '#000', color: '#fff', borderColor: '#000' });
    }

    $(document).on('click', '#toggle-sidebar-btn', function(e) {
        if ($('#nav-audiobook').length) {
            e.preventDefault();

            // Switch back to the audiobook manager view.
            $('.nav-item-btn').removeClass('active');
            $('#nav-audiobook').addClass('active');
            $('.view-container').removeClass('active').addClass('hidden').hide();
            $('#view-audio-manager').addClass('active').removeClass('hidden').show();

            // Hide waveform view (it is not a .view-container on /audi).
            $('#view-waveform').addClass('hidden').hide();

            // Re-open the last active book editor (chapters list).
            if (window.VoiceQwen && typeof window.VoiceQwen.openAudiobookEditor === 'function' && window.VoiceQwen.lastAudiobookPostId) {
                window.VoiceQwen.openAudiobookEditor(window.VoiceQwen.lastAudiobookPostId);
            }
            return;
        }

        // LOCUTOR UI: toggle the floating file sidebar overlay.
        $('.vapor-window.sidebar').toggleClass('hidden').css({ top: '60px', left: '20px' });
    });

    // Draggable Sidebar (Overlay Mode only)
    let isDraggingSidebar = false, grabX, grabY;

    $(document).on('mousedown', '.sidebar.overlay-mode .vapor-window-header', function(e) {
        if ($(e.target).closest('button').length) return; 
        
        const $sidebar = $('.sidebar.overlay-mode');
        isDraggingSidebar = true;
        
        // Calculate the offset of the mouse relative to the top-left of the sidebar
        const offset = $sidebar.offset();
        grabX = e.pageX - offset.left;
        grabY = e.pageY - offset.top;
        
        $sidebar.css('cursor', 'grabbing');
        e.preventDefault();
    });

    $(document).on('mousemove', function(e) {
        if (isDraggingSidebar) {
            const $sidebar = $('.sidebar.overlay-mode');
            const parentOffset = $sidebar.offsetParent().offset();
            
            let newLeft = e.pageX - parentOffset.left - grabX;
            let newTop = e.pageY - parentOffset.top - grabY;
            
            $sidebar.css({
                left: newLeft + 'px',
                top: newTop + 'px'
            });
        }
    });

    $(document).on('mouseup', function() {
        if (isDraggingSidebar) {
            isDraggingSidebar = false;
            $('.sidebar.overlay-mode').css('cursor', '');
        }
    });

    $(document).on('click', '.nav-btn-back', function() {
        const view = $(this).data('view');
        $('.view-pane').addClass('hidden');
        $(`#view-${view}`).removeClass('hidden');
    });

    // Initial Load sequence
    setTimeout(() => {
        if (typeof window.VoiceQwen.loadVoices === 'function') window.VoiceQwen.loadVoices();
        if (typeof window.VoiceQwen.loadFiles === 'function') window.VoiceQwen.loadFiles();
        if (typeof window.VoiceQwen.checkBackgroundStatus === 'function') window.VoiceQwen.checkBackgroundStatus();
    }, 100);
});
