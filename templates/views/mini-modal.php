<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!-- Add Speech Mini Modal - TRULY FLOATING (OUTSIDE VIEWERS) -->
<div id="wave-mini-modal" class="vapor-window mini-modal hidden">
    <div class="vapor-window-header mini-header">
        <div class="vapor-dots"><span></span><span></span><span></span></div>
        <div class="vapor-window-title mini-title">ADD SPEECH</div>
        <button id="mini-modal-close" class="mini-close">×</button>
    </div>
    <div class="vapor-pane mini-pane">
        <label class="mini-label">VOICE:</label>
        <div id="mini-voice-selector" class="mini-voice-list">
            <!-- Populated via JS -->
        </div>
        
        <textarea id="mini-text" placeholder="Escribe el texto a insertar..."></textarea>
        
        <div class="stability-control-mini">
            <label class="mini-label">ESTABILIDAD: <span id="mini-stability-val">0.7</span></label>
            <input type="range" id="mini-stability" min="0.1" max="1.0" step="0.1" value="0.7">
        </div>
        
        <button id="mini-generate-btn" class="vapor-btn-main mini-btn">GENERATE & INSERT</button>
    </div>
</div>
