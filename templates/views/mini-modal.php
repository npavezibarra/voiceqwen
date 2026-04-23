<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!-- Add Speech Mini Modal - TRULY FLOATING (OUTSIDE VIEWERS) -->
<div id="wave-mini-modal" class="vapor-window mini-modal hidden">
    <!-- Custom Resize Handles -->
    <div class="resize-handle-e"></div>
    <div class="resize-handle-s"></div>
    <div class="resize-handle-se"></div>

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
        
        <div class="stability-control-mini" style="display: flex; flex-direction: column; gap: 8px;">
            <div>
                <label class="mini-label">ESTABILIDAD: <span id="mini-stability-val">0.7</span></label>
                <input type="range" id="mini-stability" min="0.1" max="1.0" step="0.1" value="0.7">
            </div>
            <div>
                <label class="mini-label" style="color: #00ffff;">PALABRAS/SEGM: <span id="mini-max-words-val">30</span></label>
                <input type="range" id="mini-max-words" min="10" max="60" step="5" value="30" style="accent-color: #00ffff;">
            </div>
            <div>
                <label class="mini-label" style="color: #ffff00;">PAUSA: <span id="mini-pause-time-val">0.5</span>s</label>
                <input type="range" id="mini-pause-time" min="0.1" max="2.0" step="0.1" value="0.5" style="accent-color: #ffff00;">
            </div>
        </div>
        
        <button id="mini-generate-btn" class="vapor-btn-main mini-btn">GENERATE & INSERT</button>
    </div>
</div>
