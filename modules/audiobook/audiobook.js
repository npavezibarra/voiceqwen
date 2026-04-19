jQuery(document).ready(function($) {
    console.log("Audiobook Module Trace: Initializing Tiered Management...");

    let currentBookId = null;
    let currentChapterId = null;
    let activeAuthor = '';
    let activeBookTitle = '';

    // --- INITIALIZATION ---
    loadAudiobooks();

    // Re-load on view switch
    $(document).on('click', '.nav-btn[data-view="audiobook"]', function() {
        loadAudiobooks();
    });

    // --- BOOK MANAGEMENT ---
    
    function loadAudiobooks() {
        const $list = $('#audiobook-list');
        $list.html('<li class="loading">Cargando libros...</li>');

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_audiobook_get_books',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            if (response.success) {
                $list.empty();
                if (response.data.length === 0) {
                    $list.append('<li class="empty-hint">Sin libros creados</li>');
                } else {
                    response.data.forEach(book => {
                        const activeClass = (currentBookId == book.id) ? 'active' : '';
                        const $li = $(`<li class="${activeClass}" data-id="${book.id}" data-title="${book.title}" data-author="${book.author}">
                            <div class="book-item">
                                <span class="book-title">${book.title}</span>
                                <small style="display:block; opacity:0.6;">${book.author}</small>
                            </div>
                        </li>`);
                        $list.append($li);
                    });
                }
            }
        });
    }

    $(document).on('click', '#add-book-btn', function() {
        $('#book-create-form').slideToggle();
    });

    $(document).on('click', '#confirm-book-btn', function() {
        const $titleInp = $('#new-book-title');
        const $authorInp = $('#new-book-author');
        const title = $titleInp.val().trim();
        const author = $authorInp.val().trim();
        const $btn = $(this);

        if (!title) {
            $titleInp.css('border-color', 'red');
            return;
        }

        $btn.prop('disabled', true).text('...');
        $titleInp.prop('disabled', true);
        $authorInp.prop('disabled', true);

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_audiobook_create_book',
            nonce: voiceqwen_ajax.nonce,
            title: title,
            author: author
        }, function(response) {
            $btn.prop('disabled', false).text('OK');
            $titleInp.prop('disabled', false);
            $authorInp.prop('disabled', false);

            if (response.success) {
                $titleInp.val('');
                $authorInp.val('');
                $('#book-create-form').slideUp();
                
                // Physical folder creation
                const folderName = sanitizeForFolder(`${title}-${author}`);
                $.post(voiceqwen_ajax.url, {
                    action: 'voiceqwen_create_folder',
                    nonce: voiceqwen_ajax.nonce,
                    folder: folderName
                });

                loadAudiobooks();
            } else {
                alert('Error: ' + response.data);
            }
        }).fail(function() {
            alert('Error de red al crear libro');
            $btn.prop('disabled', false).text('OK');
        });
    });

    $(document).on('click', '#audiobook-list li', function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        const author = $(this).data('author');
        
        $('#audiobook-list li').removeClass('active');
        $(this).addClass('active');
        
        currentBookId = id;
        activeBookTitle = title;
        activeAuthor = author;

        // Update physical path
        const folderName = sanitizeForFolder(`${title}-${author}`);
        if (window.VoiceQwen) {
            window.VoiceQwen.setPath(folderName);
            window.VoiceQwen.loadFiles();
        }

        $('#add-chapter-btn').prop('disabled', false);
        loadChapters(id);
        
        // UI Clean up
        $('#audiobook-editor-ui').addClass('hidden');
        $('#editor-empty-hint').show();
        $('#chapter-create-form').hide();
    });

    // --- CHAPTER MANAGEMENT ---

    function loadChapters(bookId) {
        const $list = $('#chapter-list');
        $list.html('<li class="loading">Cargando...</li>');

        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_audiobook_get_chapters',
            nonce: voiceqwen_ajax.nonce,
            book_id: bookId
        }, function(response) {
            if (response.success) {
                $list.empty();
                if (response.data.length === 0) {
                    $list.append('<li class="empty-hint">Sin capítulos. Pulsa +</li>');
                } else {
                    response.data.forEach(ch => {
                        const activeClass = (currentChapterId == ch.id) ? 'active' : '';
                        const $li = $(`<li class="${activeClass}" data-id="${ch.id}" data-title="${ch.title}">
                            <span>${ch.title}</span>
                        </li>`);
                        $li.data('content', ch.content);
                        $list.append($li);
                    });
                }
            }
        });
    }

    $(document).on('click', '#add-chapter-btn', function() {
        $('#chapter-create-form').slideToggle();
    });

    $(document).on('click', '#confirm-chapter-btn', function() {
        const $inp = $('#new-chapter-title');
        const title = $inp.val().trim();
        const $btn = $(this);

        if (!title || !currentBookId) return;

        $btn.prop('disabled', true).text('...');
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_audiobook_create_chapter',
            nonce: voiceqwen_ajax.nonce,
            book_id: currentBookId,
            title: title
        }, function(response) {
            $btn.prop('disabled', false).text('ADD');
            if (response.success) {
                $inp.val('');
                $('#chapter-create-form').slideUp();
                loadChapters(currentBookId);
            } else {
                alert('Error: ' + response.data);
            }
        });
    });

    $(document).on('click', '#chapter-list li', function() {
        const id = $(this).data('id');
        const title = $(this).data('title');
        const content = $(this).data('content');

        $('#chapter-list li').removeClass('active');
        $(this).addClass('active');

        currentChapterId = id;
        $('#active-chapter-title').text(title);
        $('#audiobook-text').val(content);
        
        $('#editor-empty-hint').hide();
        $('#audiobook-editor-ui').removeClass('hidden');
        
        // Pre-fill the generated filename title
        $('#audiobook-title').val(title);
    });

    // --- EDITOR LOGIC ---

    $(document).on('click', '#save-chapter-btn', function() {
        if (!currentChapterId) return;
        const content = $('#audiobook-text').val();
        const $btn = $(this);
        
        $btn.text('Guardando...');
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_audiobook_save_chapter',
            nonce: voiceqwen_ajax.nonce,
            chapter_id: currentChapterId,
            content: content
        }, function(response) {
            $btn.text('GUARDAR CAMBIOS');
            // Update cached content in the list
            $(`#chapter-list li[data-id="${currentChapterId}"]`).data('content', content);
        });
    });

    $(document).on('input', '#audiobook-stability', function() {
        $('#audiobook-stability-val').text($(this).val());
    });

    $(document).on('click', '#generate-audiobook-btn', function() {
        const voice = $('input[name="audiobook-voice"]:checked').val();
        const text = $('#audiobook-text').val();
        const chapterTitle = $('#active-chapter-title').text();
        const stability = $('#audiobook-stability').val();
        const $btn = $(this);
        const $status = $('#audiobook-status-msg');

        if (!text) {
             $status.text('Error: Texto vacío').css('color', 'red');
             return;
        }

        // Auto-save before generating
        $('#save-chapter-btn').click();

        // New Naming Convention: {booktitle}-{chapter}
        const fullTitle = `${activeBookTitle}-${chapterTitle}`;
        const cleanFullTitle = sanitizeForFolder(fullTitle);

        const formData = new FormData();
        formData.append('action', 'voiceqwen_generate_audio');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('voice', voice);
        formData.append('text', text);
        formData.append('stability', stability);
        formData.append('audiobook_title', cleanFullTitle); // Pass the composite name

        $btn.prop('disabled', true).text('Procesando...');
        $status.text('Iniciando sistema...').css('color', 'blue').show();

        // Trigger global polling if available
        if (window.VoiceQwen && window.VoiceQwen.startPolling) {
            window.VoiceQwen.currentJobSource = 'audiobook';
            window.VoiceQwen.startPolling();
        }

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $status.text('Generando audio...').css('color', 'green');
                    if (typeof startPolling === 'function') startPolling();
                } else {
                    $status.text('Error: ' + response.data).css('color', 'red');
                    $btn.prop('disabled', false).text('GENERAR AUDIO');
                }
            }
        });
    });

    // Voice population listener
    $(document).on('voiceqwen_voices_loaded', function(e, voices) {
        const $selector = $('#audiobook-voice-selector');
        if (!$selector.length) return;
        $selector.empty();
        voices.forEach((voice, index) => {
            const checked = index === 0 ? 'checked' : '';
            const html = `
                <label class="avatar-radio">
                    <input type="radio" name="audiobook-voice" value="${voice.id}" ${checked}>
                    <div class="avatar-circle" data-voice="${voice.id}" style="background-image: url('${voice.avatar}');"></div>
                    <span class="avatar-name">${voice.name}</span>
                </label>`;
            $selector.append(html);
        });
    });

    // Helper: Sanitization
    function sanitizeForFolder(str) {
        return str.toLowerCase()
                  .normalize("NFD")
                  .replace(/[\u0300-\u036f]/g, "")
                  .replace(/[^a-z0-9]/g, '-')
                  .replace(/-+/g, '-')
                  .replace(/^-|-$/g, '');
    }
});
