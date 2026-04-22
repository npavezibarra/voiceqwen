<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="vapor-window sidebar">
    <div class="vapor-window-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 10px;">
        <div style="display: flex; align-items: center;">
            <div class="vapor-dots"><span></span><span></span><span></span></div>
            <div class="vapor-window-title">Mis Archivos</div>
        </div>
        <div style="display: flex; align-items: center; gap: 5px;">
            <button id="sidebar-new-folder-btn" class="nav-btn" style="background:#fff; border:2px solid #000; color:#000; padding: 2px 8px; font-size: 10px; height: 18px; line-height: 1;" title="Nueva Carpeta">📁+</button>
            <button id="frontend-analyze-btn" class="nav-btn" style="width: auto; margin: 0; padding: 2px 8px; font-size: 10px; height: 18px; line-height: 1;">ANALYZE</button>
        </div>
    </div>
    <ul id="file-list" class="vapor-list">
        <li class="loading">Cargando...</li>
    </ul>
    <div id="sidebar-player"></div>
</div>
