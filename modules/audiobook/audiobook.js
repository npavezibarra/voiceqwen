/**
 * VoiceQwen Audiobook Manager Logic
 */
jQuery(document).ready(function($) {
    const modal = $('#vq-book-modal');
    const createBtn = $('#vq-create-book-btn');
    const closeBtn = $('#vq-modal-close');
    const confirmBtn = $('#vq-modal-confirm');
    const editorPanel = $('#vq-editor-panel');
    const bookListContainer = $('#vq-book-list-container');
    
    let activeWavesurfer = null;

    window.VoiceQwen = window.VoiceQwen || {};
    // Expose editor loader so /audi waveform "VOLVER" can restore the last opened book editor.
    window.VoiceQwen.openAudiobookEditor = loadEditor;
    
    // --- Dropdown Toggle ---
    $(document).on('click', '.vq-chapter-dropdown-btn', function(e) {
        e.stopPropagation();
        $('.vq-dropdown-menu').not($(this).siblings('.vq-dropdown-menu')).removeClass('show');
        $(this).siblings('.vq-dropdown-menu').toggleClass('show');
    });

    $(document).on('click', function() {
        $('.vq-dropdown-menu').removeClass('show');
    });

    // --- Dropdown Item Actions ---
    $(document).on('click', '.vq-dropdown-item', function() {
        const action = $(this).data('action');
        const container = $(this).closest('.vq-audiobook-editor');
        const postId = container.find('.vq-chapters-list').data('id');

        if (action === 'upload-wav') {
            triggerLocalUpload(postId, container);
        } else if (action === 'select-r2') {
            // Future: R2 selection modal
        } else if (action === 'create-audio') {
            // Future: Logic for generating audio
        }
    });

    function triggerLocalUpload(postId, container) {
        const input = $('<input type="file" accept=".wav">');
        input.on('change', function() {
            const file = this.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'vq_upload_local_chapter');
            formData.append('nonce', voiceqwen_ajax.nonce);
            formData.append('post_id', postId);
            formData.append('file', file);

            const progress = container.find('.vq-upload-progress-container');
            const bar = progress.find('.vq-progress-bar');
            const status = progress.find('.vq-upload-status');

            progress.show();
            status.text('Uploading to Local Folder...');

            $.ajax({
                url: voiceqwen_ajax.url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            const percent = (evt.loaded / evt.total) * 100;
                            bar.css('width', percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    progress.hide();
                    if (response.success) {
                        addChapterToList(container, response.data, 'local');
                    } else {
                        alert('Upload failed: ' + response.data);
                    }
                }
            });
        });
        input.click();
    }

    // --- Sync to R2 Action ---
    $(document).on('click', '.vq-sync-btn', function(e) {
        e.preventDefault();
        const btn = $(this);
        const item = btn.closest('.vq-chapter-item');
        const key = btn.data('key');
        const container = btn.closest('.vq-audiobook-editor');
        const postId = container.find('.vq-chapters-list').data('id');

        if (btn.hasClass('vq-syncing')) return;

        btn.addClass('vq-syncing').text('⌛');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_sync_to_r2',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId,
                key: key
            },
            success: function(response) {
                if (response.success) {
                    console.log("Sync success for key:", key, "New R2 Key:", response.data.new_key);
                    const actions = btn.closest('.vq-chapter-actions');
                    actions.find('.vq-badge-local').replaceWith('<span class="vq-badge vq-badge-r2">R2</span>');
                    btn.remove();
                    item.attr('data-key', response.data.new_key);
                    item.find('.vq-inline-play').attr('data-key', response.data.new_key).attr('data-storage', 'r2');
                    item.find('.vq-chapter-title').trigger('change'); // Trigger save
                } else {
                    console.error("Sync failed:", response.data);
                    alert('Sync failed: ' + response.data);
                    btn.removeClass('vq-syncing').text('↑');
                }
            },
            error: function() {
                btn.removeClass('vq-syncing').text('↑');
            }
        });
    });

    // --- View Switch Listener ---
    $(document).on('click', '.nav-btn[data-view="audiobook"]', function() {
        // We could refresh the list here if needed
    });

    // --- Navigation (DblClick to Edit) ---
    $(document).on('dblclick', '.vq-book-item', function() {
        const item = $(this);
        const postId = item.data('id');

        $('.vq-book-item').removeClass('active');
        item.addClass('active');

        loadEditor(postId);
    });

    // Fallback for single click selection in mobile/vapor theme
    $(document).on('click', '.vq-book-item', function() {
        $('.vq-book-item').removeClass('selected');
        $(this).addClass('selected');
    });

    function loadEditor(postId) {
        window.VoiceQwen.lastAudiobookPostId = postId;

        if (activeWavesurfer) {
            activeWavesurfer.destroy();
            activeWavesurfer = null;
        }

        editorPanel.html('<div class="welcome-state"><div class="welcome-content"><h3>Loading...</h3></div></div>');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_get_book_editor',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    editorPanel.html(response.data);
                    initSortable(postId);
                } else {
                    editorPanel.html('<div class="welcome-state"><h3>Error loading editor</h3><p>' + response.data + '</p></div>');
                }
            }
        }).fail(function(xhr, status, error) {
            editorPanel.html('<div class="welcome-state"><h3>Connection Error</h3><p>' + error + '</p></div>');
        });
    }

    // --- Modal Management ---
    createBtn.on('click', () => modal.removeClass('hidden').show());
    closeBtn.on('click', () => modal.addClass('hidden').hide());
    
    confirmBtn.on('click', function() {
        const title = $('#vq-new-book-title').val().trim();
        const author = $('#vq-new-book-author').val().trim();

        if (!title) return;

        confirmBtn.prop('disabled', true).text('...');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_create_book',
                nonce: voiceqwen_ajax.nonce,
                title: title,
                author: author
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Refresh to update list
                } else {
                    alert('Error: ' + response.data);
                    confirmBtn.prop('disabled', false).text('Save');
                }
            }
        });
    });

    // --- Drag & Drop (SortableJS) ---
    function initSortable(postId) {
        const el = $(`.vq-chapters-list[data-id="${postId}"]`)[0];
        if (el) {
            new Sortable(el, {
                handle: '.vq-drag-handle',
                animation: 150,
                onEnd: function() {
                    savePlaylist(postId);
                }
            });
        }
    }

    // --- Save Playlist ---
    function savePlaylist(postId) {
        const playlist = [];
        $(`.vq-chapters-list[data-id="${postId}"] .vq-chapter-item`).each(function() {
            const item = $(this);
            playlist.push({
                title: item.find('.vq-chapter-title').val(),
                key: item.data('key'),
                storage: item.find('.vq-inline-play').data('storage') || 'r2'
            });
        });

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_save_playlist',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId,
                playlist: playlist
            }
        });
    }

    $(document).on('change', '.vq-chapter-title', function() {
        const postId = $(this).closest('.vq-chapters-list').data('id');
        savePlaylist(postId);
    });

    $(document).on('click', '.vq-remove-track', function() {
        if (confirm('Remove this track from playlist?')) {
            const list = $(this).closest('.vq-chapters-list');
            const postId = list.data('id');
            $(this).closest('.vq-chapter-item').remove();
            savePlaylist(postId);
        }
    });

    $(document).on('click', '.vq-inline-play', function() {
        const btn = $(this);
        const key = btn.data('key');
        const storage = btn.data('storage') || 'r2';
        const card = btn.closest('.vq-card');
        const postId = card.data('id');
        
        console.log("Playing chapter:", { key, storage, postId });
        const chapterItem = btn.closest('.vq-chapter-item');
        const playerContainer = card.find('.vq-inline-player');
        
        if (activeWavesurfer && btn.hasClass('loaded')) {
            activeWavesurfer.playPause();
            return;
        }

        if (activeWavesurfer) {
            activeWavesurfer.destroy();
            $('.vq-inline-play').text('▶').removeClass('loaded');
            $('.vq-inline-player').hide();
        }

        // Move player below the active chapter
        playerContainer.insertAfter(chapterItem);
        btn.text('⌛');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_get_track_url',
                nonce: voiceqwen_ajax.nonce,
                key: key,
                storage: storage,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    const signedUrl = response.data;
                    const wavesurferEl = playerContainer.find('.vq-wavesurfer-preview')[0];
                    playerContainer.show();
                    activeWavesurfer = WaveSurfer.create({
                        container: wavesurferEl,
                        waveColor: '#ff00ff',
                        progressColor: '#00ffff',
                        cursorColor: '#fff',
                        barWidth: 2,
                        height: 40,
                        backend: 'MediaElement'
                    });

                    activeWavesurfer.on('error', (err) => {
                        console.error("WaveSurfer Error:", err);
                        alert("Error loading audio: " + err);
                        btn.text('▶').removeClass('loaded');
                    });

                    activeWavesurfer.load(signedUrl);

                    activeWavesurfer.on('ready', () => {
                        activeWavesurfer.play();
                        btn.text('⏸').addClass('loaded');
                        updateTime(activeWavesurfer, playerContainer);
                    });

                    activeWavesurfer.on('play', () => btn.text('⏸'));
                    activeWavesurfer.on('pause', () => btn.text('▶'));
                    activeWavesurfer.on('audioprocess', () => updateTime(activeWavesurfer, playerContainer));
                    activeWavesurfer.on('finish', () => btn.text('▶'));
                } else {
                    alert('Error: ' + response.data);
                    btn.text('▶');
                }
            }
        });
    });

    function updateTime(ws, container) {
        const current = formatTime(ws.getCurrentTime());
        const total = formatTime(ws.getDuration());
        container.find('.vq-preview-time').text(`${current} / ${total}`);
    }

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    // --- Edit Local Chapter in Waveform ---
    $(document).on('click', '.vq-chapter-edit', function() {
        const btn = $(this);
        const key = btn.data('key');
        const card = btn.closest('.vq-card');
        const postId = card.data('id');
        window.VoiceQwen.lastAudiobookPostId = postId;
        const folderName = card.data('folder') || '';
        const relPath = folderName ? `${folderName}/${key}` : key;
        const displayName = btn.closest('.vq-chapter-item').find('.vq-chapter-title').val() || key;

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_get_track_url',
                nonce: voiceqwen_ajax.nonce,
                key: key,
                storage: 'local',
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    const localUrl = response.data;
                    const $waveformView = $('#view-waveform');
                    
                    // Switch view using the main navigation if available
                    const $navBtn = $('.vapor-nav .nav-btn[data-view="waveform"]');
                    if ($navBtn.length) {
                        $navBtn.click();
                    } else {
                        // Fallback for pages without the main nav (like /audi)
                        $('.view-pane, .view-container').removeClass('active').addClass('hidden');
                        $waveformView.removeClass('hidden').addClass('active').show();
                        $waveformView.css({
                            'display': 'block',
                            'opacity': '1',
                            'visibility': 'visible'
                        });
                    }

                    // Ensure mini-generation saves into the audiobook folder (and the sidebar follows it).
                    if (folderName && window.VoiceQwen && typeof window.VoiceQwen.setPath === 'function') {
                        window.VoiceQwen.setPath(folderName);
                    }

                    // Load into Waveform Editor
                    if (window.VoiceQwen && typeof window.VoiceQwen.loadWaveform === 'function') {
                        window.VoiceQwen.loadWaveform(localUrl, displayName, false, false, '', relPath, postId);
                        $('.vapor-nav .nav-btn[data-view="waveform"]').click();
                    }
                } else {
                    alert("Error: " + response.data);
                }
            }
        });
    });

    // --- Library File Selector ---
    $(document).on('click', '.vq-upload-files-btn', function() {
        const bookId = $(this).data('id');
        const bookTitle = $(this).closest('.vq-card').data('title');
        const $modal = $('#mini-modal');
        const $content = $modal.find('.modal-body');
        
        $modal.find('h3').text('Select from Library');
        $content.html('<p>Cargando archivos...</p>');
        $modal.fadeIn();

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_list_files',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            if (response.success) {
                renderSelector(response.data, $content, bookId, bookTitle, $modal);
            }
        });
    });

    function renderSelector(files, $container, bookId, bookTitle, $modal) {
        let html = '<div class="library-selector" style="max-height:300px; overflow-y:auto;">';
        
        const process = (items, prefix = '') => {
            items.forEach(item => {
                if (item.type === 'folder') {
                    html += `<div style="padding:5px; opacity:0.5; font-size:10px;">📁 ${prefix}${item.name}</div>`;
                    process(item.children, prefix + '  ');
                } else {
                    html += `<div class="lib-file-item" data-rel="${item.rel_path}" style="padding:10px; border-bottom:1px solid #eee; cursor:pointer;">
                        📄 ${item.name}
                    </div>`;
                }
            });
        };

        process(files);
        html += '</div>';
        $container.html(html);

        $('.lib-file-item').on('click', function() {
            const relPath = $(this).data('rel');
            $container.html('<p>Sending to R2...</p>');
            
            $.post(voiceqwen_ajax.url, {
                action: 'voiceqwen_send_to_audiobook',
                nonce: voiceqwen_ajax.nonce,
                item_rel: relPath,
                book_id: bookId
            }, function(res) {
                if (res.success) {
                    $container.html('<p style="color:green;">✓ Success!</p>');
                    setTimeout(() => {
                        $modal.fadeOut();
                        loadEditor(bookId); // Refresh editor to show new chapter
                    }, 1000);
                } else {
                    $container.html('<p style="color:red;">✗ ' + res.data + '</p>');
                }
            });
        });
    }

    $(document).on('click', '.vq-upload-cover-btn', function() {
        $(this).siblings('.vq-cover-uploader').click();
    });

    $(document).on('change', '.vq-cover-uploader', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const card = $(this).closest('.vq-card');
        const bookId = card.data('id');
        const btn = card.find('.vq-upload-cover-btn');

        const formData = new FormData();
        formData.append('action', 'vq_upload_cover');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('post_id', bookId);
        formData.append('file', file);

        btn.text('Uploading...');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                btn.text('Cover');
                if (response.success) {
                    const newUrl = response.data + '?v=' + new Date().getTime();
                    
                    // Update Editor Card
                    const coverContainer = card.find('.vq-card-header-cover');
                    coverContainer.html(`<img src="${newUrl}" class="vq-mini-cover">`);
                    
                    // Update Sidebar List Item
                    const sidebarItem = $(`.vq-book-item[data-id="${bookId}"]`);
                    const thumbContainer = sidebarItem.find('.vq-book-item-thumb');
                    
                    if (thumbContainer.length) {
                        thumbContainer.html(`<img src="${newUrl}">`);
                    }
                    
                    console.log("Cover updated successfully:", newUrl);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                btn.text('Cover');
                alert('Upload failed: ' + error);
            }
        });
    });

    $(document).on('click', '.vq-upload-background-btn', function() {
        $(this).siblings('.vq-background-uploader').click();
    });

    $(document).on('change', '.vq-background-uploader', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const card = $(this).closest('.vq-card');
        const bookId = card.data('id');
        const btn = card.find('.vq-upload-background-btn');

        const formData = new FormData();
        formData.append('action', 'vq_upload_background');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('post_id', bookId);
        formData.append('file', file);

        btn.text('Uploading...');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                btn.text('FONDO');
                if (response.success) {
                    alert('Background updated successfully!');
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                btn.text('FONDO');
                alert('Upload failed: ' + error);
            }
        });
    });

    $(document).on('change', '.vq-hidden-uploader', function(e) {
        const files = e.target.files;
        if (!files.length) return;

        const card = $(this).closest('.vq-card');
        const bookId = card.data('id');
        const bookTitle = card.data('title');
        const progressContainer = card.find('.vq-upload-progress-container');
        const progressBar = card.find('.vq-progress-bar');
        const status = card.find('.vq-upload-status');

        progressContainer.show();
        
        const uploadQueue = Array.from(files);
        let completed = 0;

        const uploadNext = () => {
            if (completed >= uploadQueue.length) {
                setTimeout(() => progressContainer.fadeOut(), 2000);
                return;
            }

            const file = uploadQueue[completed];
            status.text(`Uploading (${completed + 1}/${uploadQueue.length}): ${file.name}`);
            
            const formData = new FormData();
            formData.append('action', 'vq_upload_chapter');
            formData.append('nonce', voiceqwen_ajax.nonce);
            formData.append('post_id', bookId);
            formData.append('book_title', bookTitle);
            formData.append('file', file);

            $.ajax({
                url: voiceqwen_ajax.url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            const percent = (evt.loaded / evt.total) * 100;
                            progressBar.css('width', percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        addChapterToList(card, response.data);
                        completed++;
                        uploadNext();
                    } else {
                        alert('Upload failed for ' + file.name + ': ' + response.data);
                        completed++;
                        uploadNext();
                    }
                }
            });
        };

        uploadNext();
    });

    function addChapterToList(card, track, storage = 'r2') {
        const list = card.find('.vq-chapters-list');
        list.find('.vq-no-chapters').remove();

        const badge = storage === 'local' 
            ? '<span class="vq-badge vq-badge-local">LOCAL</span> <button class="vq-chapter-edit" title="Edit Audio" data-key="'+track.key+'"><span class="material-symbols-outlined">graphic_eq</span></button> <button class="vq-sync-btn" title="Sync to Cloudflare R2" data-key="'+track.key+'"><span class="material-symbols-outlined">cloud_upload</span></button>' 
            : '<span class="vq-badge vq-badge-r2">R2</span>';

        const item = `
            <li class="vq-chapter-item" data-key="${track.key}">
                <span class="vq-drag-handle">≡</span>
                <input type="text" class="vq-chapter-title" value="${track.title}" placeholder="Chapter Title">
                <div class="vq-chapter-actions">
                    ${badge}
                    <button class="vq-inline-play" data-key="${track.key}" data-storage="${storage}"><span class="material-symbols-outlined">play_circle</span></button>
                    <button class="vq-remove-track"><span class="material-symbols-outlined">delete_forever</span></button>
                </div>
            </li>
        `;
        list.append(item);
        savePlaylist(list.data('id'));
    }
    // --- Chapter Title Auto-save ---
    $(document).on('change', '.vq-chapter-title', function() {
        const list = $(this).closest('.vq-chapters-list');
        const bookId = list.data('id');
        console.log("Title changed, saving playlist for book:", bookId);
        savePlaylist(bookId);
    });

    $(document).on('keypress', '.vq-chapter-title', function(e) {
        if (e.which === 13) { // Enter
            $(this).blur();
        }
    });

    // --- Synced from Waveform Event ---
    $(document).on('voiceqwen_audio_synced', function(e, relPath) {
        console.log("Audio synced from waveform:", relPath);
        // Refresh the active book view if it's open
        const activeCard = $('.vq-card:visible');
        if (activeCard.length) {
            const postId = activeCard.data('id');
            if (postId) {
                // We can just trigger a re-load of the editor to refresh badges
                $.post(voiceqwen_ajax.url, {
                    action: 'vq_get_book_editor',
                    nonce: voiceqwen_ajax.nonce,
                    post_id: postId
                }, function(response) {
                    if (response.success) {
                        $('.audiobook-editor-column').html(response.data);
                        // Re-init sortable
                        const listEl = $('.vq-chapters-list.sortable')[0];
                        if (listEl) {
                            new Sortable(listEl, {
                                handle: '.vq-drag-handle',
                                animation: 150,
                                onEnd: function() { savePlaylist(postId); }
                            });
                        }
                    }
                });
            }
        }
    });
    // --- Export Audiobook ---
    $(document).on('click', '.vq-export-book-btn', function(e) {
        e.stopPropagation();
        const postId = $(this).data('id');
        const btn = $(this);
        const originalText = btn.text();
        
        btn.text('EXPORTING...').prop('disabled', true);

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_export_audiobook',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId
            },
            success: function(response) {
                btn.text(originalText).prop('disabled', false);
                if (response.success) {
                    const data = response.data;
                    const fileName = (data.title || 'audiobook').toLowerCase().replace(/[^a-z0-9]/g, '-') + '.json';
                    const jsonStr = JSON.stringify(data, null, 4);
                    
                    const blob = new Blob([jsonStr], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                } else {
                    alert('Export failed: ' + response.data);
                }
            }
        });
    });

    // --- Import Audiobook ---
    $('#vq-import-book-btn').on('click', function() {
        $('#vq-import-file-input').click();
    });

    $('#vq-import-file-input').on('change', function() {
        const file = this.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('action', 'vq_import_audiobook');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('file', file);

        const btn = $('#vq-import-book-btn');
        const originalText = btn.text();
        btn.text('IMPORTING...').prop('disabled', true);

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                btn.text(originalText).prop('disabled', false);
                if (response.success) {
                    alert('Audiobook imported successfully!');
                    location.reload(); // Refresh to show new book
                } else {
                    alert('Import failed: ' + response.data);
                }
            }
        });
        
        this.value = ''; // Reset input
        // --- Download from R2 to Local ---
    $(document).on('click', '.vq-download-from-r2', function(e) {
        e.stopPropagation();
        const btn = $(this);
        const key = btn.data('key');
        const card = btn.closest('.vq-card');
        const postId = card.data('id');
        
        const originalIcon = btn.html();
        btn.html('<span class="material-symbols-outlined vq-spin">sync</span>').prop('disabled', true);

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_download_from_r2',
                nonce: voiceqwen_ajax.nonce,
                key: key,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    // Refresh book editor to show edit button
                    btn.closest('.vq-book-item').click();
                    setTimeout(() => {
                        $(`.vq-book-item[data-id="${postId}"]`).click();
                    }, 100);
                } else {
                    btn.html(originalIcon).prop('disabled', false);
                    alert('Download failed: ' + response.data);
                }
            }
        });
    });
});
});
