jQuery(document).ready(function ($) {
    let lastScrollEl = null;
    let cachedCandidatesRoot = null;
    let cachedCandidates = [];
    let lastScrollPx = 0;

    function getWS() {
        return window.VoiceQwen && window.VoiceQwen.wavesurferInstance ? window.VoiceQwen.wavesurferInstance : null;
    }

    function parseTranslateX(transform) {
        if (!transform || transform === 'none') return 0;
        const m = transform.match(/^matrix\((.+)\)$/);
        if (m) {
            const parts = m[1].split(',').map(s => Number(s.trim()));
            if (parts.length === 6) return parts[4] || 0;
            return 0;
        }
        const m3 = transform.match(/^matrix3d\((.+)\)$/);
        if (m3) {
            const parts = m3[1].split(',').map(s => Number(s.trim()));
            // matrix3d tx is index 12
            if (parts.length === 16) return parts[12] || 0;
            return 0;
        }
        // translateX(...) fallback
        const t = transform.match(/translateX\(([-0-9.]+)px\)/);
        if (t) return Number(t[1]) || 0;
        return 0;
    }

    function buildCandidates(rootEl) {
        if (!rootEl) return [];
        if (cachedCandidatesRoot === rootEl && cachedCandidates.length) return cachedCandidates;
        cachedCandidatesRoot = rootEl;
        cachedCandidates = [];

        const pushIf = (el) => {
            if (!el || el.nodeType !== 1) return;
            const sw = el.scrollWidth || 0;
            const cw = el.clientWidth || 0;
            if (sw > cw + 2) cachedCandidates.push(el);
        };

        pushIf(rootEl);
        const all = rootEl.querySelectorAll('*');
        for (let i = 0; i < all.length && i < 400; i++) pushIf(all[i]);
        return cachedCandidates;
    }

    function getScrollPx(rootEl, wrapperEl) {
        // 0) Prefer geometry: as the waveform scrolls, the inner wrapper shifts left relative to the root.
        // This is the most robust signal across native scroll and transform-based scrolling.
        if (rootEl && wrapperEl && typeof rootEl.getBoundingClientRect === 'function' && typeof wrapperEl.getBoundingClientRect === 'function') {
            const rootRect = rootEl.getBoundingClientRect();
            const wrapperRect = wrapperEl.getBoundingClientRect();
            const geomShift = rootRect.left - wrapperRect.left;
            if (geomShift > 0.5) return geomShift;
        }

        // 1) Prefer real scrollLeft from the element that actually scrolls.
        const cand = buildCandidates(rootEl);
        let bestEl = null;
        let bestScroll = 0;
        for (let i = 0; i < cand.length; i++) {
            const el = cand[i];
            const sl = el.scrollLeft || 0;
            if (sl > bestScroll) {
                bestScroll = sl;
                bestEl = el;
            }
        }

        if (bestEl) {
            lastScrollEl = bestEl;
            return bestScroll;
        }

        // 2) Fallback: some renderers shift content with transform translateX instead of scrollLeft.
        // Look for the most-negative translateX (meaning it moved left as you scroll right).
        const searchRoot = rootEl || wrapperEl;
        if (!searchRoot) return 0;
        const all = searchRoot.querySelectorAll('*');
        let bestShift = 0;
        for (let i = 0; i < all.length && i < 400; i++) {
            const tr = window.getComputedStyle(all[i]).transform;
            const tx = parseTranslateX(tr);
            // When content shifts left, tx is negative; convert to a positive scroll distance.
            const shift = tx < 0 ? -tx : 0;
            if (shift > bestShift) bestShift = shift;
        }
        return bestShift;
    }

    function findScrollEl(rootEl) {
        if (!rootEl) return null;

        if (lastScrollEl && rootEl.contains(lastScrollEl)) {
            const sw = lastScrollEl.scrollWidth || 0;
            const cw = lastScrollEl.clientWidth || 0;
            if (sw > cw + 2) return lastScrollEl;
        }

        // Prefer a descendant that actually scrolls horizontally.
        const queue = [rootEl];
        let best = null;
        let bestScrollWidth = 0;

        for (let i = 0; queue.length && i < 300; i++) {
            const el = queue.shift();
            if (!el || el.nodeType !== 1) continue;

            const sw = el.scrollWidth || 0;
            const cw = el.clientWidth || 0;
            if (sw > cw + 2 && sw > bestScrollWidth) {
                best = el;
                bestScrollWidth = sw;
            }

            // Keep scan cheap.
            const kids = el.children;
            for (let j = 0; j < kids.length; j++) queue.push(kids[j]);
        }

        if (best) return best;

        // Fallback: choose the element with the largest scrollWidth.
        best = rootEl;
        bestScrollWidth = rootEl.scrollWidth || 0;
        const all = rootEl.querySelectorAll('*');
        for (let i = 0; i < all.length && i < 300; i++) {
            const el = all[i];
            const sw = el.scrollWidth || 0;
            if (sw > bestScrollWidth) {
                best = el;
                bestScrollWidth = sw;
            }
        }
        return best;
    }

    function ensureRulerCanvas() {
        const host = document.getElementById('wave-timeline');
        if (!host) return null;

        let canvas = host.querySelector('canvas.vq-time-ruler');
        if (!canvas) {
            host.innerHTML = '';
            canvas = document.createElement('canvas');
            canvas.className = 'vq-time-ruler';
            host.appendChild(canvas);
        }
        return canvas;
    }

    function pickIntervals(pxPerSec) {
        if (pxPerSec >= 200) return { minor: 0.1, major: 1 };
        if (pxPerSec >= 80) return { minor: 0.5, major: 2 };
        if (pxPerSec >= 40) return { minor: 1, major: 5 };
        if (pxPerSec >= 15) return { minor: 2, major: 10 };
        return { minor: 5, major: 30 };
    }

    function fmtSeconds(sec) {
        const s = Math.max(0, sec);
        if (s < 60) return `${Math.round(s)}s`;
        const m = Math.floor(s / 60);
        const r = Math.round(s % 60);
        return `${m}:${String(r).padStart(2, '0')}`;
    }

    function renderRuler() {
        const ws = getWS();
        if (!ws) return;
        const duration = ws.getDuration ? ws.getDuration() : 0;
        if (!duration) return;

        const canvas = ensureRulerCanvas();
        if (!canvas) return;

        const wrapper = ws.getWrapper ? ws.getWrapper() : null;
        if (!wrapper) return;
        const root = document.getElementById('waveform');
        const scrollParent = findScrollEl(root) || findScrollEl(wrapper) || wrapper;

        const dpr = window.devicePixelRatio || 1;
        const cssW = canvas.clientWidth || canvas.parentElement.clientWidth || 0;
        const cssH = canvas.parentElement.clientHeight || 26;
        if (cssW <= 0) return;

        canvas.width = Math.floor(cssW * dpr);
        canvas.height = Math.floor(cssH * dpr);
        canvas.style.width = cssW + 'px';
        canvas.style.height = cssH + 'px';

        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, cssW, cssH);

        const sliderPxPerSec = Number($('#wave-zoom').val() || 0);
        const wrapperRect = wrapper.getBoundingClientRect ? wrapper.getBoundingClientRect() : null;
        const totalPx = scrollParent.scrollWidth || wrapper.scrollWidth || (wrapperRect ? wrapperRect.width : 0) || wrapper.clientWidth || cssW;
        const computedPxPerSec = totalPx / duration;
        const pxPerSec = sliderPxPerSec > 0 ? sliderPxPerSec : computedPxPerSec;
        const intervals = pickIntervals(pxPerSec);

        const scrollPx = getScrollPx(root, wrapper);
        const viewStart = scrollPx / pxPerSec;
        const viewEnd = (scrollPx + (scrollParent.clientWidth || cssW)) / pxPerSec;

        // Styles
        const isBW = !!document.querySelector('.vq-bw-waveform');
        ctx.font = '10px sans-serif';
        // Default: high-contrast on the (mostly light) timeline background.
        ctx.fillStyle = isBW ? '#111' : '#111';
        ctx.strokeStyle = isBW ? '#111' : 'rgba(0,0,0,0.35)';
        ctx.lineWidth = 1;

        const minorH = Math.floor(cssH * 0.35);
        const majorH = Math.floor(cssH * 0.6);

        const first = Math.floor(viewStart / intervals.minor) * intervals.minor;
        for (let t = first; t <= viewEnd + intervals.minor; t += intervals.minor) {
            const x = (t * pxPerSec) - scrollPx;
            if (x < -5 || x > cssW + 5) continue;

            const isMajor = Math.abs((t / intervals.major) - Math.round(t / intervals.major)) < 1e-6;
            const h = isMajor ? majorH : minorH;

            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, h);
            ctx.stroke();

            if (isMajor) {
                ctx.fillText(fmtSeconds(t), x + 2, cssH - 6);
            }
        }
    }

    function setZoom(next) {
        const ws = getWS();
        if (!ws) return;
        const z = Math.max(10, Math.min(1000, Math.round(next)));
        $('#wave-zoom').val(String(z));
        if (ws.zoom) ws.zoom(z);
        renderRuler();
    }

    // Spacebar toggles play/pause (when not typing).
    $(document).on('keydown', function (e) {
        if (e.code !== 'Space') return;
        const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        if (tag === 'textarea' || tag === 'input' || tag === 'select' || (e.target && e.target.isContentEditable)) return;
        const ws = getWS();
        if (!ws || $('#view-waveform').hasClass('hidden')) return;
        e.preventDefault();
        ws.playPause();
    });

    // Keep ruler in sync with slider zoom.
    $(document).on('input', '#wave-zoom', function () {
        const ws = getWS();
        if (!ws) return;
        if (ws.zoom) ws.zoom(Number($(this).val()));
        renderRuler();
    });

    // Pinch-to-zoom (trackpad): browsers emit wheel with ctrlKey=true.
    document.addEventListener('wheel', function (ev) {
        try {
            const wf = document.getElementById('waveform');
            if (!wf) return;
            if (!wf.contains(ev.target)) return;
            if (!ev.ctrlKey) return;
            const ws = getWS();
            if (!ws) return;
            ev.preventDefault();
            const cur = Number($('#wave-zoom').val() || 10);
            setZoom(ev.deltaY < 0 ? cur * 1.12 : cur * 0.89);
        } catch (_) {}
    }, { passive: false, capture: true });

    // Re-render on waveform ready + on resize.
    $(document).on('voiceqwen_waveform_ready', function () {
        setTimeout(renderRuler, 0);
    });
    window.addEventListener('resize', () => setTimeout(renderRuler, 0));

    // Track the actual scrolling element under #waveform so the ruler follows horizontal scroll.
    document.addEventListener('scroll', (ev) => {
        try {
            const wf = document.getElementById('waveform');
            if (!wf) return;
            if (!wf.contains(ev.target)) return;
            lastScrollEl = ev.target;
            renderRuler();
        } catch (_) {}
    }, true);

    // Some trackpads scroll horizontally via wheel first; render after the scroll settles.
    document.addEventListener('wheel', (ev) => {
        try {
            const wf = document.getElementById('waveform');
            if (!wf) return;
            if (!wf.contains(ev.target)) return;
            if (ev.ctrlKey) return; // pinch is handled above (and will render).
            if (Math.abs(ev.deltaX || 0) < 1) return;
            setTimeout(renderRuler, 0);
        } catch (_) {}
    }, { capture: true, passive: true });

    // Last-resort: poll scroll position while the waveform view is visible, so the ruler follows even if
    // the browser/library doesn't emit scroll events (or uses transforms).
    function rafPoll() {
        try {
            const ws = getWS();
            if (!ws) return requestAnimationFrame(rafPoll);
            const wf = document.getElementById('waveform');
            const vw = document.getElementById('view-waveform');
            if (!wf || !vw || vw.classList.contains('hidden')) return requestAnimationFrame(rafPoll);

            const wrapper = ws.getWrapper ? ws.getWrapper() : null;
            const scrollPx = getScrollPx(wf, wrapper);
            if (Math.abs(scrollPx - lastScrollPx) >= 1) {
                lastScrollPx = scrollPx;
                renderRuler();
            }
        } catch (_) {}
        requestAnimationFrame(rafPoll);
    }
    requestAnimationFrame(rafPoll);
});
