# VoiceQwen

`voiceqwen` (UI: `LOCUTOR`) es un plugin de WordPress para generar audio TTS, construir dialogos multi-voz, editar audio en un waveform editor y organizar proyectos tipo audiobook con capitulos, playlist y almacenamiento local o Cloudflare R2.

Carpeta del plugin: `wp-content/plugins/voiceqwen/`

## Funcionalidades

- `CREATE AUDIO`: genera `.wav` desde texto o `.txt`, con controles de estabilidad, maximo de palabras por segmento y pausa entre segmentos.
- `DIALOGUES`: genera audio multi-voz desde texto etiquetado con formato `[Nombre]...[/Nombre]`.
- `WAVE VIEWER / WAVEFORM EDITOR`: editor visual sobre WaveSurfer para revisar, cortar, insertar voz, reproducir, deshacer, restaurar y guardar.
- `FILE MANAGER`: explorador de archivos por usuario con carpetas, mover, renombrar, ordenar, upload de `.wav` y borrado.
- `UPLOAD VOICE`: interfaz para registrar nuevas voces con muestra, transcripcion y avatar.
- `AUDIOBOOK`: administra audiobooks, capitulos, playlist, uploads, reproduccion inline y sincronizacion local/R2.
- `AUDIO QUALITY REPORT`: analisis basico de calidad de audio.
- `CONFIG`: configuracion de storage y test de conexion a R2.

## Editor de Waveform

El editor de waveform es una de las piezas centrales del plugin. Hoy soporta:

- Visualizacion del waveform con `WaveSurfer`.
- Seleccion por region.
- Menu contextual sobre una region seleccionada con acciones `DELETE` y `VOICE`.
- Menu contextual sobre un punto del waveform con acciones `VOICE` y `MARKER`.
- Insercion de voz nueva en un punto exacto usando el panel `ADD SPEECH`.
- Borrado de segmentos seleccionados.
- `UNDO (-1)`.
- `RESTORE ORIGINAL`.
- `SAVE EDITS`.
- Auto-save mientras se trabaja.
- Timeline inferior con segundos visibles.
- Timeline que se adapta al `zoom`.
- Timeline que sigue el `horizontal scroll`.
- `Spacebar` como toggle `PLAY / PAUSE`.
- Zoom por slider y soporte de gesto/scroll horizontal segun el viewer actual.

## ADD SPEECH

El panel `ADD SPEECH` se abre desde el editor en dos casos:

- `Right click` sobre un punto del waveform y luego `VOICE`.
- Seleccion de una region y luego `VOICE`.

El panel permite:

- Elegir voz.
- Escribir el texto a insertar.
- Generar un clip nuevo.
- Insertarlo en la posicion activa del waveform.

Valores default del panel:

- `Estabilidad`: `0.5`
- `Palabras/segm`: `40`
- `Pausa`: `0.1`

## Guardado de ediciones

El flujo esperado del editor es:

- El usuario abre un audio original.
- El editor permite borrar segmentos o insertar clips nuevos generados desde texto.
- Al guardar, se debe sobrescribir el audio correcto en la ubicacion correcta del proyecto.
- El sistema crea un backup original una sola vez.
- El sistema mantiene auto-save mientras el usuario trabaja.

Archivos relacionados:

- Backup original: `<archivo>.original.wav`
- Auto-save: `<archivo>-autosave.wav`

## Markers

Se implemento un mecanismo de `markers` para dejar puntos de referencia dentro del audio y volver mas tarde a trabajar sobre ellos.

### Que hace un marker

- Se agrega sobre un punto exacto del waveform.
- Se dibuja como una linea vertical con un handle visible arriba.
- Puede tener nombre opcional.
- Debe persistir entre sesiones para el mismo audio.
- Puede seleccionarse.
- Puede moverse horizontalmente.
- Puede eliminarse.

### Como se usa

- `Right click` sobre un punto del waveform.
- Elegir `MARKER`.
- El marker aparece en esa posicion del audio.
- `Click` sobre el handle superior para seleccionarlo.
- Arrastre horizontal del handle para moverlo.
- `Right click` sobre el marker para eliminarlo.
- Tambien puede borrarse con tecla `Delete` / `Backspace` cuando esta seleccionado.

### Persistencia de markers

Los markers no se guardan en base de datos. Se guardan como JSON al lado del `.wav` correspondiente, dentro del storage del usuario:

