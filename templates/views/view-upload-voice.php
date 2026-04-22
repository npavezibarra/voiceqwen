<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="vapor-window main view-pane hidden" id="view-upload-voice">
    <div class="vapor-window-header">
        <div class="vapor-dots"><span></span><span></span><span></span></div>
        <div class="vapor-window-title">NUEVO CHILENO FAVORITO</div>
    </div>
    
    <div class="vapor-pane">
        <form id="upload-voice-form">
            <div class="form-group">
                <label>Nombre del Personaje:</label>
                <input type="text" id="new-voice-name" placeholder="Ej: Condorito" required>
            </div>
            
            <div class="form-group">
                <label>Audio de Muestra (.wav):</label>
                <input type="file" id="new-voice-audio" accept=".wav" required>
                <small>Muestra de voz clara, idealmente 10-20 segundos.</small>
            </div>

            <div class="form-group">
                <label>Transcripción Exacta:</label>
                <textarea id="new-voice-text" placeholder="Escribe exactamente lo que dice el audio de arriba..." required></textarea>
            </div>

            <div class="form-group">
                <label>Foto de Avatar:</label>
                <input type="file" id="new-voice-avatar" accept="image/*" required>
            </div>

            <button type="submit" class="vapor-btn-main" style="margin: 20px 0 0 0; width: 100%;">GUARDAR CHILENO</button>
        </form>
        <div id="upload-status" style="margin-top: 15px;"></div>
    </div>
</div>
