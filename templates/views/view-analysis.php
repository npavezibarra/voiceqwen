<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="vapor-window main view-pane hidden" id="id-view-analysis">
    <div class="vapor-window-header">
        <div class="vapor-dots"><span></span><span></span><span></span></div>
        <div class="vapor-window-title">AUDIO QUALITY REPORT</div>
    </div>
    
    <div id="fn-analysis-loading" class="hidden" style="text-align: center; padding: 40px;">
        <div class="vapor-dots" style="justify-content: center; margin-bottom: 15px;"><span></span><span></span><span></span></div>
        <p style="font-size: 24px;">RUNNING QC ENGINE...</p>
    </div>

    <div id="fn-analysis-results" class="hidden">
        <div class="vapor-pane" style="max-height: 400px; overflow-y: auto;">
            <table class="fn-report-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 3px solid #000; text-align: left; color: #0000ff;">
                        <th style="padding: 5px;">File</th>
                        <th style="padding: 5px;">Peak</th>
                        <th style="padding: 5px;">RMS</th>
                        <th style="padding: 5px;">Status</th>
                    </tr>
                </thead>
                <tbody id="fn-analysis-body"></tbody>
            </table>
        </div>
        
        <div class="vapor-pane" style="border-top: 3px solid #0000ff; background: rgba(255,0,255,0.05);">
            <div id="fn-analysis-summary"></div>
            <div id="fn-analysis-recommendation" style="margin-top: 15px; padding: 10px; border: 2px dashed #ff00ff;"></div>
        </div>
    </div>

    <div class="controls" style="padding: 10px;">
        <button class="nav-btn-back" data-view="create" style="background:#fff; border:2px solid #000; color:#000; padding:5px 10px; cursor:pointer;">← BACK TO CREATE</button>
    </div>
</div>
