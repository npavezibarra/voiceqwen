jQuery(document).ready(function ($) {
    $('.avatar-circle').on('click', function (e) {
        e.preventDefault();
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
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const size = Math.min(img.width, img.height, 400);
                canvas.width = size; canvas.height = size;
                const s = Math.min(img.width, img.height);
                ctx.drawImage(img, (img.width - s) / 2, (img.height - s) / 2, s, s, 0, 0, size, size);

                let quality = 0.9;
                let dataUrl = canvas.toDataURL('image/jpeg', quality);
                while (dataUrl.length > 330000 && quality > 0.1) {
                    quality -= 0.1;
                    dataUrl = canvas.toDataURL('image/jpeg', quality);
                }

                $.post(voiceqwen_ajax.url, {
                    action: 'voiceqwen_update_avatar',
                    nonce: voiceqwen_ajax.nonce,
                    voice: voice,
                    image: dataUrl
                }, (res) => {
                    if (res.success) $(`.avatar-circle[data-voice="${voice}"]`).css('background-image', `url(${dataUrl})`);
                });
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
});
