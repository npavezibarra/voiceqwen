<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!-- View: Audiobook Manager -->
<div class="vapor-window main view-pane hidden" id="view-audiobook">
    <div class="audiobook-manager-wrap">
        <div class="audiobook-header">
            <div class="header-left">
                <span class="vapor-icon">🎧</span>
                <h2>AUDIOBOOK MANAGER</h2>
            </div>
            <div class="header-right">
                <button id="vq-import-book-btn" class="nav-btn">IMPORT</button>
                <button id="vq-create-book-btn" class="nav-btn">+ CREATE NEW</button>
                <input type="file" id="vq-import-file-input" style="display:none;" accept=".json">
            </div>
        </div>

        <div class="audiobook-split">
            <!-- Sidebar: List of Audiobooks -->
            <div class="audiobook-list-column">
                <div class="audiobook-list-header">
                    <h3>ALBUMS / BOOKS</h3>
                </div>
                <div id="vq-book-list-container" class="audiobook-list-container">
                    <!-- Populated via PHP or AJAX -->
                    <?php
                    $books = get_posts(array(
                        'post_type' => 'audiobook',
                        'posts_per_page' => -1,
                        'post_status' => 'publish'
                    ));
                    if (empty($books)): ?>
                        <p class="no-books">No audiobooks found.</p>
                    <?php else: ?>
                        <?php foreach ($books as $book): 
                            $author = get_post_meta($book->ID, '_vq_author', true);
                            $cover_url = \VoiceQwen\Audiobook\AudiobookManager::get_cover_url($book->ID);
                        ?>
                            <div class="vq-book-item" data-id="<?php echo $book->ID; ?>">
                                <div class="vq-book-item-thumb">
                                    <?php if ($cover_url): ?>
                                        <img src="<?php echo esc_url($cover_url); ?>" alt="">
                                    <?php else: ?>
                                        <div class="vq-thumb-placeholder"></div>
                                    <?php endif; ?>
                                </div>
                                <div class="vq-book-item-content">
                                    <span class="vq-book-item-title"><?php echo esc_html($book->post_title); ?></span>
                                    <span class="vq-book-item-author"><?php echo esc_html($author); ?></span>
                                </div>
                                <div class="vq-book-item-arrow">›</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Panel: Editor -->
            <div id="vq-editor-panel" class="audiobook-editor-column">
                <div class="welcome-state">
                    <div class="welcome-content">
                        <span class="welcome-icon">💿</span>
                        <h3>Select an audiobook to edit</h3>
                        <p>Double-click on an item from the list to reveal its chapters and settings.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Book Modal -->
        <div id="vq-book-modal" class="vapor-modal hidden">
            <div class="vapor-modal-content">
                <h3>New Audiobook</h3>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="vq-new-book-title" placeholder="Book Title...">
                </div>
                <div class="form-group">
                    <label>Author</label>
                    <input type="text" id="vq-new-book-author" placeholder="Author Name...">
                </div>
                <div class="modal-actions">
                    <button id="vq-modal-close" class="nav-btn">Cancel</button>
                    <button id="vq-modal-confirm" class="vapor-btn-main">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
