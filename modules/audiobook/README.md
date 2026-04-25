# VoiceQwen Audiobook Manager

Sistema avanzado de gestión de audiolibros con almacenamiento híbrido (Local + Cloudflare R2).

## 🚀 Mecanismo de Funcionamiento

### 1. Almacenamiento Híbrido
El sistema permite trabajar con archivos en dos ubicaciones:
- **Local**: Archivos almacenados en tu laptop (`wp-content/uploads/voiceqwen/{usuario}/`). Ideal para edición rápida y generación de audio.
- **Cloudflare R2**: Almacenamiento en la nube para distribución final. Los archivos se suben manteniendo una estructura limpia: `{nombre-del-libro}/{archivo}.wav`.

### 2. Sincronización Diferencial (Estado "CHANGED")
Para asegurar que la versión en la nube sea siempre la más reciente, el sistema implementa un comparador de tamaños:
- Al subir un archivo a R2, se registra su **tamaño original** (huella digital).
- Si editas el archivo localmente (con el Waveform Editor), el tamaño cambiará.
- El plugin detectará esta diferencia y marcará el capítulo como **CHANGED** (Naranja), habilitando de nuevo el botón de **Sync** para actualizar la nube.

### 3. Radar de Búsqueda y Auto-Sanación
El plugin incluye una lógica de resolución de rutas ultra-robusta:
- **Auto-Detección**: Al abrir un libro, el sistema verifica si los archivos locales ya existen en R2. Si los encuentra, actualiza el estado a **R2** automáticamente.
- **Búsqueda Profunda**: Si un archivo local no se encuentra en la ruta esperada, el sistema realiza un escaneo recursivo en el directorio del usuario para localizarlo.
- **Anclaje de Rutas (Local by Flywheel)**: Debido a las particularidades de entornos locales en Mac, el sistema utiliza rutas absolutas configuradas (`/Users/nicolas/Local Sites/...`) para garantizar que los botones de edición aparezcan siempre que el archivo esté en el disco.

### 4. Sincronización Bidireccional
El sistema permite no solo subir, sino también gestionar versiones:
- **Cloud Sync**: Sube tus cambios locales a R2.
- **Download to Local**: Baja archivos de R2 si no existen en tu laptop.
- **Restore from Cloud**: Si un archivo local está marcado como **CHANGED**, puedes descartar tus cambios y restaurar la versión de la nube (sobrescribiendo el local).

## 🛠 Flujo de Trabajo Recomendado

1. **Crear/Generar**: Añade capítulos o genéralos con IA. Quedarán en estado **LOCAL**.
2. **Editar**: Usa el icono de ondas para ajustar silencios, cortar o unir audios localmente.
3. **Subir**: Pulsa el icono de nube (**Sync**) para enviar la versión final a Cloudflare R2.
4. **Actualizar**: Si haces cambios posteriores en tu laptop, el estado pasará a **CHANGED**. Pulsa **Sync** de nuevo para sobreescribir la nube.

## ⚙️ Configuración R2
Para que la sincronización funcione, asegúrate de configurar las credenciales en la pestaña **CONFIG** del Audiobook Manager:
- Account ID
- Access Key & Secret Key
- Bucket Name (ej: `el-villegas-audiobook-2`)
