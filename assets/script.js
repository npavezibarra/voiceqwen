jQuery(document).ready(function ($) {
    let pollingInterval = null;

    // Load initial file list and check for background jobs
    loadFileList();
    checkBackgroundStatus();

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

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_audio');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('voice', voice);

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
    $('.nav-btn').on('click', function() {
        const view = $(this).data('view');
        $('.nav-btn').removeClass('active');
        $(this).addClass('active');
        
        $('.view-pane').addClass('hidden');
        $(`#view-${view}`).removeClass('hidden');

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
                });
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
                const startTime = response.data.details.time;
                const elapsed = Math.floor((Date.now() / 1000) - startTime);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 100; // corrected from % 60 to % 60 in my thought but logic says elapsed % 60
                
                let timeStr = `${minutes}m ${elapsed % 60}s`;
                $status.show().html(`
                    <div style="font-weight:bold; margin-bottom:5px; color:#0000ff;">
                        ⚡ Generando con Qwen3-TTS 1.7B...
                    </div>
                    <div style="font-size:0.9em; color:#555;">
                        Tiempo transcurrido: ${timeStr}<br>
                        (El modelo es pesado, ten paciencia. El archivo aparecerá solo al finalizar).
                    </div>
                `);
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
                    $reset.addClass('hidden');
                    loadFileList();
                }
            }
        });
    }

    function loadFileList() {
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_list_files',
            nonce: voiceqwen_ajax.nonce
        }, function (response) {
            if (response.success) {
                const $list = $('#file-list');
                $list.empty();
                if (response.data.length === 0) {
                    $list.append('<li>(No hay archivos)</li>');
                } else {
                    response.data.forEach(function (file) {
                        const $li = $(`<li class="file-item" data-filename="${file.name}">
                            <span class="file-name">${file.name}</span>
                            <span class="trash-btn" title="Eliminar">🗑️</span>
                        </li>`);

                        $li.on('click', '.file-name', function (e) {
                            if (e.ctrlKey) return; 
                            playAudio(file.url, file.name);
                        });

                        // Delete handler
                        $li.on('click', '.trash-btn', function(e) {
                            e.stopPropagation();
                            if (!confirm(`¿Borrar ${file.name}?`)) return;
                            
                            $.post(voiceqwen_ajax.url, {
                                action: 'voiceqwen_delete_file',
                                nonce: voiceqwen_ajax.nonce,
                                filename: file.name
                            }, function(res) {
                                if (res.success) {
                                    loadFileList();
                                } else {
                                    alert(res.data);
                                }
                            });
                        });

                        // CTRL + Click for Context Menu
                        $li.on('mousedown', function (e) {
                            if (e.ctrlKey) {
                                e.preventDefault();
                                showContextMenu(e, file.name);
                            }
                        });

                        $list.append($li);
                    });
                }
            }
        });
    }

    // Prevent default context menu on CTRL+Click
    $(document).on('contextmenu', '.file-item', function (e) {
        if (e.ctrlKey) e.preventDefault();
    });

    function showContextMenu(e, filename) {
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
            if (confirm('¿Borrar "' + filename + '"?')) {
                deleteFile(filename);
            }
        });

        $menu.on('click', '.menu-rename', function () {
            $menu.remove();
            renameFilePrompt(filename);
        });

        $(document).one('click', function () {
            $menu.remove();
        });
    }

    function deleteFile(filename) {
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_delete_file',
            nonce: voiceqwen_ajax.nonce,
            filename: filename
        }, function (response) {
            if (response.success) loadFileList();
        });
    }

    function renameFilePrompt(filename) {
        const $li = $(`.file-item[data-filename="${filename}"]`);
        const $nameSpan = $li.find('.file-name');
        const currentName = filename.replace('.wav', '');
        const $input = $(`<input type="text" class="vapor-rename-input" value="${currentName}">`);

        $nameSpan.hide();
        $li.append($input);
        $input.focus().select();

        $input.on('keyup', function (e) {
            if (e.key === 'Enter') {
                submitRename($input.val(), filename);
            } else if (e.key === 'Escape') {
                loadFileList();
            }
        });

        $input.on('blur', function () {
            submitRename($input.val(), filename);
        });
    }

    function submitRename(newName, oldName) {
        if (!newName || newName + '.wav' === oldName) {
            loadFileList();
            return;
        }

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_rename_file',
            nonce: voiceqwen_ajax.nonce,
            old_name: oldName,
            new_name: newName
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

    // Initial load
    loadFileList();
    checkBackgroundStatus(); 

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

    $(document).on('click', '.nav-btn-back', function() {
        const view = $(this).data('view');
        $('.view-pane').addClass('hidden');
        $(`#view-${view}`).removeClass('hidden');
    });
});

