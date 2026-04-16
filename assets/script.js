jQuery(document).ready(function ($) {
    let pollingInterval = null;
    let wavesurfer = null;
    let wsRegions = null;
    let activeFileUrl = '';
    let activeFileName = '';
    let currentWaveUrl = '';
    let waveUndoStack = []; // Now stores AudioBuffers
    let activeAudioBuffer = null; // The high-quality master source
    let audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 44100 });

    // Initial load is handled at the bottom of the file to ensure everything is defined.

    // Tab switching
    $('.vapor-tab').on('click', function () {
        const tab = $(this).data('tab');
        $('.vapor-tab').removeClass('active');
        $(this).addClass('active');
        $('.vapor-pane').addClass('hidden');
        $('#pane-' + tab).removeClass('hidden');
    });

    // Generate Audio
    $('#generate-btn').on('click', function () {
        const activeTab = $('.vapor-tab.active').data('tab');
        const voice = $('input[name="voice"]:checked').val();
        const $btn = $(this);
        const $status = $('#status-msg');
        const stability = $('#tts-stability').val();

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_audio');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('voice', voice);
        formData.append('stability', stability);

        if (activeTab === 'textarea') {
            const text = $('#tts-text').val();
            if (!text) {
                $status.text('Error: Texto vacío').css('color', 'red');
                return;
            }
            formData.append('text', text);
        } else {
            const fileInput = $('#tts-file')[0];
            if (fileInput.files.length === 0) {
                $status.text('Error: Selecciona un archivo').css('color', 'red');
                return;
            }
            formData.append('file', fileInput.files[0]);
        }

        $btn.prop('disabled', true).text('Procesando...');
        $status.text('Iniciando proceso en segundo plano...').css('color', '#000');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success && response.data.status === 'processing') {
                    $status.show().text('Generando audio... puedes cerrar la web si quieres. El archivo aparecerá solo.').css('color', '#0000ff');
                    $('#reset-status-btn').removeClass('hidden');
                    startPolling();
                } else {
                    $status.text('Error: ' + response.data).css('color', 'red');
                    $btn.prop('disabled', false).text('Generar Audio');
                }
            },
            error: function () {
                $status.text('Error de red').css('color', 'red');
                $btn.prop('disabled', false).text('Generar Audio');
            }
        });
    });

    // Generate Dialogue
    $('#generate-dialogue-btn').on('click', function () {
        const text = $('#dialogue-text').val();
        const $btn = $(this);
        const $status = $('#dialogue-status-msg');
        const stability = $('#dialogue-stability').val();

        if (!text) {
            $status.text('Error: Texto vacío').css('color', 'red');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_dialogue');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('text', text);
        formData.append('stability', stability);

        $btn.prop('disabled', true).text('Procesando...');
        $status.text('Iniciando diálogo en segundo plano...').css('color', '#000');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success && response.data.status === 'processing') {
                    $status.show().html('<div style="color:blue; font-weight:bold;">🚀 SE HA INICIADO LA GENERACIÓN...</div>').css('color', '#0000ff');
                    $('#reset-status-btn').removeClass('hidden');
                    startPolling();
                } else {
                    const errorMsg = response.data || 'Error desconocido';
                    $status.show().html(`<div style="border:2px solid red; background:white; padding:10px; color:red; font-weight:bold;">⚠️ ERROR: ${errorMsg}</div>`);
                    $btn.prop('disabled', false).text('Generar Diálogo');
                }
            },
            error: function () {
                $status.show().html('<div style="color:red; font-weight:bold;">⚠️ Error de red o del servidor.</div>');
                $btn.prop('disabled', false).text('Generar Diálogo');
            }
        });
    });

    // Avatar Upload
    $('.avatar-circle').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).siblings('.avatar-upload').click();
    });

    $('.avatar-upload').on('change', function () {
        const file = this.files[0];
        const voice = $(this).data('voice');
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                // Create Canvas for cropping and resizing
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                // Target square size
                const size = Math.min(img.width, img.height, 400);
                canvas.width = size;
                canvas.height = size;

                // Center crop
                const sourceSize = Math.min(img.width, img.height);
                const sourceX = (img.width - sourceSize) / 2;
                const sourceY = (img.height - sourceSize) / 2;

                ctx.drawImage(img, sourceX, sourceY, sourceSize, sourceSize, 0, 0, size, size);

                // Export to base64 (JPEG for better size control)
                let quality = 0.9;
                let dataUrl = canvas.toDataURL('image/jpeg', quality);

                // Check size (rough estimate from base64)
                while (dataUrl.length > 250000 * 1.33 && quality > 0.1) {
                    quality -= 0.1;
                    dataUrl = canvas.toDataURL('image/jpeg', quality);
                }

                // Upload via AJAX
                $.post(voiceqwen_ajax.url, {
                    action: 'voiceqwen_update_avatar',
                    nonce: voiceqwen_ajax.nonce,
                    voice: voice,
                    image: dataUrl
                }, function (response) {
                    if (response.success) {
                        $(`.avatar-circle[data-voice="${voice}"]`).css('background-image', `url(${dataUrl})`);
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });

    // Navigation (View Switching)
    $('.vapor-nav .nav-btn').on('click', function() {
        const view = $(this).data('view');
        if (!view) return; // Safety check
        
        $('.vapor-nav .nav-btn').removeClass('active');
        $(this).addClass('active');
        
        $('.view-pane').addClass('hidden');
        if ($(`#view-${view}`).length) {
            $(`#view-${view}`).removeClass('hidden');
        }

        if (view === 'create') {
            loadVoices();
        }
    });

    function loadVoices() {
        const $selector = $('#dynamic-voice-selector');
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_get_voices',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            if (response.success) {
                $selector.empty();
                const $chips = $('#dialogue-voice-chips');
                if ($chips.length) $chips.empty();

                response.data.forEach(function(voice, index) {
                    const checked = index === 0 ? 'checked' : '';
                    const html = `
                        <label class="avatar-radio">
                            <input type="radio" name="voice" value="${voice.id}" ${checked}>
                            <div class="avatar-circle" data-voice="${voice.id}" style="background-image: url('${voice.avatar}');">
                            </div>
                            <span class="avatar-name">${voice.name}</span>
                        </label>
                    `;
                    $selector.append(html);

                    // Add to Dialogues Chips
                    if ($chips.length) {
                        const $chip = $(`<button type="button" class="nav-btn" style="font-size: 14px; padding: 4px 10px; border-style: dashed; background: #fff; cursor: pointer;">[${voice.name}]</button>`);
                        $chip.on('click', function() {
                            const $textarea = $('#dialogue-text');
                            const val = $textarea.val();
                            const tagStart = `[${voice.name}]`;
                            const tagEnd = `[/${voice.name}]`;
                            const pos = $textarea[0].selectionStart;
                            const end = $textarea[0].selectionEnd;
                            
                            const textBefore = val.substring(0, pos);
                            const textAfter = val.substring(end);
                            const selectedText = val.substring(pos, end);

                            const newText = textBefore + tagStart + selectedText + tagEnd + textAfter;
                            $textarea.val(newText);
                            $textarea.focus();
                            
                            // Move cursor inside tags if no text was selected
                            if (pos === end) {
                                const newPos = pos + tagStart.length;
                                $textarea[0].setSelectionRange(newPos, newPos);
                            }
                        });
                        $chips.append($chip);
                    }
                });

                // Also populate Mini Selector if it exists
                const $miniSelector = $('#mini-voice-selector');
                if ($miniSelector.length) {
                    $miniSelector.empty();
                    response.data.forEach(function(voice, index) {
                        const checked = index === 0 ? 'checked' : '';
                        const html = `
                            <label class="avatar-radio mini">
                                <input type="radio" name="mini-voice" value="${voice.id}" ${checked} style="display:none;">
                                <div class="avatar-circle" style="background-image: url('${voice.avatar}');">
                                </div>
                                <span class="avatar-name">${voice.name}</span>
                            </label>
                        `;
                        $miniSelector.append(html);
                    });
                }
            }
        });
    }

    // New Voice Upload
    $('#upload-voice-form').on('submit', function(e) {
        e.preventDefault();
        const $status = $('#upload-status');
        const $btn = $(this).find('button');
        
        const formData = new FormData();
        formData.append('action', 'voiceqwen_upload_voice');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('name', $('#new-voice-name').val());
        formData.append('audio', $('#new-voice-audio')[0].files[0]);
        formData.append('text', $('#new-voice-text').val());
        formData.append('avatar', $('#new-voice-avatar')[0].files[0]);

        $btn.prop('disabled', true).text('Guardando...');
        $status.text('Subiendo archivos...').css('color', 'blue');

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $status.text('¡Voz creada con éxito! Ya puedes usarla en Create Audio.').css('color', 'green');
                    $('#upload-voice-form')[0].reset();
                } else {
                    $status.text('Error: ' + response.data).css('color', 'red');
                }
                $btn.prop('disabled', false).text('GUARDAR CHILENO');
            },
            error: function() {
                $status.text('Error de red').css('color', 'red');
                $btn.prop('disabled', false).text('GUARDAR CHILENO');
            }
        });
    });

    $('#reset-status-btn').on('click', function () {
        if (!confirm('¿Estás seguro de que quieres cancelar el proceso actual?')) return;

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_reset_status',
            nonce: voiceqwen_ajax.nonce
        }, function (response) {
            if (response.success) {
                $('#generate-btn').prop('disabled', false).text('Generar Audio').css('background', '');
                $('#status-msg').hide();
                $('#reset-status-btn').addClass('hidden');
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                }
            }
        });
    });

    function startPolling() {
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(checkBackgroundStatus, 5000);
    }

    function checkBackgroundStatus() {
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_check_status',
            nonce: voiceqwen_ajax.nonce
        }, function (response) {
            const $status = $('#status-msg');
            const $btn = $('#generate-btn');
            const $reset = $('#reset-status-btn');

            if (response.success && response.data.status === 'processing') {
                const details = response.data.details;
                const startTime = details.time;
                const elapsed = Math.floor((Date.now() / 1000) - startTime);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                
                let progressStr = "";
                let statusColor = "#0000ff";
                let statusEmoji = "⚡";

                if (details.stage === 'resting') {
                    statusColor = "#00aa00";
                    statusEmoji = "🧘";
                    progressStr = `
                        <div style="color:${statusColor}; font-weight:bold; font-size:1.1em; margin: 10px 0;">
                            ${statusEmoji} SISTEMA DESCANSANDO / LIMPIANDO RAM...
                        </div>
                        <div style="font-size:0.9em; color:#666;">(Segmento ${details.current}/${details.total})</div>
                    `;
                } else if (details.stage === 'generating') {
                    statusColor = "#ff00ff";
                    statusEmoji = "🚀";
                    let subInfo = "";
                    if (details.sub_current && details.sub_total) {
                        subInfo = `<br><small style="font-size:0.8em;">Fragmento interno ${details.sub_current} de ${details.sub_total}</small>`;
                    }
                    progressStr = `
                        <div style="color:${statusColor}; font-weight:bold; font-size:1.1em; margin: 10px 0;">
                            ${statusEmoji} PROCESANDO SEGMENTO ${details.current} DE ${details.total}...
                            ${subInfo}
                        </div>
                    `;
                } else if (details.stage === 'concatenating') {
                    statusColor = "#ffaa00";
                    statusEmoji = "📦";
                    progressStr = `
                        <div style="color:${statusColor}; font-weight:bold; font-size:1.1em; margin: 10px 0;">
                            ${statusEmoji} CONCATENANDO Y GUARDANDO ARCHIVO...
                        </div>
                    `;
                } else if (details.current && details.total) {
                    progressStr = `<div style="color:#ff00ff; font-weight:bold; font-size:1.1em; margin: 10px 0;">🚀 PROCESANDO SEGMENTO ${details.current} DE ${details.total}...</div>`;
                }

                let timeStr = `${minutes}m ${seconds}s`;
                
                // ETA Calculation
                let etaStr = ``;
                const completedSegments = parseInt(details.current) - 1;
                const totalSegments = parseInt(details.total);

                if (completedSegments > 0 && totalSegments) {
                    const secondsPerSegment = elapsed / completedSegments;
                    const remainingSegments = totalSegments - completedSegments;
                    const etaSeconds = Math.floor(secondsPerSegment * remainingSegments);
                    if (etaSeconds > 0) {
                        const etaMins = Math.floor(etaSeconds / 60);
                        const etaSecs = etaSeconds % 60;
                        etaStr = `<div style="color:#000; font-weight:bold; margin:10px 0; background: rgba(0,255,255,0.05); padding: 8px; border-left: 4px solid #0000ff; border-top: 1px solid #0000ff; border-right: 1px solid #0000ff; border-bottom: 5px solid #0000ff;">🕒 TIEMPO RESTANTE ESTIMADO: ${etaMins}m ${etaSecs}s</div>`;
                    }
                } else if (totalSegments > 1) {
                    // During the very first fragment
                    etaStr = `<div style="color:#666; font-style:italic; margin:10px 0; padding: 5px; border-left: 3px solid #ccc;">🕒 Calculando tiempo restante... (esperando completar primer segmento)</div>`;
                }

                const currentFrag = details.current || "...";
                const totalFrags = details.total || "...";
                const displayMsg = details.message || (details.current ? `Procesando fragmento ${details.current}` : "Iniciando sistema y cargando modelo...");

                $status.show().html(`
                    <div style="background:#0000ff; color:#fff; padding:5px 10px; margin-bottom:10px; font-weight:bold; display:flex; justify-content:space-between;">
                        <span>${statusEmoji} ESTADO DEL PROCESO</span>
                        <span>FRAGMENTO ${currentFrag} de ${totalFrags}</span>
                    </div>
                    <div style="border:2px solid #0000ff; padding:15px; background:rgba(0,0,255,0.02);">
                        <div style="color:${statusColor}; font-weight:bold; font-size:1.1em; margin-bottom: 10px;">
                            ${statusEmoji} ${displayMsg.toUpperCase()}
                        </div>
                        ${progressStr}
                        ${etaStr}
                        <div style="font-size:0.95em; color:#333; margin-top:15px; padding-top:10px; border-top:2px dotted #0000ff;">
                            <b>Tiempo transcurrido:</b> ${timeStr}
                        </div>
                    </div>
                `);
                
                // Update dialogue status too if it's the one active
                $('#dialogue-status-msg').show().html($status.html());

                $btn.prop('disabled', true).text('Procesando...');
                $reset.removeClass('hidden');
                
                // Start polling if not already started
                if (!pollingInterval) startPolling();
            } else {
                // If not processing (completed, error, or never started)
                // Stop polling if active
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                }
                
                // Reset UI only if it was showing processing
                if ($btn.text() === 'Procesando...') {
                    $status.text('¡Proceso finalizado!').css('color', 'green');
                    $btn.prop('disabled', false).text('Generar Audio');
                    
                    // Also reset dialogue button if it was active
                    $('#generate-dialogue-btn').prop('disabled', false).text('Generar Diálogo');
                    $('#dialogue-status-msg').text('¡Diálogo listo!').css('color', 'green');

                    $reset.addClass('hidden');
                    loadFileList();
                }
            }
        });
    }

    let currentPath = ''; // Track current folder path
    let fullFileTree = []; // Store full tree for navigation

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
        
        // Root Drop Zone (always available for moving items)
        const $rootDrop = $('<div class="root-drop-zone" data-folder="">Arrastra aquí para mover a la raíz</div>');
        setupDropZone($rootDrop);
        $list.append($rootDrop);

        // Back Button if inside a folder
        if (currentPath !== '') {
            const $backBtn = $(`<li class="folder-item-back" style="cursor:pointer; padding: 8px; background: rgba(0,0,255,0.1); border-bottom: 2px solid #000; font-weight: bold; margin-bottom: 5px;">
                <span>⬅️ ATRÁS (RAÍZ)</span>
            </li>`);
            $backBtn.on('click', function() {
                currentPath = '';
                renderCurrentPath();
            });
            $list.append($backBtn);
        }

        // Find items for current path
        let itemsToRender = fullFileTree;
        if (currentPath !== '') {
            const parts = currentPath.split('/');
            let current = itemsToRender;
            for (const part of parts) {
                const found = current.find(i => i.type === 'folder' && i.name === part);
                if (found) current = found.children;
            }
            itemsToRender = current;
        }

        if (itemsToRender.length === 0) {
            $list.append('<li style="padding:10px; opacity:0.5;">(Carpeta vacía)</li>');
        } else {
            itemsToRender.forEach(function (item) {
                if (item.type === 'folder') {
                    const $folderRow = $(`<li class="folder-item" data-rel="${item.rel_path}" data-filename="${item.name}" draggable="true">
                        <div class="folder-item-row" style="width: 100%; justify-content: space-between;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span class="folder-icon"></span>
                                <span class="folder-name">${item.name}</span>
                            </div>
                            <span style="font-size: 10px; opacity: 0.5;">ENTRAR ➡️</span>
                        </div>
                    </li>`);
                    
                    setupDraggable($folderRow);
                    
                    // Enter folder
                    $folderRow.on('click', '.folder-item-row', function(e) {
                        e.stopPropagation();
                        currentPath = item.rel_path;
                        renderCurrentPath();
                    });

                    // Context menu for Folders
                    $folderRow.on('contextmenu', '.folder-item-row', function (e) {
                        e.preventDefault();
                        showContextMenu(e, item.rel_path, true); // true = isFolder
                    });

                    setupDropZone($folderRow.find('.folder-item-row'));
                    $list.append($folderRow);
                } else {
                    const $li = $(`<li class="file-item" draggable="true" data-filename="${item.name}" data-rel="${item.rel_path}" data-url="${item.url}">
                        <span class="file-name">${item.name}</span>
                        <span class="trash-btn" title="Eliminar">🗑️</span>
                    </li>`);

                    setupDraggable($li);

                    $li.on('click', '.file-name', function (e) {
                        if (e.ctrlKey) return;
                        const activeView = $('.nav-btn.active').data('view');
                        if (activeView === 'waveform') {
                            $('.file-item').removeClass('active-file');
                            $li.addClass('active-file');
                            activeFileUrl = item.url;
                            activeFileName = item.name;
                            loadWaveform(item.url, item.name, item.has_backup);
                        } else {
                            playAudio(item.url, item.name);
                        }
                    });

                    // Context menu for Files
                    $li.on('contextmenu', function (e) {
                        e.preventDefault();
                        showContextMenu(e, item.rel_path, false);
                    });

                    // Delete handler (trash icon)
                    $li.on('click', '.trash-btn', function(e) {
                        e.stopPropagation();
                        if (!confirm(`¿Borrar ${item.name}?`)) return;
                        deleteFile(item.rel_path);
                    });

                    $list.append($li);
                }
            });
        }
    }

    function setupDraggable($el) {
        $el.on('dragstart', function(e) {
            e.originalEvent.dataTransfer.setData('text/plain', $(this).data('rel'));
            e.originalEvent.dataTransfer.setData('item-name', $(this).data('filename'));
            $(this).addClass('is-dragging');
        });
        
        $el.on('dragover', function(e) {
            e.preventDefault();
            const rect = this.getBoundingClientRect();
            const relY = e.originalEvent.clientY - rect.top;
            $(this).removeClass('drag-over-top drag-over-bottom');
            
            if (relY < rect.height / 2) {
                $(this).addClass('drag-over-top');
            } else {
                $(this).addClass('drag-over-bottom');
            }
        });

        $el.on('dragleave', function() {
            $(this).removeClass('drag-over-top drag-over-bottom');
        });

        $el.on('dragend', function() {
            $(this).removeClass('is-dragging');
            $('.drag-over-top, .drag-over-bottom').removeClass('drag-over-top drag-over-bottom');
        });

        $el.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const draggedRel = e.originalEvent.dataTransfer.getData('text/plain');
            const draggedName = e.originalEvent.dataTransfer.getData('item-name');
            const targetName = $(this).data('filename');
            
            $(this).removeClass('drag-over-top drag-over-bottom');
            
            if (draggedRel === $(this).data('rel')) return;

            const isTop = (e.originalEvent.clientY - this.getBoundingClientRect().top) < (this.getBoundingClientRect().height / 2);
            
            reorderItems(draggedName, targetName, isTop);
        });
    }

    function reorderItems(draggedName, targetName, isTop) {
        let items = fullFileTree;
        if (currentPath !== '') {
            const parts = currentPath.split('/');
            let current = items;
            for (const part of parts) {
                const found = current.find(i => i.type === 'folder' && i.name === part);
                if (found) current = found.children;
            }
            items = current;
        }

        const draggedIdx = items.findIndex(i => i.name === draggedName);
        if (draggedIdx === -1) return;
        const itemObj = items.splice(draggedIdx, 1)[0];
        
        let targetIdx = items.findIndex(i => i.name === targetName);
        if (!isTop) targetIdx++;
        
        items.splice(targetIdx, 0, itemObj);
        
        const nameOrder = items.map(i => i.name);
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_save_order',
            nonce: voiceqwen_ajax.nonce,
            rel_path: currentPath,
            order: nameOrder
        }, function(res) {
            if (res.success) {
                renderCurrentPath();
            } else {
                alert("Error al guardar el orden: " + (res.data || "Unknown Error"));
                loadFileList();
            }
        });
    }

    function setupDropZone($el) {
        $el.on('dragenter', function(e) {
            e.preventDefault();
        });
        $el.on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('drop-hover');
        });
        $el.on('dragleave', function() {
            $(this).removeClass('drop-hover');
        });
        $el.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('drop-hover');
            
            const targetFolder = $(this).hasClass('root-drop-zone') ? '' : ($(this).closest('.folder-item').data('rel') || '');
            
            // Check for OS files
            if (e.originalEvent.dataTransfer.files && e.originalEvent.dataTransfer.files.length > 0) {
                const file = e.originalEvent.dataTransfer.files[0];
                if (!file.name.toLowerCase().endsWith('.wav')) {
                    alert('Solo se permiten archivos .wav');
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'voiceqwen_upload_os_file');
                formData.append('nonce', voiceqwen_ajax.nonce);
                formData.append('file', file);
                formData.append('target_folder', targetFolder);
                
                const originalText = $(this).text();
                $(this).text('Subiendo...'); // Update UI
                
                $.ajax({
                    url: voiceqwen_ajax.url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        if (res.success) {
                            loadFileList();
                        } else {
                            alert(res.data);
                            loadFileList();
                        }
                    },
                    error: function() {
                        alert('Error al subir el archivo');
                        loadFileList();
                    }
                });
                return;
            }

            const itemRel = e.originalEvent.dataTransfer.getData('text/plain');
            if (itemRel === targetFolder || !itemRel) return;
            
            if (targetFolder !== '' && targetFolder.startsWith(itemRel + '/')) {
                alert("No puedes mover una carpeta dentro de sí misma.");
                return;
            }

            $.post(voiceqwen_ajax.url, {
                action: 'voiceqwen_move_item',
                nonce: voiceqwen_ajax.nonce,
                item_rel: itemRel,
                target_folder: targetFolder
            }, function(res) {
                if (res.success) {
                    loadFileList();
                } else {
                    alert(res.data);
                }
            });
        });
    }

    // New Folder Event
    $(document).on('click', '#sidebar-new-folder-btn', function() {
        const name = prompt("Nombre de la nueva carpeta:");
        if (!name) return;
        
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_create_folder',
            nonce: voiceqwen_ajax.nonce,
            folder: name
        }, function(res) {
            if (res.success) {
                loadFileList();
            } else {
                alert(res.data);
            }
        });
    });

    // Prevent default context menu on CTRL+Click
    $(document).on('contextmenu', '.file-item', function (e) {
        if (e.ctrlKey) e.preventDefault();
    });

    function showContextMenu(e, relPath, isFolder = false) {
        $('.vapor-context-menu').remove();
        const $menu = $('<div class="vapor-context-menu"></div>');
        $menu.append('<div class="menu-rename">Rename</div>');
        $menu.append('<div class="menu-delete">Delete</div>');

        $menu.css({
            top: e.pageY,
            left: e.pageX
        });

        $('body').append($menu);

        $menu.on('click', '.menu-delete', function () {
            $menu.remove();
            if (confirm(`¿Borrar ${isFolder ? 'la carpeta' : 'el archivo'} "${relPath}"?`)) {
                deleteFile(relPath);
            }
        });

        $menu.on('click', '.menu-rename', function () {
            $menu.remove();
            renameFilePrompt(relPath, isFolder);
        });

        $(document).one('click', function () {
            $menu.remove();
        });
    }

    function deleteFile(relPath) {
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_delete_file',
            nonce: voiceqwen_ajax.nonce,
            filename: relPath
        }, function (response) {
            if (response.success) {
                loadFileList();
            } else {
                alert(response.data || "Error al eliminar");
            }
        });
    }

    function renameFilePrompt(relPath, isFolder = false) {
        // Find the element by rel_path
        const selector = isFolder ? `.folder-item[data-rel="${relPath}"]` : `.file-item[data-rel="${relPath}"]`;
        const $li = $(selector);
        const $nameSpan = isFolder ? $li.find('.folder-name') : $li.find('.file-name');
        
        const fileName = relPath.split('/').pop();
        const currentName = isFolder ? fileName : fileName.replace('.wav', '');
        
        const $input = $(`<input type="text" class="vapor-rename-input" value="${currentName}" style="width: 100%; margin-top: 5px;">`);

        $nameSpan.hide();
        if (isFolder) {
            $li.find('.folder-item-row').append($input);
        } else {
            $li.append($input);
        }
        $input.focus().select();

        let submitted = false;
        const finish = () => {
            if (submitted) return;
            submitted = true;
            submitRename($input.val(), relPath, isFolder);
        };

        $input.on('keyup', function (e) {
            if (e.key === 'Enter') finish();
            else if (e.key === 'Escape') loadFileList();
        });

        $input.on('blur', finish);
    }

    function submitRename(newName, oldRelPath, isFolder = false) {
        const oldName = oldRelPath.split('/').pop();
        const compareName = isFolder ? oldName : oldName.replace('.wav', '');

        if (!newName || newName === compareName) {
            loadFileList();
            return;
        }

        // Final new name
        const finalNewName = isFolder ? newName : (newName.endsWith('.wav') ? newName : newName + '.wav');
        
        // Rel path construction for rename
        // The backend expects the logic to handle paths. 
        // Currently voiceqwen_rename_file expects old_name and new_name (just filenames).
        // I need to update the backend to handle relative paths for rename.

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_rename_file',
            nonce: voiceqwen_ajax.nonce,
            old_name: oldRelPath, // Passing rel path
            new_name: finalNewName
        }, function (response) {
            if (response.success) {
                loadFileList();
            } else {
                alert(response.data);
                loadFileList();
            }
        });
    }

    function playAudio(url, filename) {
        $('#audio-container').empty(); // Clear old main container
        $('#sidebar-player').html(`
            <div class="sidebar-player-inner" style="padding: 10px; border-top: 2px dotted #0000ff; background: rgba(0,0,255,0.05);">
                <div style="font-size: 14px; margin-bottom: 5px; color: #0000ff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    Reproduciendo: ${filename}
                </div>
                <audio controls autoplay src="${url}" style="width:100%; height: 32px;"></audio>
                <div style="margin-top:5px; text-align:right;">
                    <a href="${url}" download class="vapor-btn-main" style="font-size:14px; padding:2px 10px; margin:0; width:auto; display:inline-block; border-width: 2px;">Download</a>
                </div>
            </div>
        `);
    }



    // Audio Analysis Logic
    $('#run-analysis-btn').on('click', function() {
        const $btn = $(this);
        const $loading = $('#analysis-loading');
        const $results = $('#analysis-results-container');
        const $tableBody = $('#analysis-results-body');
        const $summary = $('#analysis-summary-content');
        const $rec = $('#analysis-recommendation-content');

        $btn.prop('disabled', true).text('ANALYZING...');
        $loading.removeClass('hidden');
        $results.addClass('hidden');
        $tableBody.empty();

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_analyze_audio',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            $btn.prop('disabled', false).text('Analyze My Files');
            $loading.addClass('hidden');

            if (response.success) {
                const data = response.data;
                
                // 1. Fill Table
                data.results.forEach(function(res) {
                    if (res.error) {
                        $tableBody.append(`
                            <tr>
                                <td colspan="6" style="color:red;">Error processing ${res.filename}: ${res.error}</td>
                            </tr>
                        `);
                        return;
                    }

                    const statusClass = res.pass ? 'status-pass' : 'status-fail';
                    const statusText = res.pass ? 'PASS' : 'FAIL';
                    const notes = res.checks.join(', ') || 'Compliant';

                    $tableBody.append(`
                        <tr>
                            <td>${res.filename}</td>
                            <td>${res.duration.toFixed(2)}s</td>
                            <td style="color: ${res.peak_db > -3 ? 'red' : 'inherit'}">${res.peak_db.toFixed(2)} dB</td>
                            <td style="color: ${(res.rms_db < -23 || res.rms_db > -18) ? 'red' : 'inherit'}">${res.rms_db.toFixed(2)} dB</td>
                            <td>${res.noise_floor === -99 ? 'N/A' : res.noise_floor.toFixed(2) + ' dB'}</td>
                            <td class="${statusClass}">${statusText}<br><small style="font-size:12px; color:#666;">${notes}</small></td>
                        </tr>
                    `);
                });

                // 2. Fill Summary
                if (data.summary) {
                    const s = data.summary;
                    $summary.html(`
                        <div class="summary-item"><span class="summary-label">Total Files:</span> ${s.total_files}</div>
                        <div class="summary-item"><span class="summary-label">Compliant:</span> <span class="status-pass">${s.files_passing}</span></div>
                        <div class="summary-item"><span class="summary-label">Non-Compliant:</span> <span class="status-fail">${s.files_failing}</span></div>
                        <div class="summary-item"><span class="summary-label">Median RMS:</span> ${s.median_rms} dB</div>
                        <div class="summary-item"><span class="summary-label">Loudest:</span> ${s.loudest}</div>
                        <div class="summary-item"><span class="summary-label">Quietest:</span> ${s.quietest}</div>
                        <div class="summary-item"><span class="summary-label">Batch Consistency:</span> ${s.is_consistent ? 'YES' : 'NO (High Variance)'}</div>
                    `);

                    // 3. Fill Recommendation
                    $rec.html(`
                        <div class="recommendation-box">
                            <p><strong>Finding:</strong> ${s.recommendation_text}</p>
                            <p><strong>Suggested Next Step:</strong><br>${s.next_step}</p>
                        </div>
                    `);
                }

                $results.removeClass('hidden');
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

    // Frontend Analyze Logic
    $('#frontend-analyze-btn').on('click', function() {
        const $view = $('#id-view-analysis');
        const $loading = $('#fn-analysis-loading');
        const $results = $('#fn-analysis-results');
        const $body = $('#fn-analysis-body');
        const $summary = $('#fn-analysis-summary');
        const $rec = $('#fn-analysis-recommendation');

        // Switch view
        $('.view-pane').addClass('hidden');
        $view.removeClass('hidden');
        
        $loading.removeClass('hidden');
        $results.addClass('hidden');
        $body.empty();

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_analyze_audio',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            $loading.addClass('hidden');

            if (response.success) {
                const data = response.data;
                $results.removeClass('hidden');

                data.results.forEach(function(res) {
                    if (res.error) return;
                    const statusClass = res.pass ? 'status-pass' : 'status-fail';
                    $body.append(`
                        <tr style="border-bottom: 1px dotted #ccc;">
                            <td style="padding: 5px; font-size: 14px;">${res.filename}</td>
                            <td style="padding: 5px;">${res.peak_db.toFixed(1)}</td>
                            <td style="padding: 5px;">${res.rms_db.toFixed(1)}</td>
                            <td style="padding: 5px;" class="${statusClass}">${res.pass ? 'OK' : 'FAIL'}</td>
                        </tr>
                    `);
                });

                if (data.summary) {
                    const s = data.summary;
                    $summary.html(`
                        <div style="font-size: 18px; color: #0000ff;">BATCH SUMMARY</div>
                        <div>Total: ${s.total_files} | Passing: ${s.files_passing}</div>
                        <div>Median RMS: ${s.median_rms} dB</div>
                    `);
                    $rec.html(`
                        <div style="color: #ff00ff; font-weight: bold;">RECOMMENDATION:</div>
                        <div style="font-size: 14px;">${s.recommendation_text}</div>
                        <div style="font-size: 14px; margin-top: 5px; font-weight: bold;">👉 ${s.next_step}</div>
                    `);
                }
            } else {
                alert('Analysis failed: ' + response.data);
                $('#view-create').removeClass('hidden');
                $view.addClass('hidden');
            }
        });
    });

    // WaveSurfer Logic
    async function loadWaveform(url, filename, hasBackup = false) {
        currentWaveUrl = url;
        waveUndoStack = [];
        $('#wave-undo').addClass('hidden').text('UNDO (0)');
        
        if (hasBackup) {
            $('#wave-restore').removeClass('hidden');
        } else {
            $('#wave-restore').addClass('hidden');
        }

        $('#wave-viewer-empty').addClass('hidden');
        $('#wave-viewer-container').addClass('hidden');
        $('#wave-viewer-loading').removeClass('hidden');
        $('#waveform-title').text(filename);
        $('#wave-save').addClass('hidden'); // Reset save button

        // 1. Fetch and Decode into Master Buffer for lossless editing
        try {
            const response = await fetch(url);
            const arrayBuffer = await response.arrayBuffer();
            activeAudioBuffer = await audioCtx.decodeAudioData(arrayBuffer);
        } catch (e) {
            console.error("Decoding error:", e);
            alert("Error al cargar el audio original.");
            $('#wave-viewer-loading').addClass('hidden');
            $('#wave-viewer-empty').removeClass('hidden');
            return;
        }

        if (wavesurfer) {
            wavesurfer.destroy();
        }

        // Initialize Regions Plugin
        wsRegions = WaveSurfer.Regions.create();

        wavesurfer = WaveSurfer.create({
            container: '#waveform',
            waveColor: '#00ffff',
            progressColor: '#ff00ff',
            cursorColor: '#ffffff',
            cursorWidth: 2,
            barWidth: 2,
            barRadius: 3,
            height: 150,
            hideScrollbar: false,
            normalize: false,
            interact: true,
            fillParent: true,
            plugins: [
                wsRegions,
                WaveSurfer.Timeline.create({
                    container: '#wave-timeline',
                    height: 20,
                    timeInterval: 1, // Optional: customize the scale
                    primaryLabelInterval: 10,
                    secondaryLabelInterval: 5,
                    style: {
                        fontSize: '10px',
                        color: '#888'
                    }
                })
            ]
        });

        // Enable region selection on drag
        wsRegions.enableDragSelection({
            color: 'rgba(255, 0, 255, 0.2)',
        });

        // Get static delete button
        let $deleteBtn = $('#wave-region-delete');

        wsRegions.on('region-created', (region) => {
            // Keep only one region active
            wsRegions.getRegions().forEach(r => {
                if (r !== region) r.remove();
            });
        });

        wsRegions.on('region-updated', (region) => {
            $deleteBtn.removeClass('hidden').off('click').on('click', function(e) {
                e.stopPropagation();
                deleteSegment(region.start, region.end);
                region.remove();
                $deleteBtn.addClass('hidden');
            });
        });

        // Hide delete button when clicking elsewhere safely
        wavesurfer.on('click', () => {
             wsRegions.clearRegions();
             $deleteBtn.addClass('hidden');
        });

        wavesurfer.on('ready', function() {
            $('#wave-viewer-loading').addClass('hidden');
            $('#wave-viewer-container').removeClass('hidden');

            // Initialize Zoom
            const $zoomSlider = $('#wave-zoom');
            if ($zoomSlider.length) {
                wavesurfer.zoom(Number($zoomSlider.val()));
                $zoomSlider.off('input').on('input', function(e) {
                    wavesurfer.zoom(Number(e.target.value));
                });
            }
        });

        // Add Speech - Context Menu logic
        let lastInsertTime = 0;
        $(document).on('contextmenu', '#waveform', function(e) {
            e.preventDefault();
            if (!activeAudioBuffer) return;

            // Using Viewport coordinates for FIXED positioning
            const x = e.clientX;
            const y = e.clientY;
            
            const $waveform = $(this);
            const waveOffset = $waveform.offset();
            const waveX = e.pageX - waveOffset.left;
            const width = $waveform.width();
            lastInsertTime = (waveX / width) * wavesurfer.getDuration();

            // Boundary checks to keep modal inside the viewport
            let finalX = x;
            let finalY = y;
            if (finalX + 330 > window.innerWidth) finalX = window.innerWidth - 340;
            if (finalY + 450 > window.innerHeight) finalY = window.innerHeight - 460;

            // Position and show modal
            $('#wave-mini-modal').removeClass('hidden').css({
                left: Math.max(0, finalX) + 'px',
                top: Math.max(0, finalY) + 'px'
            });
            $('#mini-status').text('');
            $('#mini-text').val('').focus();
        });

        $(document).on('click', '#mini-modal-close', function() {
            $('#wave-mini-modal').addClass('hidden');
        });

        $(document).on('input', '#mini-stability', function() {
            $('#mini-stability-val').text($(this).val());
        });

        $(document).on('click', '#mini-generate-btn', async function() {
            const voice = $('input[name="mini-voice"]:checked').val();
            const text = $('#mini-text').val();
            const stability = $('#mini-stability').val();
            const $btn = $(this);
            const $status = $('#mini-status');

            if (!text) {
                alert("Escribe algo para insertar.");
                return;
            }

            $btn.prop('disabled', true).text('GENERATING...');
            $status.text('Solicitando audio...');

            const formData = new FormData();
            formData.append('action', 'voiceqwen_generate_audio');
            formData.append('nonce', voiceqwen_ajax.nonce);
            formData.append('voice', voice);
            formData.append('stability', stability);
            formData.append('text', text);

            $.ajax({
                url: voiceqwen_ajax.url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if (res.success && res.data.status === 'processing') {
                        $status.text('Generando (polling)...');
                        pollMiniInsertion(res.data.file_url, $btn, $status);
                    } else {
                        $status.text('Error: ' + res.data);
                        $btn.prop('disabled', false).text('GENERATE & INSERT');
                    }
                },
                error: function() {
                    $status.text('Error de red.');
                    $btn.prop('disabled', false).text('GENERATE & INSERT');
                }
            });
        });

        async function pollMiniInsertion(fileUrl, $btn, $status) {
            const check = async () => {
                try {
                    const response = await fetch(fileUrl, { method: 'HEAD' });
                    if (response.ok) {
                        $status.text('Insertando en la onda...');
                        await handleInsertion(fileUrl);
                        $('#wave-mini-modal').addClass('hidden');
                        $btn.prop('disabled', false).text('GENERATE & INSERT');
                    } else {
                        setTimeout(check, 2000);
                    }
                } catch (e) {
                    setTimeout(check, 2000);
                }
            };
            check();
        }

        async function handleInsertion(url) {
            try {
                // Fetch and decode the new clip
                const response = await fetch(url);
                const arrayBuffer = await response.arrayBuffer();
                const insertBuffer = await audioCtx.decodeAudioData(arrayBuffer);

                // Perform the insertion
                const newBuffer = await insertAudioAt(activeAudioBuffer, insertBuffer, lastInsertTime);
                
                // Push current to undo
                waveUndoStack.push(activeAudioBuffer);
                activeAudioBuffer = newBuffer;

                // Update UI
                const blob = audioBufferToWav(activeAudioBuffer);
                const blobUrl = URL.createObjectURL(blob);
                currentWaveUrl = blobUrl;
                wavesurfer.load(blobUrl);

                $('#wave-save').removeClass('hidden');
                $('#wave-undo').removeClass('hidden').text(`UNDO (${waveUndoStack.length})`);
                alert("¡Audio insertado correctamente!");
            } catch (e) {
                console.error("Insertion error:", e);
                alert("Error al insertar el fragmento de audio.");
            }
        }

        async function insertAudioAt(orig, insert, time) {
            const sampleRate = orig.sampleRate;
            const frameStart = Math.floor(time * sampleRate);
            const newLength = orig.length + insert.length;
            const newBuffer = audioCtx.createBuffer(orig.numberOfChannels, newLength, sampleRate);

            for (let i = 0; i < orig.numberOfChannels; i++) {
                const chan = newBuffer.getChannelData(i);
                const origChan = orig.getChannelData(i);
                const insertChan = i < insert.numberOfChannels ? insert.getChannelData(i) : insert.getChannelData(0); // fallback if mono

                // 1. Before insert
                chan.set(origChan.subarray(0, frameStart));
                // 2. Insert
                chan.set(insertChan, frameStart);
                // 3. After insert
                chan.set(origChan.subarray(frameStart), frameStart + insert.length);
            }
            return newBuffer;
        }

        wavesurfer.on('error', function(err) {
            alert('Error cargando la onda: ' + err);
            $('#wave-viewer-loading').addClass('hidden');
            $('#wave-viewer-empty').removeClass('hidden');
        });

        // Add a "click to seek" behavior which is default but making sure it works
        wavesurfer.load(url);
    }

    $(document).on('click', '#wave-play', function() {
        if (wavesurfer) wavesurfer.play();
    });

    $(document).on('click', '#wave-pause', function() {
        if (wavesurfer) wavesurfer.pause();
    });

    $(document).on('click', '#wave-stop', function() {
        if (wavesurfer) {
            wavesurfer.stop();
        }
    });

    $(document).on('click', '#wave-undo', function() {
        if (waveUndoStack.length > 0) {
            // Restore actual AudioBuffer data
            activeAudioBuffer = waveUndoStack.pop();
            
            // Re-render Preview
            const blob = audioBufferToWav(activeAudioBuffer);
            currentWaveUrl = URL.createObjectURL(blob);
            wavesurfer.load(currentWaveUrl);
            
            if (waveUndoStack.length === 0) {
                $(this).addClass('hidden');
            }
            $(this).text(`UNDO (${waveUndoStack.length})`);
        }
    });

    $(document).on('click', '#wave-restore', function() {
        if (!confirm('¿Restaurar a la versión original generada? Se perderán todos los recortes realizados.')) return;
        
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_restore_original',
            nonce: voiceqwen_ajax.nonce,
            filename: activeFileName
        }, function(res) {
            if (res.success) {
                // Reload the waveform with the original URL (bypass cache by adding timestamp)
                const freshUrl = activeFileUrl + '?t=' + new Date().getTime();
                loadWaveform(freshUrl, activeFileName, false);
                alert('Restaurado correctamente.');
            } else {
                alert('Error al restaurar: ' + res.data);
            }
        });
    });

    $(document).on('click', '#wave-save', async function() {
        if (!activeAudioBuffer) return;

        const $btn = $(this);
        $btn.prop('disabled', true).text('SAVING...');

        try {
            // Encode the current Master Buffer directly (Lossless from memory)
            const wavBlob = audioBufferToWav(activeAudioBuffer);

            const formData = new FormData();
            formData.append('action', 'voiceqwen_save_edited_audio');
            formData.append('nonce', voiceqwen_ajax.nonce);
            formData.append('filename', activeFileName);
            formData.append('audio', wavBlob, activeFileName);

            $.ajax({
                url: voiceqwen_ajax.url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('¡Cambios guardados con éxito!');
                        $btn.addClass('hidden');
                        if (response.data.has_backup) {
                            $('#wave-restore').removeClass('hidden');
                        }
                    } else {
                        alert('Error al guardar: ' + response.data);
                    }
                    $btn.prop('disabled', false).text('SAVE EDITS');
                },
                error: function() {
                    alert('Error de red al guardar.');
                    $btn.prop('disabled', false).text('SAVE EDITS');
                }
            });
        } catch(e) {
            alert('Error de procesamiento local.');
            $btn.prop('disabled', false).text('SAVE EDITS');
        }
    });

    function deleteSegment(start, end) {
        if (!activeAudioBuffer) return;

        const frameStart = Math.floor(start * activeAudioBuffer.sampleRate);
        const frameEnd = Math.floor(end * activeAudioBuffer.sampleRate);
        const frameCount = activeAudioBuffer.length - (frameEnd - frameStart);

        if (frameCount <= 0) return;

        // Push current high-quality buffer to Undo Stack
        waveUndoStack.push(activeAudioBuffer);

        // Create new AudioBuffer from sliced data
        const newBuffer = audioCtx.createBuffer(
            activeAudioBuffer.numberOfChannels,
            frameCount,
            activeAudioBuffer.sampleRate
        );

        for (let i = 0; i < activeAudioBuffer.numberOfChannels; i++) {
            const chanData = activeAudioBuffer.getChannelData(i);
            const newChanData = newBuffer.getChannelData(i);
            
            // Slice 1: Before cut
            newChanData.set(chanData.subarray(0, frameStart));
            
            // Slice 2: After cut
            newChanData.set(chanData.subarray(frameEnd), frameStart);
        }

        // Update Master Buffer
        activeAudioBuffer = newBuffer;

        // Generate Preview for WaveSurfer
        const blob = audioBufferToWav(activeAudioBuffer);
        const url = URL.createObjectURL(blob);
        currentWaveUrl = url;
        
        wavesurfer.load(url);
        $('#wave-save').removeClass('hidden'); // Show save button after edit
        $('#wave-undo').removeClass('hidden').text(`UNDO (${waveUndoStack.length})`);
    }

    // Helper: AudioBuffer to Standard 16-bit PCM WAV Blob
    function audioBufferToWav(buffer) {
        let numOfChan = buffer.numberOfChannels,
            length = buffer.length * numOfChan * 2 + 44, // 2 bytes for 16-bit
            bufferArr = new ArrayBuffer(length),
            view = new DataView(bufferArr),
            channels = [], i, sample,
            offset = 0,
            pos = 0;

        function setUint16(data) {
            view.setUint16(pos, data, true);
            pos += 2;
        }

        function setUint32(data) {
            view.setUint32(pos, data, true);
            pos += 4;
        }

        // write WAVE header
        setUint32(0x46464952);                         // "RIFF"
        setUint32(length - 8);                         // file length - 8
        setUint32(0x45564157);                         // "WAVE"

        setUint32(0x20746d66);                         // "fmt " chunk
        setUint32(16);                                 // length = 16
        setUint16(1);                                  // 1 = PCM (uncompressed)
        setUint16(numOfChan);
        setUint32(Math.round(buffer.sampleRate));
        setUint32(Math.round(buffer.sampleRate) * 2 * numOfChan);  // avg. bytes/sec
        setUint16(numOfChan * 2);                      // block-align
        setUint16(16);                                 // 16-bit

        setUint32(0x61746164);                         // "data" - chunk
        setUint32(length - pos - 4);                   // chunk length

        for(i = 0; i < buffer.numberOfChannels; i++)
            channels.push(buffer.getChannelData(i));

        while(pos < length) {
            for(i = 0; i < numOfChan; i++) {
                sample = Math.max(-1, Math.min(1, channels[i][offset])); // clamp
                sample = (sample < 0 ? sample * 0x8000 : sample * 0x7FFF); // scale to 16-bit signed int
                view.setInt16(pos, sample, true); 
                pos += 2;
            }
            offset++;
        }

        return new Blob([bufferArr], {type: "audio/wav"});
    }

    // Added Stability Slider UI Handlers
    $(document).on('input', '#tts-stability', function() {
        $('#stability-val').text($(this).val());
    });
    $(document).on('input', '#dialogue-stability', function() {
        $('#dialogue-stability-val').text($(this).val());
    });

    $(document).on('click', '.nav-btn-back', function() {
        const view = $(this).data('view');
        $('.view-pane').addClass('hidden');
        $(`#view-${view}`).removeClass('hidden');
    });

    // Consolidated Initial Load
    loadVoices();
    loadFileList();
    checkBackgroundStatus();
});

