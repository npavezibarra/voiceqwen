jQuery(document).ready(function ($) {
    let currentPath = ''; 
    let fullFileTree = [];

    window.VoiceQwen = window.VoiceQwen || {};
    window.VoiceQwen.loadFiles = loadFileList;
    window.VoiceQwen.getPath = () => currentPath;
    window.VoiceQwen.setPath = (newPath) => {
        currentPath = (newPath || '').replace(/^\/+/, '');
        renderCurrentPath();
    };

    function loadFileList() {
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_list_files',
            nonce: voiceqwen_ajax.nonce
        }, function (response) {
            if (response.success) {
                fullFileTree = response.data;
                renderCurrentPath();
            }
        });
    }

    function renderCurrentPath() {
        const $list = $('#file-list');
        $list.empty();
        
        $list.append(setupDropZone($('<div class="root-drop-zone" data-folder="">Arrastra aquí para raíz</div>')));

        if (currentPath !== '') {
            $(`<li class="folder-item-back" style="cursor:pointer; padding:8px; background:rgba(0,0,255,0.1);">⬅️ ATRÁS (RAÍZ)</li>`)
                .on('click', () => { currentPath = ''; renderCurrentPath(); })
                .appendTo($list);
        }

        let items = fullFileTree;
        if (currentPath !== '') {
            currentPath.split('/').forEach(part => {
                const found = items.find(i => i.type === 'folder' && i.name === part);
                if (found) items = found.children;
            });
        }

        if (items.length === 0) {
            $list.append('<li style="padding:10px; opacity:0.5;">(Carpeta vacía)</li>');
        } else {
            items.forEach(item => {
                const isFolder = item.type === 'folder';
                const $li = $(`<li class="${isFolder ? 'folder-item' : 'file-item'}" data-rel="${item.rel_path}" data-filename="${item.name}" draggable="true">
                    <div class="${isFolder ? 'folder-item-row' : ''}" style="width:100%; display:flex; justify-content:space-between; align-items:center;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span class="${isFolder ? 'folder-icon' : 'file-icon'}"></span>
                            <span class="${isFolder ? 'folder-name' : 'file-name'}">${item.name}</span>
                        </div>
                        ${isFolder ? '<span style="font-size:10px; opacity:0.5;">ENTRAR ➡️</span>' : '<div style="display:flex; gap:4px;"><span class="send-to-ab-btn" title="Send to Audiobook">📀</span><span class="trash-btn">🗑️</span></div>'}
                    </div>
                </li>`);

                if (isFolder) {
                    $li.on('click', () => { currentPath = item.rel_path; renderCurrentPath(); });
                } else {
                    $li.on('click', (e) => {
                        if ($(e.target).closest('.trash-btn').length || $(e.target).closest('.send-to-ab-btn').length) return;
                        
                        if (typeof window.VoiceQwen.loadWaveform === 'function') {
                            // Switch view to waveform first
                            $('.vapor-nav .nav-btn[data-view="waveform"]').click();
                            window.VoiceQwen.loadWaveform(item.url, item.name, item.has_backup, item.has_autosave, item.autosave_url, item.rel_path);
                        } else {
                            playAudio(item.url, item.name);
                        }
                    });
                    $li.on('click', '.trash-btn', (e) => { e.stopPropagation(); if (confirm(`¿Borrar ${item.name}?`)) deleteFile(item.rel_path); });
                    $li.on('click', '.send-to-ab-btn', (e) => { e.stopPropagation(); sendToAudiobook(item.rel_path); });
                }
                setupDraggable($li);
                $list.append($li);
            });
        }
    }

    function setupDraggable($el) {
        $el.on('dragstart', (e) => {
            e.originalEvent.dataTransfer.setData('text/plain', $el.data('rel'));
            e.originalEvent.dataTransfer.setData('item-name', $el.data('filename'));
            $el.addClass('is-dragging');
        }).on('dragover', (e) => {
            e.preventDefault();
            $el.addClass('drop-hover');
        }).on('dragleave', () => $el.removeClass('drop-hover'))
        .on('dragend', () => $el.removeClass('is-dragging'))
        .on('drop', (e) => {
            e.preventDefault(); e.stopPropagation(); $el.removeClass('drop-hover');
            const draggedRel = e.originalEvent.dataTransfer.getData('text/plain');
            const targetRel = $el.data('rel');
            if (draggedRel && draggedRel !== targetRel) {
                $.post(voiceqwen_ajax.url, { action: 'voiceqwen_move_item', nonce: voiceqwen_ajax.nonce, item_rel: draggedRel, target_folder: targetRel }, loadFileList);
            }
        });
    }

    function setupDropZone($el) {
        return $el.on('dragover', (e) => { e.preventDefault(); $el.addClass('drop-hover'); })
            .on('dragleave', () => $el.removeClass('drop-hover'))
            .on('drop', (e) => {
                e.preventDefault(); $el.removeClass('drop-hover');
                const itemRel = e.originalEvent.dataTransfer.getData('text/plain');
                if (itemRel) $.post(voiceqwen_ajax.url, { action: 'voiceqwen_move_item', nonce: voiceqwen_ajax.nonce, item_rel: itemRel, target_folder: '' }, loadFileList);
            });
    }

    function deleteFile(relPath) {
        $.post(voiceqwen_ajax.url, { action: 'voiceqwen_delete_file', nonce: voiceqwen_ajax.nonce, filename: relPath }, loadFileList);
    }

    function playAudio(url, filename) {
        $('#sidebar-player').html(`
            <div style="padding:10px; background:rgba(0,0,255,0.05); border-top:2px dotted blue;">
                <div style="font-size:12px; color:blue; margin-bottom:5px;">${filename}</div>
                <audio controls autoplay src="${url}" style="width:100%; height:30px;"></audio>
            </div>`);
    }

    function sendToAudiobook(relPath) {
        const $modal = $('#mini-modal');
        const $content = $modal.find('.modal-body');
        
        $modal.find('h3').text('Enviar a Audiobook');
        $content.html('<p>Cargando libros...</p>');
        $modal.fadeIn();

        // Use the new namespaced action to get books
        $.post(voiceqwen_ajax.url, {
            action: 'vq_get_books',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            if (response.success && response.data.length > 0) {
                let html = '<ul class="vapor-list" style="max-height:200px; overflow-y:auto;">';
                response.data.forEach(book => {
                    html += `<li class="book-choice" data-id="${book.id}" style="cursor:pointer; padding:8px; border-bottom:1px solid #ccc;">${book.title}</li>`;
                });
                html += '</ul>';
                $content.html(html);

                $('.book-choice').on('click', function() {
                    const bookId = $(this).data('id');
                    $content.html('<p>Subiendo a Cloudflare R2...</p>');
                    
                    $.post(voiceqwen_ajax.url, {
                        action: 'voiceqwen_send_to_audiobook',
                        nonce: voiceqwen_ajax.nonce,
                        item_rel: relPath,
                        book_id: bookId
                    }, function(res) {
                        if (res.success) {
                            $content.html(`<p style="color:green;">✓ ${res.data}</p>`);
                            setTimeout(() => $modal.fadeOut(), 1500);
                        } else {
                            $content.html(`<p style="color:red;">✗ ${res.data}</p>`);
                        }
                    });
                });
            } else {
                $content.html('<p>No se encontraron audiobooks creados.</p>');
            }
        });
    }

    $(document).on('click', '#sidebar-new-folder-btn', () => {
        const name = prompt("Nombre de la carpeta:");
        if (name) $.post(voiceqwen_ajax.url, { action: 'voiceqwen_create_folder', nonce: voiceqwen_ajax.nonce, folder: name }, loadFileList);
    });
});
