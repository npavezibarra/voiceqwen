<?php
/**
 * Admin template for Audio Analysis page.
 * Uses the $deps variable passed from the controller.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="vapor-grid-bg"></div>
<div class="wrap vapor-container" style="margin-top: 30px;">
    <div class="vapor-header">
        <div class="vapor-dots">
            <span></span><span></span><span></span>
        </div>
        <div class="vapor-title">AUDIO ANALYSIS ENGINE</div>
    </div>

    <div class="vapor-window main">
        <div class="vapor-window-header">
            <div class="vapor-dots"><span></span><span></span><span></span></div>
            <div class="vapor-window-title">System Status</div>
        </div>
        <div class="vapor-pane">
            <div class="system-check">
                <p>
                    <strong>ffmpeg detected:</strong> 
                    <span style="color: <?php echo $deps['ffmpeg'] ? '#00ff00' : '#ff0000'; ?>">
                        <?php echo $deps['ffmpeg'] ? 'YES (' . $deps['paths']['ffmpeg'] . ')' : 'NO'; ?>
                    </span>
                </p>
                <p>
                    <strong>ffprobe detected:</strong> 
                    <span style="color: <?php echo $deps['ffprobe'] ? '#00ff00' : '#ff0000'; ?>">
                        <?php echo $deps['ffprobe'] ? 'YES (' . $deps['paths']['ffprobe'] . ')' : 'NO'; ?>
                    </span>
                </p>
            </div>
            
            <div style="margin-top: 20px;">
                <p>This engine analyzes your generated WAV files against audiobook technical standards (RMS -23 to -18 dB, Peak < -3 dB).</p>
                <button id="run-analysis-btn" class="vapor-btn-main" style="width: auto; margin: 0; padding: 10px 40px;">
                    Analyze My Files
                </button>
            </div>
        </div>
    </div>

    <div id="analysis-loading" class="hidden" style="text-align: center; margin: 20px 0;">
        <div class="vapor-window" style="display: inline-block; padding: 20px;">
            <div class="vapor-dots" style="justify-content: center;"><span></span><span></span><span></span></div>
            <p style="margin-top: 10px; font-family: 'VT323', monospace; font-size: 20px;">ANALYZING BITSTREAMS...</p>
        </div>
    </div>

    <div id="analysis-results-container" class="hidden" style="margin-top: 30px;">
        <div class="vapor-window main">
            <div class="vapor-window-header">
                <div class="vapor-dots"><span></span><span></span><span></span></div>
                <div class="vapor-window-title">File Report</div>
            </div>
            <div class="vapor-pane" style="overflow-x: auto;">
                <table class="vapor-report-table" style="width: 100%; border-collapse: collapse; font-family: 'VT323', monospace;">
                    <thead>
                        <tr style="border-bottom: 3px solid #0000ff; text-align: left; font-size: 20px; color: #0000ff;">
                            <th style="padding: 10px;">File Name</th>
                            <th style="padding: 10px;">Duration</th>
                            <th style="padding: 10px;">Peak</th>
                            <th style="padding: 10px;">RMS</th>
                            <th style="padding: 10px;">Noise Floor</th>
                            <th style="padding: 10px;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-results-body" style="font-size: 18px;">
                        <!-- JS injected rows -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="vapor-body" style="margin-top: 30px;">
            <div class="vapor-window main">
                <div class="vapor-window-header">
                    <div class="vapor-dots"><span></span><span></span><span></span></div>
                    <div class="vapor-window-title">Batch Summary</div>
                </div>
                <div class="vapor-pane" id="analysis-summary-content">
                    <!-- JS injected summary -->
                </div>
            </div>

            <div class="vapor-window sidebar" style="width: 350px;">
                <div class="vapor-window-header">
                    <div class="vapor-dots"><span></span><span></span><span></span></div>
                    <div class="vapor-window-title">Normalization Recommendation</div>
                </div>
                <div class="vapor-pane" id="analysis-recommendation-content">
                    <!-- JS injected recommendation -->
                </div>
            </div>
        </div>
    </div>

    <div class="vapor-deco-text">QC</div>
</div>

<style>
.vapor-container {
    background: transparent;
}
.vapor-report-table td {
    padding: 10px;
    border-bottom: 1px dotted #0000ff;
}
.vapor-report-table tr:last-child td {
    border-bottom: none;
}
.status-pass { color: #008800; font-weight: bold; }
.status-fail { color: #ff0000; font-weight: bold; }
.summary-item { margin-bottom: 10px; font-size: 18px; }
.summary-label { color: #0000ff; }
.recommendation-box { background: rgba(0, 0, 255, 0.05); padding: 10px; border: 1px solid #0000ff; }
</style>
