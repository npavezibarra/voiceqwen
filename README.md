# VoiceQwen (Plugin WordPress)

`voiceqwen` (UI: **LOCUTOR**) es un plugin de WordPress orientado a crear audio con voces (TTS), generar diálogos multi‑voz, editar audio con un editor de waveform y administrar proyectos tipo “audiobook” (capítulos/playlist), con opción de almacenar en local o Cloudflare R2.

> Carpeta del plugin: `wp-content/plugins/voiceqwen/`

## Funcionalidades

- **CREATE AUDIO (TTS)**: genera `.wav` desde texto o desde archivo `.txt`, con controles de estabilidad, segmentación (máx palabras por segmento) y pausa entre segmentos.
- **DIALOGUES (multi‑voz)**: genera un `.wav` usando texto etiquetado por personaje, con el formato `[Nombre]...[/Nombre]`.
- **WAVE VIEWER / Editor**: visualiza waveform (WaveSurfer), permite selección por región, borrar selección, undo, restaurar original y guardar ediciones. Incluye auto‑save.
- **File Manager**: explorador de archivos por usuario (en `wp_upload_dir()`), con creación de carpetas, mover, renombrar, ordenar (persistencia `.order.json`), upload de `.wav` y borrado.
- **UPLOAD VOICE**: UI/formulario para registrar una nueva voz (muestra `.wav`, transcripción, avatar). Nota: el backend de entrenamiento/registro puede requerir implementación adicional según tu pipeline.
- **AUDIOBOOK MANAGER**: crea audiobooks (CPT `audiobook`), administra playlist de capítulos, reordena con SortableJS, sube capítulos y portada, reproduce inline, y permite sincronizar capítulos locales a Cloudflare R2.
- **AUDIO QUALITY REPORT**: análisis de calidad (peak/RMS + resumen) sobre los `.wav` del usuario.
- **CONFIG**: configuración de almacenamiento (local/R2) y test de conexión a R2.

## Cómo se integra en WordPress

- El plugin asegura la existencia de páginas:
  - `voice` (título: `LOCUTOR`) que renderiza el shortcode `[voiceqwen_ui]`.
  - `audi` (título: `Audi`) que usa un template propio.
- El UI principal se renderiza vía shortcode `voiceqwen_ui()` en `voiceqwen.php`.
- El plugin encola assets (CSS/JS) y librerías externas:
  - WaveSurfer + plugins (regions/timeline)
  - SortableJS
- La comunicación front/back se hace con `admin-ajax.php` usando `nonce` (`voiceqwen_nonce`) y requiere usuario autenticado para las acciones sensibles.

## Arquitectura (Modular y por dominio)

El objetivo es que el plugin sea fácil de evolucionar (y fácil de mantener con agentes) separando UI, lógica, endpoints y plantillas.

### 1. Entrada principal (`/`)

- `voiceqwen.php`: entry point. Carga subsistemas, registra templates, encola assets, define el shortcode y asegura páginas.
- `tts_cli.py`: backend Python para generación TTS single‑voice.
- `tts_dialogue.py`: backend Python para generación multi‑voz (diálogos).

### 2. Endpoints AJAX (`/includes/`)

- `ajax-generation.php`: generación TTS/diálogos en segundo plano (`nohup`), tracking vía `status.json`, reset/cancel.
- `ajax-files.php`: árbol de archivos, mover, renombrar, crear carpetas, upload `.wav`, eliminar, orden persistente.
- `ajax-editor.php`: guardar ediciones del editor (backup `.original.wav`), restore original, autosave y limpieza.
- `ajax-meta.php`: voces disponibles (desde `assets/voices`), avatar custom, audio analysis.
- `file-helpers.php`: utilidades de filesystem (tree, deletes, etc.).
- `class-voiceqwen-audio-analyzer.php`: lógica del reporte de calidad (QC).

### 3. UI / Templates (`/templates/`)

- `templates/voice-template.php`: template para la página `voice`.
- `templates/audi-template.php`: template para la página `audi`.
- `templates/views/`: vistas parciales del UI principal (create/dialogues/waveform/audiobook/upload/config/sidebar/mini‑modal).

### 4. Frontend (`/assets/`)

- `assets/js/`: routing de vistas, generación/polling, file manager, waveform UI + lógica, avatar manager.
- `assets/css/`: base + theme + estilos específicos (waveform/audiobook).
- `assets/voices/`: definiciones de voces (referencias), usadas para listar voces disponibles.

### 5. Módulos (`/modules/`)

- `modules/audiobook/`: módulo aislado con CPT, settings, manager UI, integración con R2.

## Datos y almacenamiento

- Por usuario, los archivos se guardan bajo: `wp_upload_dir()['basedir']/voiceqwen/<username>/`.
- El estado de trabajos en background se guarda en `status.json` en la carpeta del usuario.
- Ediciones crean backup una sola vez: `<archivo>.original.wav`.
- Auto‑save: `<archivo>-autosave.wav`.
- Audiobooks:
  - Metadatos en el CPT `audiobook` (playlist, author, cover key, folder name).
  - `local` usa `wp_upload_dir()` y `r2` usa Cloudflare R2 vía `aws/aws-sdk-php`.

## Dependencias

- PHP: usa Composer (`composer.json`) para `aws/aws-sdk-php` (R2).
- JS: WaveSurfer y SortableJS via CDN (ver `voiceqwen_enqueue_assets()` en `voiceqwen.php`).
- Python: `tts_cli.py` y `tts_dialogue.py` se ejecutan en background. Actualmente se intenta usar un Python de venv específico y, si no existe, cae a `python3`.

## Reglas de desarrollo (Obligatorio)

Lee y respeta `AGENTS.md` antes de cambiar código.

Regla clave para mantener el plugin ágil (especialmente al programar con agentes):

- **No habrá archivos grandes**: ningún archivo de código debe superar ~**500 líneas**.
- **Si un archivo se acerca a ~500 líneas o empieza a mezclar dominios**, se debe **crear otro archivo** y **encapsular** funciones/clases de forma lógica (por responsabilidad).
- **Una responsabilidad por archivo**: separa UI, lógica pura, endpoints, helpers, templates, etc.
- **Mantener la estructura modular**: no volver a diseños monolíticos (por ejemplo, “todo en un solo `script.js`”).

## Tips rápidos (para agentes)

- Añade features creando archivos nuevos por dominio en `includes/` o `assets/js/` antes de “seguir engordando” un archivo existente.
- Mantén funciones pequeñas y con nombres explícitos; cuando un endpoint crezca, divide por `ajax-<dominio>.php`.
- Evita acoplar el JS a HTML suelto: la UI vive en `templates/views/` y la lógica en `assets/js/`.
