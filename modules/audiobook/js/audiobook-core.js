/**
 * VoiceQwen Audiobook - Core Module (Entry Point)
 */
jQuery(document).ready(function($) {
    const modal = $('#vq-book-modal');
    const editorPanel = $('#vq-editor-panel');
    
    window.VoiceQwen = window.VoiceQwen || {};
    window.VoiceQwen.openAudiobookEditor = loadEditor;

    // --- Initialization ---
    function init() {
        // Any startup logic here
    }

    function loadEditor(postId) {
        window.VoiceQwen.lastAudiobookPostId = postId;
        
        if (window.VoiceQwen.Player.activeWavesurfer) {
            window.VoiceQwen.Player.activeWavesurfer.destroy();
            window.VoiceQwen.Player.activeWavesurfer = null;
        }

        editorPanel.html('<div class="welcome-state"><h3>Loading...</h3></div>');

        window.VoiceQwen.AJAX.loadEditor(postId, function(response) {
            if (response.success) {
                editorPanel.html(response.data);
                initSortable(postId);
            } else {
                editorPanel.html('<div class="welcome-state"><h3>Error loading editor</h3></div>');
            }
        });
    }

    function initSortable(postId) {
        const el = $(`.vq-chapters-list[data-id="${postId}"]`)[0];
        if (el && typeof Sortable !== 'undefined') {
            new Sortable(el, {
                handle: '.vq-drag-handle',
                animation: 150,
                onEnd: function() {
                    window.VoiceQwen.AJAX.savePlaylist(postId);
                }
            });
        }
    }

    // --- Event Listeners ---

    // Book Selection
    $(document).on('click', '.vq-book-item', function() {
        $('.vq-book-item').removeClass('active selected');
        $(this).addClass('active selected');
        loadEditor($(this).data('id'));
    });

    // Dropdowns
    $(document).on('click', '.vq-chapter-dropdown-btn', function(e) {
        e.stopPropagation();
        $('.vq-dropdown-menu').not($(this).siblings('.vq-dropdown-menu')).removeClass('show');
        $(this).siblings('.vq-dropdown-menu').toggleClass('show');
    });

    $(document).on('click', function() {
        $('.vq-dropdown-menu').removeClass('show');
    });

    // Dropdown Actions
    $(document).on('click', '.vq-dropdown-item', function() {
        const action = $(this).data('action');
        const card = $(this).closest('.vq-card');
        const postId = card.find('.vq-chapters-list').data('id');

        if (action === 'upload-wav') {
            triggerLocalUpload(postId, card);
        } else if (action === 'upload-txt') {
            card.find('.vq-text-uploader').click();
        }
    });

    function triggerLocalUpload(postId, card) {
        const input = $('<input type="file" accept=".wav">');
        input.on('change', function() {
            const file = this.files[0];
            if (!file) return;

            window.VoiceQwen.UI.updateUploadProgress(card.find('.vq-upload-progress-container'), 0, 'Uploading...');
            
            window.VoiceQwen.AJAX.uploadLocalChapter(postId, file, card, 
                (percent) => window.VoiceQwen.UI.updateUploadProgress(card.find('.vq-upload-progress-container'), percent),
                (res) => {
                    card.find('.vq-upload-progress-container').hide();
                    if (res.success) window.VoiceQwen.UI.addChapterToList(card, res.data, 'local');
                }
            );
        });
        input.click();
    }

    // Inline Play
    $(document).on('click', '.vq-inline-play', function() {
        const btn = $(this);
        window.VoiceQwen.Player.playTrack(btn, btn.data('key'), btn.data('storage'), btn.closest('.vq-card').data('id'));
    });

    // Sync to R2
    $(document).on('click', '.vq-sync-btn', function() {
        const btn = $(this);
        const item = btn.closest('.vq-chapter-item');
        const postId = btn.closest('.vq-card').data('id');
        
        btn.addClass('vq-syncing').text('⌛');
        window.VoiceQwen.AJAX.syncToR2(postId, btn.data('key'), function(res) {
            if (res.success) {
                item.find('.vq-badge-local').replaceWith('<span class="vq-badge vq-badge-r2">R2</span>');
                btn.remove();
                item.attr('data-key', res.data.new_key);
                item.find('.vq-inline-play').attr('data-key', res.data.new_key).attr('data-storage', 'r2');
                window.VoiceQwen.AJAX.savePlaylist(postId);
            } else {
                btn.removeClass('vq-syncing').text('↑');
            }
        });
    });

    // Removal
    $(document).on('click', '.vq-remove-track', function() {
        if (confirm('Remove chapter?')) {
            const list = $(this).closest('.vq-chapters-list');
            $(this).closest('.vq-chapter-item').remove();
            window.VoiceQwen.AJAX.savePlaylist(list.data('id'));
        }
    });

    // Cover Upload
    $(document).on('click', '.vq-upload-cover-btn', function() {
        $(this).siblings('.vq-cover-uploader').click();
    });

    $(document).on('change', '.vq-cover-uploader', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const card = $(this).closest('.vq-card');
        const btn = card.find('.vq-upload-cover-btn');
        btn.text('...');
        window.VoiceQwen.AJAX.uploadCover(card.data('id'), file, (res) => {
            btn.text('Cover');
            if (res.success) {
                const url = res.data + '?v=' + Date.now();
                card.find('.vq-card-header-cover').html(`<img src="${url}" class="vq-mini-cover">`);
                $(`.vq-book-item[data-id="${card.data('id')}"] .vq-book-item-thumb`).html(`<img src="${url}">`);
            }
        });
    });

    // Background Upload
    $(document).on('click', '.vq-upload-background-btn', function() {
        $(this).siblings('.vq-background-uploader').click();
    });

    $(document).on('change', '.vq-background-uploader', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const card = $(this).closest('.vq-card');
        const btn = card.find('.vq-upload-background-btn');
        btn.text('...');
        window.VoiceQwen.AJAX.uploadBackground(card.data('id'), file, (res) => {
            btn.text('FONDO');
            if (res.success) alert('Background updated!');
        });
    });

    // Text Upload
    $(document).on('change', '.vq-text-uploader', function(e) {
        const files = e.target.files;
        if (!files.length) return;
        const card = $(this).closest('.vq-card');
        const progress = card.find('.vq-upload-progress-container');
        
        window.VoiceQwen.UI.updateUploadProgress(progress, 0, 'Uploading text...');
        window.VoiceQwen.AJAX.uploadTextChapters(card.data('id'), files, (res) => {
            progress.hide();
            if (res.success) res.data.forEach(t => window.VoiceQwen.UI.addChapterToList(card, t, 'text'));
        });
    });

    // Create Book Modal
    $('#vq-create-book-btn').on('click', () => $('#vq-book-modal').show());
    $('#vq-modal-close').on('click', () => $('#vq-book-modal').hide());
    $('#vq-modal-confirm').on('click', function() {
        const title = $('#vq-new-book-title').val();
        const author = $('#vq-new-book-author').val();
        if (!title) return;
        $(this).prop('disabled', true).text('...');
        window.VoiceQwen.AJAX.createBook(title, author, (res) => {
            if (res.success) location.reload();
            else $(this).prop('disabled', false).text('Save');
        });
    });

    // Auto-save title
    $(document).on('change', '.vq-chapter-title', function() {
        window.VoiceQwen.AJAX.savePlaylist($(this).closest('.vq-chapters-list').data('id'));
    });

    // Edit in Waveform
    $(document).on('click', '.vq-chapter-edit', function() {
        const btn = $(this);
        const key = btn.data('key');
        const card = btn.closest('.vq-card');
        const postId = card.data('id');
        const textKey = btn.closest('.vq-chapter-item').attr('data-text-key') || btn.closest('.vq-chapter-item').data('text-key');
        const displayName = btn.closest('.vq-chapter-item').find('.vq-chapter-title').val() || key;

        window.VoiceQwen.AJAX.loadEditor(postId, (res) => { // Just a ping to ensure session
             jQuery.ajax({
                url: voiceqwen_ajax.url,
                type: 'POST',
                data: { action: 'vq_get_track_url', nonce: voiceqwen_ajax.nonce, key: key, storage: 'local', post_id: postId },
                success: function(response) {
                    if (response.success) {
                        const localUrl = response.data;
                        $('.view-pane, .view-container').removeClass('active').addClass('hidden');
                        $('#view-waveform').removeClass('hidden').addClass('active').show();
                        if (window.VoiceQwen.loadWaveform) {
                            window.VoiceQwen.loadWaveform(localUrl, displayName, false, false, '', key, postId, textKey);
                        }
                    }
                }
            });
        });
    });

    // Generate Chapter (Microphone)
    $(document).on('click', '.vq-chapter-voice', function(e) {
        const btn = $(this);
        const item = btn.closest('.vq-chapter-item');
        const textKey = item.attr('data-text-key') || btn.data('text-key');
        const bookId = item.closest('.vq-chapters-list').data('id');
        
        const $modal = $('#wave-mini-modal');
        const bookTitle = item.closest('.vq-card').data('title');
        const chapterTitle = item.find('.vq-chapter-title').val();

        $modal.find('.mini-title').text('GENERATE CHAPTER AUDIO');
        $modal.find('#mini-generate-btn')
            .data('mode', 'chapter')
            .data('chapter-id', item.data('id'))
            .data('text-key', textKey)
            .data('book-id', bookId)
            .data('book-title', bookTitle)
            .data('chapter-title', chapterTitle);
        
        $modal.find('#mini-text').hide();
        $modal.removeClass('hidden').css({ 
            left: `${e.clientX}px`, top: `${e.clientY}px`, transform: 'none', position: 'fixed', display: 'flex'
        }).show();
        
        // Ensure mode is set for chapter generation
        $modal.find('#mini-generate-btn').data('mode', 'chapter');
        window.VoiceQwen.currentJobSource = 'chapter';
    });

    function handleChapterAudio(url) {
        console.log("Audiobook: Updating chapter audio with", url);
        const bookId = window.VoiceQwen.lastAudiobookPostId;
        if (!bookId) return;

        const filename = url.split('/').pop();
        const $list = $(`.vq-chapters-list[data-id="${bookId}"]`);
        
        // Find the item that was being generated. 
        // We can use a temporary data attribute or look for the one with the matching title prefix
        const $btn = $('#mini-generate-btn');
        const textKey = $btn.data('text-key');
        
        const $item = $list.find(`.vq-chapter-item[data-text-key="${textKey}"]`);
        if ($item.length) {
            $item.attr('data-storage', 'local');
            $item.attr('data-key', filename);
            $item.find('.vq-inline-play').attr('data-storage', 'local').attr('data-key', filename).show();
            $item.find('.vq-chapter-voice').hide();
            $item.find('.vq-badge-text').replaceWith('<span class="vq-badge vq-badge-local">Local</span>');
            
            // Auto-save playlist to persist the new audio association
            window.VoiceQwen.AJAX.savePlaylist(bookId);
            
            // Reload editor to ensure everything is fresh
            loadEditor(bookId);
        }
    }

    window.VoiceQwen.handleChapterAudio = handleChapterAudio;

    init();
});
