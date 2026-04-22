<!-- View 6: Audiobook -->
<div class="vapor-window main view-pane hidden" id="view-audiobook">
    <div class="audiobook-workspace">
        <!-- Col 1: Books -->
        <div class="audiobook-col book-col">
            <div class="col-header">
                <h3>LIBROS</h3>
                <button id="add-book-btn" class="nav-btn" title="Nuevo Libro">+</button>
            </div>
            
            <div id="book-create-form" class="mini-form hidden">
                <input type="text" id="new-book-title" placeholder="Título...">
                <input type="text" id="new-book-author" placeholder="Autor...">
                <button id="confirm-book-btn" class="vapor-btn-main">OK</button>
            </div>

            <ul id="audiobook-list" class="vapor-list">
                <li class="loading">Cargando libros...</li>
            </ul>
        </div>

        <!-- Col 2: Chapters -->
        <div class="audiobook-col chapter-col">
            <div class="col-header">
                <h3>CAPÍTULOS</h3>
                <button id="add-chapter-btn" class="nav-btn" title="Nuevo Capítulo" disabled>+</button>
            </div>
            
            <div id="chapter-create-form" class="mini-form hidden">
                <input type="text" id="new-chapter-title" placeholder="Ej: Capítulo 1">
                <button id="confirm-chapter-btn" class="vapor-btn-main">ADD</button>
            </div>

            <ul id="chapter-list" class="vapor-list">
                <li class="empty-hint">Selecciona un libro</li>
            </ul>
        </div>

        <!-- Col 3: Editor -->
        <div class="audiobook-col editor-col">
            <div class="col-header">
                <h3 id="active-chapter-title">EDITOR</h3>
            </div>
            
            <div id="audiobook-editor-ui" class="hidden">
                <div class="voice-selector" id="audiobook-voice-selector">
                    <!-- Populated via JS -->
                </div>

                <div class="form-group" style="margin-top:15px;">
                    <textarea id="audiobook-text" style="height: calc(100vh - 450px);" placeholder="Escribe el contenido del capítulo aquí..."></textarea>
                </div>

                <div class="stability-control" style="margin: 15px 0; padding: 10px; background: rgba(255,0,255,0.05); border: 1px solid #ff00ff;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #ff00ff;">ESTABILIDAD: <span id="audiobook-stability-val">0.7</span></label>
                    <input type="range" id="audiobook-stability" min="0.1" max="1.0" step="0.1" value="0.7" style="width: 100%; cursor: pointer;">
                </div>

                <div class="controls">
                    <button id="generate-audiobook-btn" class="vapor-btn-main">GENERAR AUDIO</button>
                    <button id="save-chapter-btn" class="nav-btn" style="margin-top: 10px; width: 100%;">GUARDAR CAMBIOS</button>
                </div>
                <div id="audiobook-status-msg"></div>
            </div>

            <div id="editor-empty-hint" class="empty-hint" style="padding: 40px; text-align: center;">
                Selecciona un capítulo para editar
            </div>
        </div>
    </div>
</div>