- `wp_upload_dir()['basedir']/voiceqwen/<username>/<ruta_relativa>.wav.markers.json`

Endpoint relacionado:

- `includes/ajax-markers.php`

Frontend relacionado:

- `assets/js/waveform-markers.js`

## Integracion con WordPress

El plugin asegura la existencia de paginas:

- `voice`: renderiza el shortcode `[voiceqwen_ui]`
- `audi`: usa template propio para la experiencia audiobook

La UI principal se monta desde `voiceqwen.php` y encola:

- `WaveSurfer`
- plugin de regiones
- plugin de timeline
- `SortableJS`

La comunicacion front/back se hace por `admin-ajax.php` con `nonce` `voiceqwen_nonce`.

## Arquitectura

La estructura actual busca mantener dominios separados para que el plugin sea mas mantenible y mas facil de extender con agentes.

### Root

- `voiceqwen.php`: entry point del plugin, shortcode, assets, bootstrap general.
- `tts_cli.py`: generacion TTS single-voice.
- `tts_dialogue.py`: generacion TTS multi-voz.

### Includes

- `includes/ajax-generation.php`: generacion en background y polling de estado.
- `includes/ajax-files.php`: file manager, arbol, mover, renombrar, uploads y orden.
- `includes/ajax-editor.php`: save edits, restore original, autosave y limpieza.
- `includes/ajax-markers.php`: get/save de markers por archivo.
- `includes/ajax-meta.php`: voces, avatars, analisis de audio.
- `includes/file-helpers.php`: helpers de filesystem.
- `includes/class-voiceqwen-audio-analyzer.php`: analisis de calidad.

### Frontend JS

- `assets/js/core.js`: estado general y wiring base.
- `assets/js/generation.js`: generacion y polling.
- `assets/js/file-manager.js`: explorador de archivos.
- `assets/js/avatar-manager.js`: gestion de avatars/voces.
- `assets/js/waveform-ui.js`: carga del waveform, seleccion, modales, acciones del editor.
- `assets/js/waveform-logic.js`: operaciones de audio sobre `AudioBuffer`.
- `assets/js/waveform-ruler-controls.js`: timeline, zoom, scroll sync, keyboard shortcuts.
- `assets/js/waveform-markers.js`: markers, persistencia y drag/delete/select.

### Templates

- `templates/voice-template.php`
- `templates/audi-template.php`
- `templates/views/`: vistas parciales del UI

### Styles

- `assets/css/base.css`
- `assets/css/theme-vaporwave.css`
- `assets/css/waveform-viewer.css`
- `assets/css/audiobook.css`

## Datos y almacenamiento

- Base de storage por usuario: `wp_upload_dir()['basedir']/voiceqwen/<username>/`
- Estado de jobs en background: `status.json`
- Orden persistente del file manager: `.order.json`
- Backups originales del editor: `<archivo>.original.wav`
- Auto-save del editor: `<archivo>-autosave.wav`
- Markers: `<archivo>.wav.markers.json`

Audiobooks:

- Metadatos principales en el CPT `audiobook`
- Playlist y metadata asociada al proyecto
- Soporte para local y Cloudflare R2

## Dependencias

- PHP Composer: `aws/aws-sdk-php`
- JS CDN: `WaveSurfer`, plugins de regiones/timeline, `SortableJS`
- Python: `tts_cli.py` y `tts_dialogue.py`

## Reglas de desarrollo

Leer y respetar `AGENTS.md` antes de cambiar codigo.

Regla obligatoria para mantener el plugin agil:

- Ningun archivo de codigo debe crecer mas alla de aproximadamente `500` lineas.
- Si un archivo se acerca a ese tamano, se debe dividir.
- Las funciones deben encapsularse por responsabilidad.
- No mezclar UI, logica pura, endpoints y helpers en un mismo archivo si ya pertenecen a dominios distintos.
- Mantener la estructura modular del plugin.

## Notas para agentes

- Antes de agregar mas logica a un archivo JS o PHP grande, revisar si corresponde crear un archivo nuevo.
- Las features del editor deben repartirse entre `waveform-ui.js`, `waveform-logic.js`, `waveform-ruler-controls.js` y `waveform-markers.js`.
- Los endpoints AJAX deben seguir separados por dominio.
- Los markers son parte del estado de edicion del audio, pero su persistencia es archivo JSON, no tabla SQL.
