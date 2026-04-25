<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
    <style>
        :root {
            --finder-bg: #f6f6f6;
            --finder-sidebar: #e8e8e8;
            --finder-border: #dcdcdc;
            --finder-accent: #007aff;
            --finder-text: #333;
        }

        body {
            margin: 0;
            padding: 0;
            background: #fff;
            font-family: 'Poppins', sans-serif;
            color: #fff;
            overflow-x: hidden;
        }

        .audi-top-bar {
            height: 80px;
            width: 100%;
            background: #fff;
            display: flex;
            align-items: center;
            padding: 0 40px;
            box-sizing: border-box;
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .top-bar-nav {
            display: flex;
            gap: 30px;
        }

        .nav-item-btn {
            background: none;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 1.5px;
            cursor: pointer;
            color: #000;
            text-transform: uppercase;
            padding: 10px 0;
            position: relative;
            opacity: 0.6;
            transition: opacity 0.3s;
        }

        .nav-item-btn.active {
            opacity: 1;
        }

        .nav-item-btn.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: #000;
        }

        /* View Containers */
        .view-container {
            padding: 40px;
            min-height: calc(100vh - 80px);
            display: none;
        }

        .view-container.active {
            display: block;
        }

        /* Finder Interface */
        .finder-window {
            background: var(--finder-bg);
            border-radius: 12px;
            height: 75vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            color: var(--finder-text);
            box-shadow: 0 30px 100px rgba(0,0,0,0.8);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .finder-toolbar {
            height: 52px;
            background: #efefef;
            display: flex;
            align-items: center;
            padding: 0 16px;
            border-bottom: 1px solid var(--finder-border);
            gap: 20px;
        }

        .finder-traffic-lights {
            display: flex;
            gap: 8px;
            width: 70px;
        }

        .light {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .red { background: #ff5f56; }
        .yellow { background: #ffbd2e; }
        .green { background: #27c93f; }

        .finder-nav-controls {
            display: flex;
            gap: 15px;
            color: #888;
        }

        .finder-title {
            flex-grow: 1;
            text-align: center;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            font-size: 13px;
            color: #555;
        }

        .finder-columns-container {
            flex-grow: 1;
            display: flex;
            overflow-x: auto;
            background: #fff;
        }

        .finder-column {
            min-width: 260px;
            border-right: 1px solid #f0f0f0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            padding: 8px 0;
        }

        .finder-item {
            display: flex;
            align-items: center;
            padding: 8px 16px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            white-space: nowrap;
            gap: 10px;
            transition: background 0.1s;
            margin: 0 8px;
            border-radius: 4px;
        }

        .finder-item:hover {
            background: #f0f0f0;
        }

        .finder-item.active {
            background: var(--finder-accent);
            color: #fff;
        }

        .finder-item .icon {
            font-size: 16px;
        }

        .finder-item .arrow {
            margin-left: auto;
            font-size: 10px;
            opacity: 0.5;
        }

        /* File Preview */
        .finder-preview {
            min-width: 400px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            background: #fff;
        }

        .preview-icon {
            width: 120px;
            height: 120px;
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .preview-name {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 8px;
            word-break: break-all;
        }

        .preview-meta {
            font-size: 12px;
            color: #999;
            margin-bottom: 30px;
        }

        .preview-player {
            width: 100%;
            max-width: 300px;
        }

        /* Custom Audio Player Style */
        audio {
            height: 35px;
            border-radius: 20px;
        }

        /* Audiobook Widget Overrides */
        #view-audiobook.hidden { display: block !important; opacity: 1 !important; visibility: visible !important; position: relative !important; }
        .audiobook-manager-wrap { background: #fff !important; border: none !important; border-radius: 12px; overflow: hidden; }
        .vapor-window { border: none !important; box-shadow: none !important; background: transparent !important; }
        .audiobook-split { background: #fff !important; }
        .audiobook-list-column { background: #fcfcfc !important; color: #000 !important; border-right: 1px solid #eee !important; }
        .audiobook-list-header { background: #f5f5f5 !important; color: #000 !important; }
        .vq-book-item:hover, .vq-book-item.active { background: #fff !important; border-left-color: #000 !important; }
        .vq-book-item-title { color: #000 !important; }
        .audiobook-editor-column { background: #fff !important; }
        .welcome-content h3 { color: #000 !important; }

        /* Compatibility for Vaporwave views on Audi page */
        .view-container.vapor-window {
            background: #fff !important;
            color: #000 !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1) !important;
            border: 1px solid #eee !important;
            border-radius: 12px !important;
            margin-top: 20px;
            display: none;
        }
        .view-container.vapor-window.active {
            display: block !important;
        }
        .view-container .vapor-window-header {
            background: #f5f5f5 !important;
            color: #000 !important;
            border-bottom: 1px solid #eee !important;
            border-radius: 12px 12px 0 0 !important;
        }
        .view-container .vapor-window-title { color: #000 !important; }
        .view-container .vapor-pane { background: #fff !important; color: #000 !important; }
        .view-container #wave-viewer-empty { border-color: #eee !important; color: #999 !important; background: #fafafa !important; }
    </style>
</head>
<body <?php body_class(); ?>>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        
        <!-- Top Navigation -->
        <div class="audi-top-bar">
            <div class="top-bar-nav">
                <button id="nav-audiobook" class="nav-item-btn active">AUDIOBOOK</button>
                <button id="nav-files" class="nav-item-btn">FILES</button>
                <button id="nav-config" class="nav-item-btn">CONFIG</button>
            </div>
        </div>

        <!-- View: Audiobook -->
        <div id="view-audio-manager" class="view-container active">
            <?php if ( is_user_logged_in() ) : ?>
                <?php 
                if (function_exists('voiceqwen_audiobook_render_ui')) {
                    voiceqwen_audiobook_render_ui(); 
                }
                ?>
            <?php else : ?>
                <div class="finder-window" style="height:auto; min-height:320px; justify-content:center; align-items:center; color:#333; text-align:center; padding:40px;">
                    <div>
                        <h2 style="color:#000; margin-bottom:12px;">Login Required</h2>
                        <p style="color:#666; max-width:520px; margin:0 auto;">Debes iniciar sesion para usar Audiobook Manager y el editor de archivos/audio.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- View: Files (Finder) -->
        <div id="view-finder" class="view-container">
            <?php if ( is_user_logged_in() ) : ?>
                <div class="finder-window">
                    <div class="finder-toolbar">
                        <div class="finder-traffic-lights">
                            <span class="light red"></span>
                            <span class="light yellow"></span>
                            <span class="light green"></span>
                        </div>
                        <div class="finder-nav-controls">
                            <span>〈</span>
                            <span>〉</span>
                        </div>
                        <div class="finder-title" id="finder-current-path">ROOT / UPLOADS</div>
                    </div>
                    <div class="finder-columns-container" id="finder-columns">
                        <!-- Columns injected here -->
                    </div>
                </div>
            <?php else : ?>
                <div class="finder-window" style="height:auto; min-height:320px; justify-content:center; align-items:center; color:#333; text-align:center; padding:40px;">
                    <div>
                        <h2 style="color:#000; margin-bottom:12px;">Login Required</h2>
                        <p style="color:#666; max-width:520px; margin:0 auto;">Debes iniciar sesion para explorar archivos o editar audiobooks.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- View: Waveform Editor (Shared) -->
        <!-- Wrapped so we can apply a black/white UI variant for /audi without affecting the main LOCUTOR UI -->
        <div class="vq-bw-waveform">
            <div class="voiceqwen-theme-90ties">
                <?php include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/views/view-waveform.php'; ?>
                <?php include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/views/view-settings.php'; ?>
            </div>
            <?php include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/views/mini-modal.php'; ?>
        </div>

        <?php
        while ( have_posts() ) :
            the_post();
            the_content();
        endwhile;
        ?>
    </main>
</div>

<?php wp_footer(); ?>

<script>
jQuery(document).ready(function($) {
    console.log("Finder UI Initialized");

    // --- Navigation Switcher ---
    $('#nav-audiobook').on('click', function() {
        $('.nav-item-btn').removeClass('active');
        $(this).addClass('active');
        $('.view-container').removeClass('active').addClass('hidden').hide();
        $('#view-audio-manager').addClass('active').removeClass('hidden').show();
    });

    $('#nav-files').on('click', function() {
        $('.nav-item-btn').removeClass('active');
        $(this).addClass('active');
        $('.view-container').removeClass('active').addClass('hidden').hide();
        $('#view-finder').addClass('active').removeClass('hidden').show();
        loadFiles();
    });

    $('#nav-config').on('click', function() {
        $('.nav-item-btn').removeClass('active');
        $(this).addClass('active');
        $('.view-container, .view-pane').removeClass('active').addClass('hidden').hide();
        $('#view-settings').addClass('active').removeClass('hidden').show();
        $('#view-settings').css({ 'display': 'block', 'opacity': 1, 'visibility': 'visible' });
    });

    // --- Finder Logic ---
    const finderColumns = $('#finder-columns');

    function loadFiles() {
        console.log("Fetching files...");
        finderColumns.html('<div class="finder-column"><div style="padding:20px; font-size:12px; color:#999;">Loading...</div></div>');
        
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_list_files',
            nonce: voiceqwen_ajax.nonce
        }, function(response) {
            console.log("Files received:", response);
            if (response.success) {
                renderColumn(response.data, 0);
            } else {
                finderColumns.html('<div class="finder-column"><div style="padding:20px; color:red;">Error loading files.</div></div>');
            }
        });
    }

    function renderColumn(items, index) {
        // Clear columns to the right
        finderColumns.find('.finder-column').each(function(i) {
            if (i >= index) $(this).remove();
        });

        const column = $('<div class="finder-column"></div>');
        
        if (items.length === 0) {
            column.append('<div style="padding:20px; font-size:12px; color:#999; font-style:italic;">Empty</div>');
        }

        items.forEach(item => {
            const icon = item.type === 'folder' ? '📁' : '🎵';
            const arrow = item.type === 'folder' ? '<span class="arrow">›</span>' : '';
            const div = $(`
                <div class="finder-item">
                    <span class="icon">${icon}</span>
                    <span class="name">${item.name}</span>
                    ${arrow}
                </div>
            `);
            
            div.on('click', function() {
                column.find('.finder-item').removeClass('active');
                div.addClass('active');

                if (item.type === 'folder') {
                    renderColumn(item.children, index + 1);
                    $('#finder-current-path').text(item.rel_path);
                } else {
                    // File selection
                    finderColumns.find('.finder-column').each(function(i) {
                        if (i > index) $(this).remove();
                    });
                    renderPreview(item, index + 1);
                }
            });

            column.append(div);
        });

        finderColumns.append(column);
        
        // Scroll to end
        finderColumns.animate({
            scrollLeft: finderColumns[0].scrollWidth
        }, 300);
    }

    function renderPreview(item, index) {
        const preview = $(`
            <div class="finder-column finder-preview">
                <div class="preview-icon">🎵</div>
                <div class="preview-name">${item.name}</div>
                <div class="preview-meta">${item.rel_path}</div>
                <div class="preview-player">
                    <audio controls src="${item.url}" style="width: 100%;"></audio>
                </div>
            </div>
        `);
        finderColumns.append(preview);
        finderColumns.animate({
            scrollLeft: finderColumns[0].scrollWidth
        }, 300);
    }
});
</script>

</body>
</html>
