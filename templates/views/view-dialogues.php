<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="vapor-window main view-pane hidden" id="view-dialogues">
    <div class="vapor-window-header">
        <div class="vapor-dots"><span></span><span></span><span></span></div>
        <div class="vapor-window-title">MULTI-VOICE DIALOGUES</div>
    </div>
    
    <div class="vapor-pane">
        <div class="vapor-window help-box" style="margin-bottom: 20px; border-style: dashed; background: rgba(0,0,255,0.02);">
            <div class="vapor-window-header" style="height: 30px; padding: 5px 10px; background: rgba(0,0,255,0.1); border-bottom: 1px dashed #0000ff;">
                <div class="vapor-window-title" style="font-size: 14px;">📖 GUÍA DE DIÁLOGOS</div>
            </div>
            <div style="padding: 15px; font-size: 16px; line-height: 1.4;">
                <div style="margin-bottom: 10px;">
                    <strong>FORMATO:</strong> Envuelve cada fragmento con el nombre del personaje. 
                    <br><code style="background: #fff; border: 1px solid #0000ff; padding: 2px 5px; font-size: 14px;">[Nombre]Texto del diálogo...[/Nombre]</code>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong>TIP:</strong> Puedes hacer clic en los nombres de abajo para insertar la etiqueta automáticamente.
                </div>
                <div id="dialogue-voice-chips" style="display: flex; flex-wrap: wrap; gap: 8px;">
                    <span style="opacity: 0.5;">Cargando personajes disponibles...</span>
                </div>
            </div>
        </div>
        
        <textarea id="dialogue-text" placeholder="[Fernando]Hola Alodia, ¿cómo estás?[/Fernando] [Alodia Corral]¡Muy bien Fernando! Estamos al aire...[/Alodia Corral]" style="height: 200px;"></textarea>
        
        <div class="stability-control" style="margin: 15px 0; padding: 10px; background: #fcfcfc; border: 1px solid #ccc;">
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Stability -->
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 11px;">ESTABILIDAD: <span id="dialogue-stability-val">0.7</span></label>
                    <input type="range" id="dialogue-stability" min="0.1" max="1.0" step="0.1" value="0.7" style="width: 100%; cursor: pointer; accent-color: #555;">
                    <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 5px; color: #666;"><span>EXPRESIVO</span><span>ESTABLE</span></div>
                </div>
                <!-- Max Words -->
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 11px;">MAX PALABRAS/SEGM.: <span id="dialogue-max-words-val">30</span></label>
                    <input type="range" id="dialogue-max-words" min="10" max="60" step="5" value="30" style="width: 100%; cursor: pointer; accent-color: #555;">
                    <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 5px; color: #666;"><span>CORTOS</span><span>LARGOS</span></div>
                </div>
                <!-- Pause Time -->
                <div style="flex: 1; min-width: 200px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px; color: #333; font-size: 11px;">PAUSA GLOBAL: <span id="dialogue-pause-time-val">0.5</span>s</label>
                    <input type="range" id="dialogue-pause-time" min="0.1" max="2.0" step="0.1" value="0.5" style="width: 100%; cursor: pointer; accent-color: #555;">
                    <div style="display: flex; justify-content: space-between; font-size: 9px; margin-top: 5px; color: #666;"><span>RÁPIDO</span><span>LENTO</span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="controls">
        <button id="generate-dialogue-btn" class="vapor-btn-main">Generar Diálogo</button>
    </div>
</div>
