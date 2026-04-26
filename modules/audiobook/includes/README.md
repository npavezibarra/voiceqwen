# Audiobook Includes - Arquitectura Modular

Esta carpeta contiene la lógica dividida del módulo de Audiobooks para mejorar la mantenibilidad y evitar errores de colisión de funciones.

## Estructura de Archivos

### 1. [AudiobookManager.php](file:///Users/nicolas/Local%20Sites/voiceqwen/app/public/wp-content/plugins/voiceqwen/modules/audiobook/includes/AudiobookManager.php)
**El Director de Orquesta.** 
Es el punto de entrada. Se encarga de inicializar los hooks de WordPress y delegar las peticiones a las clases correspondientes. No contiene lógica de negocio pesada.

### 2. [AudiobookAJAX.php](file:///Users/nicolas/Local%20Sites/voiceqwen/app/public/wp-content/plugins/voiceqwen/modules/audiobook/includes/AudiobookAJAX.php)
**Capa de Comunicación.**
Contiene todos los manejadores de `wp_ajax_*`. Su función es recibir los datos del frontend, verificar la seguridad (nonces), y llamar al `Processor` para realizar los cambios.

### 3. [AudiobookUI.php](file:///Users/nicolas/Local%20Sites/voiceqwen/app/public/wp-content/plugins/voiceqwen/modules/audiobook/includes/AudiobookUI.php)
**Capa de Presentación.**
Contiene todo el código HTML/PHP que se renderiza en la interfaz. Incluye la lógica de "Auto-Healing" para verificar si los archivos existen en R2 antes de mostrarlos.

### 4. [AudiobookProcessor.php](file:///Users/nicolas/Local%20Sites/voiceqwen/app/public/wp-content/plugins/voiceqwen/modules/audiobook/includes/AudiobookProcessor.php)
**Lógica de Negocio.**
Aquí es donde ocurre la magia: subida de archivos, sincronización con R2, manipulación de metadatos de capítulos y optimización de imágenes.

### 5. [AudiobookUtils.php](file:///Users/nicolas/Local%20Sites/voiceqwen/app/public/wp-content/plugins/voiceqwen/modules/audiobook/includes/AudiobookUtils.php)
**Herramientas Auxiliares.**
Funciones estáticas que no dependen del estado del sistema, como calcular la duración de un WAV, generar URLs de portadas o resolver rutas de archivos.

---

## Otros Componentes
*   `CoverOptimizer.php`: Clase especializada en redimensionar y comprimir JPEGs.
*   `R2Client.php`: Cliente para la API de Cloudflare R2 (S3 compatible).
*   `PostTypes.php`: Registro del tipo de contenido personalizado `audiobook`.
