jQuery(document).ready(function ($) {
    function getActivePostId($scope) {
        const $card = ($scope && $scope.length) ? $scope.closest('.vq-card') : $('.vq-card').first();
        const postId = Number($card.data('id') || 0);
        return postId > 0 ? postId : 0;
    }

    function saveAuthor($input) {
        const postId = getActivePostId($input);
        if (!postId) return;

        const author = String($input.val() || '').trim();
        $input.prop('disabled', true);

        $.ajax({
            url: voiceqwen_ajax.url,
            type: 'POST',
            data: {
                action: 'vq_update_book_author',
                nonce: voiceqwen_ajax.nonce,
                post_id: postId,
                author: author
            }
        }).done(function (res) {
            if (!res || !res.success) {
                $input.prop('disabled', false);
                alert('Error: ' + (res && res.data ? res.data : 'Failed to save'));
                return;
            }

            const saved = (res.data && typeof res.data.author === 'string') ? res.data.author : author;
            const $wrap = $input.parent();
            $input.remove();
            if (saved) {
                $wrap.append('<div class="vq-card-author"></div>');
                $wrap.find('.vq-card-author').text(saved);
            }

            // Update sidebar list item too (no reload needed).
            const $item = $('.vq-book-item[data-id="' + postId + '"]');
            if ($item.length) {
                const $authorEl = $item.find('.vq-book-item-author');
                if ($authorEl.length) {
                    $authorEl.text(saved);
                } else if (saved) {
                    $item.find('.vq-book-item-content').append('<span class="vq-book-item-author"></span>');
                    $item.find('.vq-book-item-author').text(saved);
                }
            }
        }).fail(function () {
            $input.prop('disabled', false);
            alert('Network error.');
        });
    }

    $(document).on('keydown', '.vq-card-author-input', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        saveAuthor($(this));
    });

    $(document).on('blur', '.vq-card-author-input', function () {
        saveAuthor($(this));
    });
});
