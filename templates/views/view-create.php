<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="vapor-window main view-pane" id="view-create">
    <div class="vapor-window-header">
        <div class="vapor-dots"><span></span><span></span><span></span></div>
        <div class="vapor-window-title">SELECCIONA TU CHILENO FAVORITO</div>
    </div>
    
    <div class="voice-selector" id="dynamic-voice-selector">
        <p>Cargando voces...</p>
    </div>
    
    <div class="vapor-tabs">
        <button class="vapor-tab active" data-tab="textarea">Texto</button>
        <button class="vapor-tab" data-tab="upload">Archivo .txt</button>
    </div>

    <div class="vapor-pane" id="pane-textarea">
        <textarea id="tts-text" placeholder="Escribe el texto aquí..."></textarea>
    </div>

    <div class="vapor-pane hidden" id="pane-upload">
        <div class="upload-box">
            <label for="tts-file">Seleccionar archivo .txt:</label>
            <input type="file" id="tts-file" accept=".txt">
        </div>
    </div>

    <div class="stability-control" style="margin: 15px 0; padding: 10px; background: #fcfcfc; border: 1px solid #ccc;">
        <div style="display: flex; gap: 20px; flex-wrap: wrap;">
            <!-- Stability -->
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 11px;">ESTABILIDAD: <span id="stability-val">0.7</span></label>
                <input type="range" id="tts-stability" min="0.1" max="1.0" step="0.1" value="0.7" style="width: 100%; cursor: pointer; accent-color: #555;">
                <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 5px; color: #666;"><span>EXPRESIVO</span><span>ESTABLE</span></div>
            </div>
            <!-- Max Words -->
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 11px;">MAX PALABRAS/SEGM.: <span id="max-words-val">30</span></label>
                <input type="range" id="tts-max-words" min="10" max="60" step="5" value="30" style="width: 100%; cursor: pointer; accent-color: #555;">
                <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 5px; color: #666;"><span>CORTOS</span><span>LARGOS</span></div>
            </div>
            <!-- Pause Time -->
            <div style="flex: 1; min-width: 200px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 11px;">PAUSA ENTRE SEGM.: <span id="pause-time-val">0.5</span>s</label>
                <input type="range" id="tts-pause-time" min="0.1" max="2.0" step="0.1" value="0.5" style="width: 100%; cursor: pointer; accent-color: #555;">
                <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 5px; color: #666;"><span>RÁPIDO</span><span>LENTO</span></div>
            </div>
        </div>
    </div>

    <div class="controls">
        <button id="generate-btn" class="vapor-btn-main">Generar Audio</button>
    </div>

    <div id="audio-container"></div>
</div>
