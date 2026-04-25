<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="vapor-window main view-pane view-container hidden" id="view-waveform">
    <div class="vapor-window-header" style="display: flex; justify-content: space-between; align-items: center; padding-right: 15px;">
        <div style="display: flex; align-items: center;">
            <div class="vapor-dots"><span></span><span></span><span></span></div>
            <div class="vapor-window-title">WAVEFORM VISUALIZER</div>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button id="wave-sync-r2" class="nav-btn hidden" style="width: auto; margin: 0; padding: 2px 12px; font-size: 14px; background: #27c93f; color: #fff; border: 2px solid #000; display: flex; align-items: center; gap: 5px;">
                <span class="material-symbols-outlined" style="font-size: 18px;">cloud_upload</span>
                UPLOAD
            </button>
            <button id="toggle-sidebar-btn" class="nav-btn" style="width: auto; margin: 0; padding: 2px 12px; font-size: 14px; background: #0000ff; color: #fff; border: 2px solid #000;">FILES</button>
        </div>
    </div>
    <div class="vapor-pane">
        <div id="wave-viewer-empty" style="text-align: center; padding: 50px; color: #0000ff; border: 2px dashed #0000ff; background: rgba(0,0,255,0.05);">
            <div style="font-size: 40px; margin-bottom: 10px;">📡</div>
            Selecciona un archivo del panel izquierdo para visualizar su frecuencia.
        </div>
        <div id="wave-viewer-loading" class="hidden" style="text-align: center; padding: 50px; color: #ff00ff;">
            <div class="vapor-dots" style="justify-content: center; margin-bottom: 10px;"><span></span><span></span><span></span></div>
            CALCULANDO ONDAS...
        </div>
        <div id="wave-viewer-container" class="hidden">
            <div id="waveform-title" style="margin-bottom: 10px; font-weight: bold; color: #ff00ff; font-size: 20px;"></div>
            <div id="waveform" style="background: #0d0d2b; border: 3px solid #0000ff; margin-bottom: 0; position: relative; min-height: 128px;"></div>
            <div id="wave-timeline"></div>
            <div id="wave-controls" style="display: flex; gap: 15px; align-items: center; justify-content: center; padding: 10px; background: rgba(0,0,255,0.05); border: 2px solid #0000ff; flex-wrap: wrap;">
                <button id="wave-play-pause" type="button" class="nav-btn wave-control-btn" style="width: auto; margin: 0; min-width: 100px;">PLAY</button>
                <button id="wave-region-delete" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #ff00ff; color: #fff; font-weight: bold; border: 2px solid #000;">DELETE SELECTION</button>
                <button id="wave-undo" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #ffaa00; color: #000; font-weight: bold; border: 2px solid #000;">UNDO (-1)</button>
                <button id="wave-restore" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #ff4444; color: #fff; font-weight: bold; border: 2px solid #000;">RESTORE ORIGINAL</button>
                <button id="wave-save" type="button" class="nav-btn hidden" style="width: auto; margin: 0; background: #00ffff; color: #000; font-weight: bold; border: 2px solid #000;">SAVE EDITS</button>
            </div>
            <div style="margin-top: 15px; text-align: center; padding-bottom: 20px;">
                <span style="font-size: 12px; font-weight: bold; margin-right: 15px;">ZOOM</span>
                <input type="range" id="wave-zoom" min="10" max="1000" value="10" style="width: 80%; display: inline-block; vertical-align: middle;">
            </div>
        </div>
    </div>
</div>
