/**
 * VoiceQwen Audiobook - UI Module
 */
window.VoiceQwen = window.VoiceQwen || {};

window.VoiceQwen.UI = {
    addChapterToList: function(card, track, storage = 'r2') {
        const list = card.find('.vq-chapters-list');
        list.find('.vq-no-chapters').remove();

        if (!track.key) {
            storage = 'text';
        }

        let badge = '';
        let playBtn = `<button class="vq-inline-play" data-key="${track.key || ''}" data-storage="${storage}"><span class="material-symbols-outlined">play_circle</span></button>`;
        
        if (storage === 'text') {
            badge = `<span class="vq-badge vq-badge-text">TEXT</span> <button class="vq-chapter-voice" title="Generate Speech" data-text-key="${track.text_key || ''}"><span class="material-symbols-outlined">mic</span></button>`;
            playBtn = ''; 
        } else if (storage === 'local') {
            badge = `<span class="vq-badge vq-badge-local">LOCAL</span> <button class="vq-chapter-edit" title="Edit Audio" data-key="${track.key}"><span class="material-symbols-outlined">graphic_eq</span></button> <button class="vq-sync-btn" title="Sync to Cloudflare R2" data-key="${track.key}"><span class="material-symbols-outlined">cloud_upload</span></button>`;
        } else {
            badge = '<span class="vq-badge vq-badge-r2">R2</span>';
        }

        // Check if track already exists (Match & Merge)
        let $existingItem = list.find(`.vq-chapter-item[data-id="${track.id}"]`);
        if (!$existingItem.length && track.id) {
            // Fallback: try matching by title if ID is not found
            list.find('.vq-chapter-item').each(function() {
                if ($(this).find('.vq-chapter-title').val() === track.title) {
                    $existingItem = $(this);
                    return false;
                }
            });
        }

        const itemHtml = `
            <span class="vq-drag-handle">≡</span>
            <input type="text" class="vq-chapter-title" value="${track.title}" placeholder="Chapter Title">
            <div class="vq-chapter-actions">
                ${badge}
                ${playBtn}
                <button class="vq-remove-track" title="Remove"><span class="material-symbols-outlined">delete_forever</span></button>
            </div>
        `;

        if ($existingItem.length) {
            $existingItem.attr('data-key', track.key || '');
            $existingItem.attr('data-text-key', track.text_key || '');
            $existingItem.html(itemHtml);
        } else {
            const newItem = `<li class="vq-chapter-item" data-key="${track.key || ''}" data-text-key="${track.text_key || ''}" data-id="${track.id || ''}">${itemHtml}</li>`;
            list.append(newItem);
        }
        
        window.VoiceQwen.AJAX.savePlaylist(list.data('id'));
    },

    updateUploadProgress: function(container, percent, statusText) {
        const bar = container.find('.vq-progress-bar');
        const status = container.find('.vq-upload-status');
        container.show();
        if (percent !== null) bar.css('width', percent + '%');
        if (statusText) status.text(statusText);
    }
};
