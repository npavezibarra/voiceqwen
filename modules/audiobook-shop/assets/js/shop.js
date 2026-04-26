/**
 * Audiobook Shop Frontend Logic
 */

let BOOKS = [];
let selectedBook = null;
let isPlaying = false;
let currentChapter = 0;
let audioPlayer = null;
let heartbeatTimer = null;

function updateClock() {
    const clockEl = document.getElementById('current-time');
    if (!clockEl) return;
    const now = new Date();
    const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    clockEl.innerText = timeStr;
}

function loadBooks() {
    const grid = document.getElementById('books-grid');
    if (!grid) return;

    grid.innerHTML = `
        <div class="col-span-full flex flex-col items-center justify-center py-20 text-zinc-300">
            <div class="w-12 h-12 border-4 border-zinc-200 border-t-black rounded-full animate-spin mb-4"></div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em]">Cargando tu librería...</p>
        </div>
    `;

    jQuery.post(voiceqwen_ajax.url, {
        action: 'vq_shop_get_books',
        nonce: voiceqwen_ajax.nonce
    }, function(response) {
        if (response.success) {
            BOOKS = response.data.books;
            window.isAdmin = response.data.is_admin;
            renderLibrary();
        } else {
            grid.innerHTML = '<p class="col-span-full text-center text-red-500 py-10">Error al cargar la librería.</p>';
        }
    });
}

