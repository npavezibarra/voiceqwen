/**
 * VoiceQwen Audiobook - Player Module
 */
window.VoiceQwen = window.VoiceQwen || {};

window.VoiceQwen.Player = {
    activeWavesurfer: null,

    playTrack: function(btn, key, storage, postId) {
        const card = btn.closest('.vq-card');
        const chapterItem = btn.closest('.vq-chapter-item');
        const playerContainer = card.find('.vq-inline-player');
        
        if (this.activeWavesurfer && btn.hasClass('loaded')) {
            this.activeWavesurfer.playPause();
            return;
        }

        if (this.activeWavesurfer) {
            this.activeWavesurfer.destroy();
            jQuery('.vq-inline-play').text('▶').removeClass('loaded');
            jQuery('.vq-inline-player').hide();
        }

        playerContainer.insertAfter(chapterItem);
        btn.text('⌛');

        jQuery.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_get_track_url',
                nonce: voiceqwen_ajax.nonce,
                key: key,
                storage: storage,
                post_id: postId
            },
            success: (response) => {
                if (response.success) {
                    this.initWavesurfer(response.data, playerContainer, btn, postId);
                } else {
                    alert('Error: ' + response.data);
                    btn.text('▶');
                }
            }
        });
    },

    initWavesurfer: function(url, container, btn, postId) {
        const wavesurferEl = container.find('.vq-wavesurfer-preview')[0];
        container.show();
        
        this.activeWavesurfer = WaveSurfer.create({
            container: wavesurferEl,
            waveColor: '#ff00ff',
            progressColor: '#00ffff',
            cursorColor: '#fff',
            barWidth: 2,
            height: 40,
            backend: 'MediaElement'
        });

        this.activeWavesurfer.load(url);

        this.activeWavesurfer.on('ready', () => {
            this.activeWavesurfer.play();
            btn.text('⏸').addClass('loaded');
            this.updateTime(container);
            
            const total = this.formatTime(this.activeWavesurfer.getDuration());
            const item = btn.closest('.vq-chapter-item');
            if (item.attr('data-duration') === '00:00' || !item.attr('data-duration')) {
                item.attr('data-duration', total);
                window.VoiceQwen.AJAX.savePlaylist(postId);
            }
        });

        this.activeWavesurfer.on('play', () => btn.text('⏸'));
        this.activeWavesurfer.on('pause', () => btn.text('▶'));
        this.activeWavesurfer.on('audioprocess', () => this.updateTime(container));
        this.activeWavesurfer.on('finish', () => btn.text('▶'));
    },

    updateTime: function(container) {
        if (!this.activeWavesurfer) return;
        const current = this.formatTime(this.activeWavesurfer.getCurrentTime());
        const total = this.formatTime(this.activeWavesurfer.getDuration());
        container.find('.vq-preview-time').text(`${current} / ${total}`);
    },

    formatTime: function(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }
};
