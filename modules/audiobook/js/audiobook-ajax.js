/**
 * VoiceQwen Audiobook - AJAX Module
 */
window.VoiceQwen = window.VoiceQwen || {};

window.VoiceQwen.AJAX = {
    savePlaylist: function(postId) {
        const playlist = [];
        jQuery(`.vq-chapters-list[data-id="${postId}"] .vq-chapter-item`).each(function() {
            const item = jQuery(this);
            playlist.push({
                title: item.find('.vq-chapter-title').val(),
                key: item.data('key'),
                text_key: item.attr('data-text-key') || item.data('text-key'),
                duration: item.attr('data-duration') || '00:00',
                storage: item.find('.vq-inline-play').data('storage') || (item.find('.vq-badge-text').length ? 'text' : 'r2')
            });
        });

        return jQuery.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_save_playlist',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId,
                playlist: playlist
            }
        });
    },

    loadEditor: function(postId, callback) {
        return jQuery.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_get_book_editor',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId
            },
            success: callback
        });
    },

    uploadLocalChapter: function(postId, file, container, onProgress, onSuccess) {
        const formData = new FormData();
        formData.append('action', 'vq_upload_local_chapter');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('post_id', postId);
        formData.append('file', file);

        return jQuery.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", (evt) => {
                    if (evt.lengthComputable) onProgress((evt.loaded / evt.total) * 100);
                }, false);
                return xhr;
            },
            success: onSuccess
        });
    },

    syncToR2: function(postId, key, callback) {
        return jQuery.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_sync_to_r2',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId,
                key: key
            },
            success: callback
        });
    },

    uploadCover: function(postId, file, callback) {
        const formData = new FormData();
        formData.append('action', 'vq_upload_cover');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('post_id', postId);
        formData.append('file', file);
        return jQuery.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: formData, processData: false, contentType: false, success: callback });
    },

    uploadBackground: function(postId, file, callback) {
        const formData = new FormData();
        formData.append('action', 'vq_upload_background');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('post_id', postId);
        formData.append('file', file);
        return jQuery.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: formData, processData: false, contentType: false, success: callback });
    },

    uploadTextChapters: function(postId, files, callback) {
        const formData = new FormData();
        formData.append('action', 'vq_upload_text_chapters');
        formData.append('nonce', voiceqwen_ajax.nonce);
        formData.append('post_id', postId);
        for (let i = 0; i < files.length; i++) formData.append('files[]', files[i]);
        return jQuery.ajax({ url: voiceqwen_ajax.url, type: 'POST', data: formData, processData: false, contentType: false, success: callback });
    },

    createBook: function(title, author, callback) {
        return jQuery.post(voiceqwen_ajax.url, { action: 'vq_create_book', nonce: voiceqwen_ajax.nonce, title: title, author: author }, callback);
    }
};