function renderLibrary() {
    const grid = document.getElementById('books-grid');
    if (!grid) return;

    if (BOOKS.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full flex flex-col items-center justify-center py-20 text-zinc-300 border-2 border-dashed border-zinc-100 rounded-3xl">
                <i data-lucide="book-x" class="w-12 h-12 mb-4 opacity-20"></i>
                <p class="text-[10px] font-bold uppercase tracking-[0.2em]">No hay libros en tu librería aún</p>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
        return;
    }

    grid.innerHTML = BOOKS.map(book => `
        <div class="flex flex-col gap-5 group cursor-pointer" onclick="openBook(${book.id})">
            <div class="relative aspect-square bg-zinc-50 overflow-hidden rounded-[6px] border border-zinc-100 shadow-sm group-hover:shadow-xl transition-all duration-500">
                <img src="${book.cover}" alt="${book.title}" class="w-full h-full object-cover grayscale-[0.2] brightness-95 group-hover:grayscale-0 group-hover:scale-105 transition-all duration-700">
                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                <div class="absolute bottom-6 left-6 bg-white text-black p-4 rounded-full shadow-2xl opacity-0 group-hover:opacity-100 translate-y-4 group-hover:translate-y-0 transition-all duration-500">
                    <i data-lucide="play" class="w-5 h-5 fill-current"></i>
                </div>
            </div>
            <div class="px-2">
                <h3 class="text-sm font-black uppercase tracking-tight truncate mb-1">${book.title}</h3>
                <p class="text-[10px] text-zinc-400 uppercase tracking-widest font-bold">${book.author}</p>
            </div>
        </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

window.openBook = function(id) {
    selectedBook = BOOKS.find(b => b.id == id);
    if (!selectedBook) return;

    currentChapter = 0;
    document.getElementById('library-view').classList.add('hidden');
    document.getElementById('player-view').classList.remove('hidden');

    document.getElementById('player-cover').src = selectedBook.cover;
    document.getElementById('player-title').innerText = selectedBook.title;
    document.getElementById('player-author').innerText = selectedBook.author;

    // Background transition
    const playerBg = document.getElementById('player-bg');
    if (selectedBook.background) {
        playerBg.style.backgroundImage = `url(${selectedBook.background})`;
        playerBg.style.filter = 'blur(3px) brightness(0.6)';
        playerBg.style.transform = 'scale(1.1)';
    } else {
        playerBg.style.backgroundImage = 'none';
        playerBg.style.backgroundColor = 'black';
    }

    // Purchase Lock Logic
    const desktopLock = document.getElementById('desktop-lock-overlay');
    const mobileLock = document.getElementById('mobile-lock-overlay');
    const desktopList = document.getElementById('desktop-chapters-list');
    const mobileList = document.getElementById('mobile-chapters-list');

    if (selectedBook.is_purchased) {
        if (desktopLock) desktopLock.classList.add('hidden');
        if (mobileLock) mobileLock.classList.add('hidden');
        if (desktopList) desktopList.classList.remove('pointer-events-none', 'opacity-50');
        if (mobileList) mobileList.classList.remove('pointer-events-none', 'opacity-50');
    } else {
        if (desktopLock) desktopLock.classList.remove('hidden');
        if (mobileLock) mobileLock.classList.remove('hidden');
        // Keep lists visible but unclickable and slightly dimmed
        if (desktopList) desktopList.classList.add('pointer-events-none', 'opacity-50');
        if (mobileList) mobileList.classList.add('pointer-events-none', 'opacity-50');

        // Setup Buy Buttons
        const buyButtons = [document.getElementById('desktop-buy-btn'), document.getElementById('mobile-buy-btn')];
        buyButtons.forEach(btn => {
            if (btn) {
                const priceTag = btn.querySelector('.price-tag');
                if (priceTag) priceTag.innerHTML = selectedBook.price_html || '';
                btn.onclick = (e) => {
                    e.stopPropagation();
                    buyAudiobook(selectedBook.product_id);
                };
            }
        });
    }

    renderChapters();
    updatePlayerUI();
    
    // Auto-load first chapter URL
    if (selectedBook.is_purchased) {
        loadChapterUrl(currentChapter);
    } else {
        // Reset player state if locked
        if (audioPlayer) {
            audioPlayer.src = '';
            isPlaying = false;
            updatePlayIcon();
        }
    }
};

function buyAudiobook(productId) {
    if (!productId) {
        alert('Este audiolibro no tiene un producto vinculado.');
        return;
    }

    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = 'AGREGANDO...';
    btn.disabled = true;

    jQuery.post(voiceqwen_ajax.url, {
        action: 'vq_woo_add_to_cart',
        nonce: voiceqwen_ajax.nonce,
        product_id: productId
    }, function(response) {
        if (response.success) {
            btn.innerHTML = '¡AGREGADO!';
            setTimeout(() => {
                window.location.href = response.data.checkout_url;
            }, 500);
        } else {
            alert('Error: ' + response.data);
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}

window.closePlayer = function() {
    document.getElementById('player-view').classList.add('hidden');
    document.getElementById('library-view').classList.remove('hidden');
    if (audioPlayer) {
        saveProgress();
        audioPlayer.pause();
    }
    isPlaying = false;
    updatePlayIcon();
    if (heartbeatTimer) clearInterval(heartbeatTimer);
};

function loadChapterUrl(index, autoPlay = false) {
    const chapter = selectedBook.chapters[index];
    if (!chapter) return;

    // Reset progress text instead of showing "Cargando..."
    const timeDisplay = document.querySelector('.space-y-6.mb-12 span:first-child');
    if (timeDisplay) timeDisplay.innerText = "00:00";

    jQuery.post(voiceqwen_ajax.url, {
        action: 'vq_get_track_url',
        nonce: voiceqwen_ajax.nonce,
        key: chapter.key,
        storage: chapter.storage,
        post_id: selectedBook.id
    }, function(response) {
        if (response.success) {
            audioPlayer.src = response.data;
            
            // Apply saved progress if any
            const savedTime = chapter.progress ? chapter.progress.time : 0;
            const isFinished = chapter.progress ? chapter.progress.finished : false;
            
            // If it was finished, we start from 0 unless the user manually seeks.
            // But usually, we just load it.
            
            audioPlayer.onloadedmetadata = function() {
                if (savedTime > 0 && !isFinished) {
                    audioPlayer.currentTime = savedTime;
                }
                updatePlayerUI();
            };

            audioPlayer.onerror = function() {
                if (timeDisplay) timeDisplay.innerText = "Error de carga";
                console.error("Audio failed to load:", audioPlayer.src);
            };

            if (autoPlay) {
                audioPlayer.play().catch(e => {
                    console.warn("Autoplay blocked or failed:", e);
                    isPlaying = false;
                    updatePlayIcon();
                });
                isPlaying = true;
                updatePlayIcon();
                startHeartbeat();
            }
        } else {
            const errMsg = response.data || "Error de URL";
            if (timeDisplay) timeDisplay.innerText = errMsg;
            console.error("AJAX Error:", response);
        }
    }).fail(function(err) {
        if (timeDisplay) timeDisplay.innerText = "Error de conexión";
        console.error("AJAX Fail:", err);
    });
}

window.togglePlay = function() {
    if (!audioPlayer.src) {
        loadChapterUrl(currentChapter, true);
        return;
    }

    if (isPlaying) {
        audioPlayer.pause();
        saveProgress();
        if (heartbeatTimer) clearInterval(heartbeatTimer);
    } else {
        audioPlayer.play();
        startHeartbeat();
    }
    isPlaying = !isPlaying;
    updatePlayIcon();
};

function startHeartbeat() {
    if (heartbeatTimer) clearInterval(heartbeatTimer);
    heartbeatTimer = setInterval(saveProgress, 15000); // 15 seconds
}

function saveProgress(isFinished = false) {
    if (!selectedBook || !audioPlayer || !audioPlayer.src) return;

    const time = isFinished ? 0 : audioPlayer.currentTime;
    
    // Update local data so UI reflects it immediately
    selectedBook.chapters[currentChapter].progress = {
        time: time,
        finished: isFinished
    };

    jQuery.post(voiceqwen_ajax.url, {
        action: 'vq_shop_save_progress',
        nonce: voiceqwen_ajax.nonce,
        book_id: selectedBook.id,
        chapter_index: currentChapter,
        time: time,
        finished: isFinished
    });
}

function updatePlayIcon() {
    const icon = document.getElementById('play-icon');
    if (!icon) return;
    icon.setAttribute('data-lucide', isPlaying ? 'pause' : 'play');
    if (!isPlaying) icon.classList.add('ml-1.5');
    else icon.classList.remove('ml-1.5');
    if (window.lucide) lucide.createIcons();
}

function updatePlayerUI() {
    const chapter = selectedBook.chapters[currentChapter];
    if (!chapter) return;
    const durationEl = document.getElementById('player-duration');
    if (durationEl) durationEl.innerText = chapter.duration || '0:00';
    
    updatePlayIcon();
    renderChapters();
    
    // Reset progress bar
    const bar = document.getElementById('progress-bar');
    if (bar) bar.style.width = '0%';
}

window.openChapters = function() {
    renderChapters();
    const overlay = document.getElementById('overlay');
    const sheet = document.getElementById('bottom-sheet');
    if (overlay) {
        overlay.classList.remove('hidden');
        setTimeout(() => overlay.classList.add('opacity-100'), 10);
    }
    if (sheet) sheet.classList.add('active');
};

window.closeChapters = function() {
    const overlay = document.getElementById('overlay');
    const sheet = document.getElementById('bottom-sheet');
    if (overlay) {
        overlay.classList.remove('opacity-100');
        setTimeout(() => overlay.classList.add('hidden'), 300);
    }
    if (sheet) sheet.classList.remove('active');
};

function renderChapters() {
    if (!selectedBook) return;
    const mobileList = document.getElementById('mobile-chapters-list');
    const desktopList = document.getElementById('desktop-chapters-list');
    const countEl = document.getElementById('chapter-count');
    
    if (countEl) countEl.innerText = `${selectedBook.chapters.length} Tracks`;

    const html = selectedBook.chapters.map((ch, idx) => {
        const isFinished = ch.progress && ch.progress.finished;
        const isActive = currentChapter === idx;
        
        return `
            <button onclick="selectChapter(${idx})" class="w-full flex items-center justify-between p-4 rounded-[6px] transition-all ${isActive ? 'bg-white text-black shadow-lg scale-[1.02]' : 'hover:bg-white/5 text-white'}">
                <div class="flex items-center gap-4">
                    <span class="text-[10px] font-black w-6 ${isActive ? 'text-black/20' : 'text-white/20'}">${(idx+1).toString().padStart(2, '0')}</span>
                    <div class="flex flex-col items-start text-left">
                        <span class="text-xs font-medium uppercase tracking-wider">${ch.title}</span>
                        ${isFinished ? `<span class="text-[8px] font-black ${isActive ? 'text-emerald-700' : 'text-emerald-500'} uppercase tracking-tighter mt-0.5">✓ Escuchado</span>` : ''}
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    ${isActive && isPlaying ? `
                        <div class="flex gap-1 items-end h-3">
                            <div class="w-0.5 bg-black animate-bounce-custom" style="height: 60%"></div>
                            <div class="w-0.5 bg-black animate-bounce-custom" style="height: 100%; animation-delay: 0.2s"></div>
                            <div class="w-0.5 bg-black animate-bounce-custom" style="height: 40%; animation-delay: 0.4s"></div>
                        </div>
                    ` : ''}
                    <span class="text-[10px] font-bold opacity-40">${ch.duration || '0:00'}</span>
                </div>
            </button>
        `;
    }).join('');

    if (mobileList) mobileList.innerHTML = html;
    if (desktopList) desktopList.innerHTML = html;
}

window.selectChapter = function(index) {
    currentChapter = index;
    updatePlayerUI();
    loadChapterUrl(currentChapter, true);
    closeChapters();
};

window.nextChapter = function() {
    if (currentChapter < selectedBook.chapters.length - 1) {
        currentChapter++;
        updatePlayerUI();
        loadChapterUrl(currentChapter, isPlaying);
    }
};

window.prevChapter = function() {
    if (currentChapter > 0) {
        currentChapter--;
        updatePlayerUI();
        loadChapterUrl(currentChapter, isPlaying);
    }
};

window.seekForward = function() {
    if (audioPlayer) {
        audioPlayer.currentTime = Math.min(audioPlayer.duration, audioPlayer.currentTime + 10);
    }
};

window.seekBackward = function() {
    if (audioPlayer) {
        audioPlayer.currentTime = Math.max(0, audioPlayer.currentTime - 10);
    }
};

function handleProgressClick(e) {
    if (!audioPlayer || !audioPlayer.duration) return;
    const container = document.getElementById('progress-container');
    if (!container) return;
    
    const rect = container.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const width = rect.width;
    const percentage = x / width;
    
    audioPlayer.currentTime = percentage * audioPlayer.duration;
    saveProgress();
}

function formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    return [
        h > 0 ? h : null,
        (h > 0 ? m.toString().padStart(2, '0') : m),
        s.toString().padStart(2, '0')
    ].filter(Boolean).join(':');
}

jQuery(document).ready(function($) {
    audioPlayer = document.getElementById('main-audio');
    
    if (audioPlayer) {
        audioPlayer.addEventListener('timeupdate', function() {
            const progress = (audioPlayer.currentTime / audioPlayer.duration) * 100;
            const bar = document.getElementById('progress-bar');
            if (bar) bar.style.width = progress + '%';
            
            // Update current time text (the first span in the container next to progress-bar)
            const timeDisplay = document.querySelector('#player-view .flex.justify-between span:first-child');
            if (timeDisplay) timeDisplay.innerText = formatTime(audioPlayer.currentTime);
            
            const durationDisplay = document.getElementById('player-duration');
            if (durationDisplay && audioPlayer.duration) {
                durationDisplay.innerText = formatTime(audioPlayer.duration);
            }
        });

        audioPlayer.addEventListener('ended', function() {
            saveProgress(true); // Mark as finished
            nextChapter();
        });

        // Click to seek
        const progressContainer = document.getElementById('progress-container');
        if (progressContainer) {
            progressContainer.addEventListener('click', handleProgressClick);
        }
    }

    if (window.lucide) lucide.createIcons();
    loadBooks();
    updateClock();
    setInterval(updateClock, 1000);
});
