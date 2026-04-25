jQuery(document).ready(function ($) {
    let markers = [];
    let saveTimer = null;
    let renderTimer = null;
    let lastPoint = { clientX: 0, clientY: 0, t: 0 };
    let selectedMarkerId = '';
    let draggingMarkerId = '';
    let dragStartClientX = 0;
    let dragStartTime = 0;
    let dragStartLeftPx = 0;
    let dragCurrentVisibleLeftPx = 0;
    let lastScrollPx = 0;
    const markerPalette = ['#3b82f6', '#a855f7', '#f59e0b', '#22c55e', '#ef4444', '#06b6d4', '#e11d48'];

    function getWS() {
        return window.VoiceQwen && window.VoiceQwen.wavesurferInstance ? window.VoiceQwen.wavesurferInstance : null;
    }

    function getRelPath() {
        return window.VoiceQwen && window.VoiceQwen.activeFileRelPath ? window.VoiceQwen.activeFileRelPath : '';
    }

    function getRoot() {
        return document.getElementById('waveform');
    }

    function getWrapper() {
        const ws = getWS();
        return ws && ws.getWrapper ? ws.getWrapper() : null;
    }

    function getPxPerSec() {
        const value = Number($('#wave-zoom').val() || 10);
        return value > 0 ? value : 10;
    }

    function getMetrics() {
        const ws = getWS();
        const root = getRoot();
        const wrapper = getWrapper();
        if (!ws || !root || !wrapper) return null;

        const duration = ws.getDuration ? ws.getDuration() : 0;
        if (!duration) return null;

        const scrollPx = getScrollPx(root, wrapper);
        const viewportWidth = root.clientWidth || 0;
        const viewportHeight = root.clientHeight || 0;
        const contentWidth = Math.max(
            wrapper.scrollWidth || 0,
            wrapper.clientWidth || 0,
            Math.round(duration * getPxPerSec())
        );

        return {
            ws,
            root,
            wrapper,
            duration,
            scrollPx,
            viewportWidth,
            viewportHeight,
            contentWidth,
            pxPerSec: contentWidth / duration
        };
    }

    function timeToVisibleLeftPx(timeSeconds) {
        const metrics = getMetrics();
        if (!metrics) return 0;
        return (timeSeconds * metrics.pxPerSec) - metrics.scrollPx;
    }

    function timeToContentLeftPx(timeSeconds) {
        const metrics = getMetrics();
        if (!metrics) return 0;
        return timeSeconds * metrics.pxPerSec;
    }

    function visibleLeftPxToTime(visibleLeftPx) {
        const metrics = getMetrics();
        if (!metrics) return 0;
        const time = (visibleLeftPx + metrics.scrollPx) / metrics.pxPerSec;
        return Math.max(0, Math.min(metrics.duration, time));
    }

    function normalizeMarkerTimes() {
        const metrics = getMetrics();
        const duration = metrics ? metrics.duration : 0;
        markers.forEach((marker) => {
            marker.t = Math.max(0, Math.min(duration, Number(marker.t) || 0));
        });
    }

    function buildCandidates(rootEl) {
        if (!rootEl) return [];
        const candidates = [];
        const pushIf = (el) => {
            if (!el || el.nodeType !== 1) return;
            const sw = el.scrollWidth || 0;
            const cw = el.clientWidth || 0;
            if (sw > cw + 2) candidates.push(el);
        };

        pushIf(rootEl);
        const all = rootEl.querySelectorAll('*');
        for (let i = 0; i < all.length && i < 400; i++) pushIf(all[i]);
        return candidates;
    }

    function getScrollPx(rootEl, wrapperEl) {
        if (rootEl && wrapperEl && rootEl.getBoundingClientRect && wrapperEl.getBoundingClientRect) {
            const rootRect = rootEl.getBoundingClientRect();
            const wrapperRect = wrapperEl.getBoundingClientRect();
            const geomShift = rootRect.left - wrapperRect.left;
            if (geomShift > 0.5) return geomShift;
        }

        const candidates = buildCandidates(rootEl || wrapperEl);
        let bestScroll = 0;
        for (let i = 0; i < candidates.length; i++) {
            const scroll = candidates[i].scrollLeft || 0;
            if (scroll > bestScroll) bestScroll = scroll;
        }
        return bestScroll;
    }

    function getOverlay() {
        const root = getRoot();
        if (!root) return null;

        root.style.position = 'relative';
        root.style.overflow = 'visible';

        let overlay = root.querySelector('.vq-markers-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'vq-markers-overlay';
            overlay.style.position = 'absolute';
            overlay.style.left = '0';
            overlay.style.top = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.pointerEvents = 'none';
            overlay.style.zIndex = '40';
            overlay.style.overflow = 'visible';
            root.appendChild(overlay);
        }
        return overlay;
    }

    function getMarkerColor(marker) {
        if (marker && marker.color) return marker.color;
        return document.querySelector('.vq-bw-waveform') ? '#000000' : '#ff00ff';
    }

    function syncOverlayBox() {
        const overlay = getOverlay();
        const root = getRoot();
        if (!overlay || !root) return;
        overlay.style.width = root.clientWidth + 'px';
        overlay.style.height = root.clientHeight + 'px';
    }

    function getMarkerDomNode(id) {
        const overlay = getOverlay();
        if (!overlay || !id) return null;
        return overlay.querySelector('.vq-marker[data-id="' + String(id).replace(/"/g, '\\"') + '"]');
    }

    function renderMarkers() {
        const overlay = getOverlay();
        const metrics = getMetrics();
        if (!overlay || !metrics) return;

        syncOverlayBox();

        overlay.innerHTML = '';
        if (!metrics.viewportWidth || !metrics.viewportHeight) return;

        const viewStart = metrics.scrollPx / metrics.pxPerSec;
        const viewEnd = (metrics.scrollPx + metrics.viewportWidth) / metrics.pxPerSec;

        markers.forEach((marker) => {
            if (marker.t < viewStart - 1 || marker.t > viewEnd + 1) return;

            const x = (marker.t * metrics.pxPerSec) - metrics.scrollPx;
            const color = getMarkerColor(marker);

            const node = document.createElement('div');
            node.className = 'vq-marker';
            node.dataset.id = marker.id;
            node.dataset.t = String(marker.t);
            node.style.position = 'absolute';
            node.style.left = x + 'px';
            node.style.top = '0';
            node.style.width = '24px';
            node.style.height = metrics.viewportHeight + 'px';
            node.style.transform = 'translateX(-12px)';
            node.style.pointerEvents = 'none';
            node.style.zIndex = '45';

            const line = document.createElement('div');
            line.className = 'vq-marker-line';
            line.style.position = 'absolute';
            line.style.left = '50%';
            line.style.top = '0';
            line.style.height = '100%';
            line.style.transform = 'translateX(-1px)';
            line.style.borderLeft = '2px dashed ' + color;
            line.style.opacity = '0.95';
            line.style.pointerEvents = 'none';
            node.appendChild(line);

            const handle = document.createElement('button');
            handle.type = 'button';
            handle.className = 'vq-marker-handle';
            handle.dataset.id = marker.id;
            handle.title = marker.label || 'Marker';
            handle.style.position = 'absolute';
            handle.style.left = '50%';
            handle.style.top = '-10px';
            handle.style.width = '18px';
            handle.style.height = '14px';
            handle.style.transform = 'translateX(-9px)';
            handle.style.border = '2px solid #000';
            handle.style.borderRadius = '2px';
            handle.style.background = color;
            handle.style.boxShadow = document.querySelector('.vq-bw-waveform') ? 'none' : '0 4px 12px rgba(0,0,0,0.25)';
            handle.style.cursor = 'ew-resize';
            handle.style.pointerEvents = 'auto';
            handle.style.padding = '0';
            handle.style.touchAction = 'none';
            if (selectedMarkerId === marker.id) handle.style.outline = '2px solid #ffffff';
            node.appendChild(handle);

            if (marker.label) {
                const label = document.createElement('div');
                label.className = 'vq-marker-label';
                label.textContent = marker.label;
                label.style.position = 'absolute';
                label.style.left = '50%';
                label.style.top = '-34px';
                label.style.transform = 'translateX(-50%)';
                label.style.whiteSpace = 'nowrap';
                label.style.fontSize = '12px';
                label.style.fontWeight = '700';
                label.style.letterSpacing = '0.5px';
                label.style.color = color;
                label.style.textShadow = document.querySelector('.vq-bw-waveform') ? 'none' : '0 2px 10px rgba(0,0,0,0.35)';
                label.style.pointerEvents = 'none';
                node.appendChild(label);
            }

            overlay.appendChild(node);
        });
    }

    function scheduleRender(delay) {
        if (renderTimer) clearTimeout(renderTimer);
        renderTimer = setTimeout(renderMarkers, typeof delay === 'number' ? delay : 0);
    }

    function loadMarkers() {
        const relPath = getRelPath();
        if (!relPath) return;
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_get_markers',
            nonce: voiceqwen_ajax.nonce,
            rel_path: relPath
        }, function (res) {
            if (!res || !res.success) return;
            markers = Array.isArray(res.data) ? res.data : [];
            scheduleRender(0);
        });
    }

    function scheduleSave() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(saveMarkers, 400);
    }

    function saveMarkers() {
        const relPath = getRelPath();
        if (!relPath) return;
        $.post(voiceqwen_ajax.url, {
            action: 'voiceqwen_save_markers',
            nonce: voiceqwen_ajax.nonce,
            rel_path: relPath,
            markers: JSON.stringify(markers)
        });
    }

    function addMarker(t) {
        const id = 'm_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
        const label = prompt('Marker name (optional):', '') || '';
        const color = markerPalette[markers.length % markerPalette.length];
        markers.push({ id, t: Math.max(0, Number(t) || 0), label: label.trim(), color });
        normalizeMarkerTimes();
        markers.sort((a, b) => a.t - b.t);
        selectedMarkerId = id;
        scheduleRender(0);
        scheduleSave();
    }

    function deleteMarker(id) {
        markers = markers.filter((marker) => marker.id !== id);
        if (selectedMarkerId === id) selectedMarkerId = '';
        scheduleRender(0);
        scheduleSave();
    }

    function ensurePointMenu() {
        if (document.getElementById('wave-point-menu')) return;
        const el = document.createElement('div');
        el.id = 'wave-point-menu';
        el.className = 'vq-point-menu hidden';
        el.innerHTML = ''
            + '<button type="button" class="vq-point-btn" data-action="voice">VOICE</button>'
            + '<button type="button" class="vq-point-btn" data-action="marker">MARKER</button>'
            + '<button type="button" class="vq-point-btn hidden" id="vq-paste-btn" data-action="paste">PASTE</button>';
        document.body.appendChild(el);
    }

    function showPointMenu(clientX, clientY, t) {
        ensurePointMenu();
        lastPoint = { clientX, clientY, t };
        const el = document.getElementById('wave-point-menu');
        if (!el) return;

        const pasteBtn = document.getElementById('vq-paste-btn');
        if (pasteBtn) {
            if (window.VoiceQwen && window.VoiceQwen.copiedAudioBuffer) {
                pasteBtn.classList.remove('hidden');
            } else {
                pasteBtn.classList.add('hidden');
            }
        }

        el.style.left = clientX + 'px';
        el.style.top = clientY + 'px';
        el.classList.remove('hidden');
    }

    function hidePointMenu() {
        const el = document.getElementById('wave-point-menu');
        if (el) el.classList.add('hidden');
    }

    function timeAtClientX(clientX) {
        const metrics = getMetrics();
        if (!metrics) return 0;
        const rect = metrics.root.getBoundingClientRect();
        const x = Math.max(0, Math.min(rect.width, clientX - rect.left));
        const t = (metrics.scrollPx + x) / metrics.pxPerSec;
        return Math.max(0, Math.min(metrics.duration, t));
    }

    document.addEventListener('contextmenu', function (ev) {
        try {
            const root = getRoot();
            if (!root || !root.contains(ev.target)) return;
            if ($(ev.target).closest('.vq-marker').length) return;
            if (!getWS()) return;
            ev.preventDefault();
            ev.stopPropagation();
            showPointMenu(ev.clientX, ev.clientY, timeAtClientX(ev.clientX));
        } catch (_) {}
    }, true);

    $(document).on('click', '#wave-point-menu .vq-point-btn', async function (e) {
        e.preventDefault();
        const action = $(this).data('action');
        if (action === 'marker') {
            addMarker(lastPoint.t);
            hidePointMenu();
            return;
        }
        if (action === 'voice') {
            hidePointMenu();
            if (window.VoiceQwen && typeof window.VoiceQwen.openAddSpeechAt === 'function') {
                window.VoiceQwen.openAddSpeechAt(lastPoint.t, lastPoint.clientX, lastPoint.clientY);
            }
            return;
        }
        if (action === 'paste') {
            hidePointMenu();
            if (window.VoiceQwen && window.VoiceQwen.copiedAudioBuffer) {
                const newBuf = await window.VoiceQwen.insertAudioAt(window.VoiceQwen.activeAudioBuffer, window.VoiceQwen.copiedAudioBuffer, lastPoint.t);
                window.VoiceQwen.waveUndoStack.push(window.VoiceQwen.activeAudioBuffer);
                window.VoiceQwen.activeAudioBuffer = newBuf;
                if (typeof window.VoiceQwen.updateWaveformPreview === 'function') {
                    window.VoiceQwen.updateWaveformPreview();
                }
            }
        }
    });

    $(document).on('contextmenu', '.vq-marker, .vq-marker-handle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = $(this).data('id') || $(this).closest('.vq-marker').data('id');
        if (!id) return;
        if (confirm('Delete marker?')) deleteMarker(String(id));
    });

    $(document).on('click', '.vq-marker-handle', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const id = String($(this).data('id') || '');
        if (!id) return;
        selectedMarkerId = id;
        scheduleRender(0);
    });

    $(document).on('pointerdown', '.vq-marker-handle', function (e) {
        if (typeof e.button === 'number' && e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();

        const id = String($(this).data('id') || '');
        if (!id) return;

        const marker = markers.find((item) => item.id === id);
        if (!marker) return;

        draggingMarkerId = id;
        selectedMarkerId = id;
        dragStartClientX = e.clientX;
        dragStartTime = Number(marker.t) || 0;
        dragStartLeftPx = timeToContentLeftPx(dragStartTime);
        dragCurrentVisibleLeftPx = timeToVisibleLeftPx(dragStartTime);
        try { this.setPointerCapture(e.pointerId); } catch (_) {}
    });

    $(document).on('pointermove', function (e) {
        if (!draggingMarkerId) return;
        const marker = markers.find((item) => item.id === draggingMarkerId);
        if (!marker) return;
        const deltaX = e.clientX - dragStartClientX;
        const node = getMarkerDomNode(draggingMarkerId);
        const metrics = getMetrics();
        if (!node || !metrics) return;
        const contentLeftPx = Math.max(0, dragStartLeftPx + deltaX);
        const visibleLeftPx = contentLeftPx - metrics.scrollPx;
        dragCurrentVisibleLeftPx = visibleLeftPx;
        node.style.left = visibleLeftPx + 'px';
    });

    $(document).on('pointerup pointercancel', function () {
        if (!draggingMarkerId) return;
        const marker = markers.find((item) => item.id === draggingMarkerId);
        if (marker) {
            marker.t = visibleLeftPxToTime(dragCurrentVisibleLeftPx);
        }
        draggingMarkerId = '';
        dragStartClientX = 0;
        dragStartTime = 0;
        dragStartLeftPx = 0;
        dragCurrentVisibleLeftPx = 0;
        markers.sort((a, b) => a.t - b.t);
        scheduleRender(0);
        scheduleSave();
    });

    $(document).on('keydown', function (e) {
        if (!selectedMarkerId) return;
        if (e.key !== 'Delete' && e.key !== 'Backspace') return;
        const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        if (tag === 'textarea' || tag === 'input' || tag === 'select' || (e.target && e.target.isContentEditable)) return;
        e.preventDefault();
        deleteMarker(selectedMarkerId);
    });

    $(document).on('mousedown', function (e) {
        const menu = document.getElementById('wave-point-menu');
        if (!menu || menu.classList.contains('hidden')) return;
        if (menu.contains(e.target)) return;
        hidePointMenu();
    });

    $(document).on('voiceqwen_waveform_ready', function () {
        lastScrollPx = 0;
        loadMarkers();
        scheduleRender(50);
    });

    $(document).on('input change', '#wave-zoom', function () {
        scheduleRender(0);
    });

    window.addEventListener('resize', function () {
        scheduleRender(0);
    });

    document.addEventListener('scroll', function (ev) {
        const root = getRoot();
        if (!root) return;
        if (!root.contains(ev.target)) return;
        scheduleRender(0);
    }, true);

    document.addEventListener('wheel', function (ev) {
        const root = getRoot();
        if (!root || !root.contains(ev.target)) return;
        setTimeout(function () { scheduleRender(0); }, 0);
    }, { passive: true });

    setInterval(function () {
        const root = getRoot();
        const wrapper = getWrapper();
        if (!root || !wrapper || $('#view-waveform').hasClass('hidden')) return;
        const scrollPx = getScrollPx(root, wrapper);
        if (Math.abs(scrollPx - lastScrollPx) >= 1) {
            lastScrollPx = scrollPx;
            scheduleRender(0);
        }
    }, 120);
});
