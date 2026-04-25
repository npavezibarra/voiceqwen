<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body <?php body_class('bg-white min-h-screen shop-body'); ?>>

    <!-- Main Container -->
    <div class="w-full bg-white flex flex-col min-h-screen selection:bg-black selection:text-white">
        <audio id="main-audio" class="hidden"></audio>
        
        <!-- Contenido Principal -->
        <div id="main-content" class="flex-1 overflow-y-auto hide-scrollbar relative">
            
            <!-- VISTA LIBRERÍA -->
            <main id="library-view" class="flex-1 px-8 py-10 md:px-12 md:py-16 w-full">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-3xl font-black uppercase tracking-tight">Tu Librería</h2>
                        <p class="text-[10px] uppercase tracking-[0.3em] text-zinc-400 mt-2 font-bold">Escuchados recientemente</p>
                    </div>
                </div>
                <div id="books-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-10">
                    <!-- Los libros se insertan aquí vía JS -->
                </div>
            </main>

            <!-- VISTA REPRODUCTOR (Oculta por defecto) -->
            <div id="player-view" class="hidden flex flex-col flex-1 relative overflow-hidden w-full min-h-screen shadow-2xl bg-black">
                <!-- Background Layer (for blur effect) -->
                <div id="player-bg" class="absolute inset-0 z-0 bg-cover bg-center transition-all duration-700"></div>
                <!-- Overlay to darken background slightly -->
                <div class="absolute inset-0 z-1 bg-black/40"></div>

                <div class="relative z-10 flex-1 flex flex-col md:flex-row gap-8 px-8 py-4 md:px-12 md:py-12 h-full w-full">
                    
                    <!-- Left Column: Player Card -->
                    <div class="w-full md:w-3/5 flex flex-col">

                    <div class="bg-white/10 backdrop-blur-xl border border-white/10 rounded-[6px] p-8 flex flex-col md:flex-row gap-8 shadow-2xl">
                        <!-- Cover inside card -->
                        <div class="w-full md:w-48 aspect-square bg-zinc-800 rounded-[6px] overflow-hidden shadow-2xl flex-shrink-0 border border-white/10">
                            <img id="player-cover" src="" alt="Cover" class="w-full h-full object-cover">
                        </div>

                        <!-- Info & Controls inside card -->
                        <div class="flex-1 flex flex-col justify-center">
                            <div class="mb-6">
                                <h2 id="player-title" class="text-2xl font-black uppercase tracking-tight mb-1 leading-none text-white">Título</h2>
                                <p id="player-author" class="text-xs text-white/60 uppercase tracking-[0.2em]">Autor</p>
                            </div>

                            <!-- Progreso -->
                            <div class="space-y-4 mb-6">
                                <div id="progress-container" class="h-2 w-full bg-white/10 rounded-full relative overflow-hidden cursor-pointer group">
                                    <div id="progress-bar" class="absolute top-0 left-0 h-full bg-white rounded-full" style="width: 0%"></div>
                                    <div class="absolute top-0 left-0 h-full w-full opacity-0 group-hover:opacity-10 bg-white transition-opacity"></div>
                                </div>
                                <div class="flex justify-between text-[10px] font-bold text-white/50 uppercase tracking-widest">
                                    <span>00:00</span>
                                    <span id="player-duration">00:00</span>
                                </div>
                            </div>

                             <!-- Controles -->
                             <div class="flex items-center justify-between mt-4">
                                 <div class="flex items-center gap-8">
                                     <button onclick="seekBackward()" class="text-white/40 hover:text-white transition-colors">
                                         <i data-lucide="rotate-ccw" class="w-7 h-7"></i>
                                     </button>
                                     <button id="play-pause-btn" onclick="togglePlay()" class="text-white hover:scale-110 transition-transform flex items-center justify-center">
                                         <i data-lucide="play" id="play-icon" class="w-10 h-10 fill-current"></i>
                                     </button>
                                     <button onclick="seekForward()" class="text-white/40 hover:text-white transition-colors">
                                         <i data-lucide="rotate-cw" class="w-7 h-7"></i>
                                     </button>
                                 </div>
                                 
                                 <!-- Hamburger for Mobile Chapters -->
                                 <button onclick="openChapters()" class="lg:hidden text-white/40 hover:text-white transition-colors p-2">
                                     <i data-lucide="menu" class="w-6 h-6"></i>
                                 </button>
                             </div>
                        </div>
                    </div>

                    <!-- Botones debajo del player -->
                    <div class="flex items-center gap-4 mt-8 px-4">
                        <button onclick="closePlayer()" class="flex items-center gap-2 px-6 py-3 bg-white/10 backdrop-blur-md rounded-full text-[10px] font-bold uppercase tracking-widest text-white hover:bg-white/20 transition-colors">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i> Volver a la Tienda
                        </button>
                        <a id="edit-book-button" href="#" class="hidden flex items-center gap-2 px-6 py-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-full text-[10px] font-bold uppercase tracking-widest text-white hover:bg-white/20 transition-colors shadow-xl">
                            <i data-lucide="edit-3" class="w-4 h-4"></i> Editar Este Libro
                        </a>
                    </div>
                </div>

                <!-- Right Column: Chapters List (Desktop) -->
                <div class="flex max-md:hidden w-full md:w-2/5 flex-col bg-white/10 backdrop-blur-xl border border-white/10 rounded-[6px] shadow-2xl overflow-hidden">
                    <div class="flex justify-between items-center p-6 border-b border-white/5 bg-white/5">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-white/70">Capítulos</h3>
                        <span id="chapter-count" class="text-[10px] font-bold text-white/40 uppercase">0 Tracks</span>
                    </div>
                    <div id="desktop-chapters-list" class="flex-1 overflow-y-auto space-y-1 p-4 max-h-[600px] hide-scrollbar">
                        <!-- Lista de capítulos vía JS -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Capítulos (Mobile Drawer) -->
        <div id="overlay" onclick="closeChapters()" class="hidden fixed inset-0 bg-black/40 z-[60] transition-opacity opacity-0 backdrop-blur-sm lg:hidden"></div>
        <div id="bottom-sheet" class="bottom-sheet fixed bottom-0 left-0 right-0 lg:hidden bg-zinc-950/80 backdrop-blur-2xl z-[70] p-10 shadow-2xl flex flex-col rounded-t-[2rem] border-t border-white/10 max-h-[80vh]">
            <div class="w-12 h-1 bg-zinc-800 rounded-full mx-auto mb-10"></div>
            <div id="mobile-chapters-list" class="flex-1 overflow-y-auto space-y-3 pr-2 hide-scrollbar">
                <!-- Lista de capítulos vía JS -->
            </div>
        </div>

    </div>
<?php wp_footer(); ?>
</body>
</html>
