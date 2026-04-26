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
                <label class="mini-label">ESTABILIDAD: <span id="mini-stability-val">0.5</span></label>
                <input type="range" id="mini-stability" min="0.1" max="1.0" step="0.1" value="0.5">
            </div>
            <div>
                <label class="mini-label" style="color: #00ffff;">PALABRAS/SEGM: <span id="mini-max-words-val">40</span></label>
                <input type="range" id="mini-max-words" min="10" max="60" step="5" value="40" style="accent-color: #00ffff;">
            </div>
            <div>
                <label class="mini-label" style="color: #ffff00;">PAUSA: <span id="mini-pause-time-val">0.1</span>s</label>
                <input type="range" id="mini-pause-time" min="0.1" max="2.0" step="0.1" value="0.1" style="accent-color: #ffff00;">
            </div>
        </div>
        
        <button id="mini-generate-btn" class="vapor-btn-main mini-btn">GENERATE & INSERT</button>
    </div>
</div>

<!-- Simple Floating Text Panel (Similar to ADD SPEECH) -->
<div id="wave-text-panel" class="vapor-window mini-modal hidden" style="width: 450px; height: 550px; position: fixed; top: 100px; right: 50px; z-index: 99999;">
    <div class="vapor-window-header mini-header">
        <div class="vapor-dots"><span></span><span></span><span></span></div>
        <div class="vapor-window-title mini-title">CHAPTER TEXT</div>
        <button id="wave-text-panel-close" class="mini-close">×</button>
    </div>
    <div class="vapor-pane mini-pane" style="height: calc(100% - 40px); display: flex; flex-direction: column; gap: 10px;">
        <textarea id="wave-text-panel-content" style="flex-grow: 1; width: 100%; padding: 10px; resize: none; font-family: monospace; font-size: 13px;"></textarea>
        <button id="wave-text-panel-save" class="vapor-btn-main mini-btn" style="background: #000 !important; color: #fff !important; width: 100%;">SAVE CHANGES</button>
    </div>
</div>
