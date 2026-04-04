# ARCHETYPICAL CHILEAN

El plugin de TTS (Text-To-Speech) definitivo para clonación de voces chilenas icónicas, potenciado por el modelo **Qwen3-TTS 1.7B**.

## Arquitectura de Procesamiento

Para garantizar la máxima estabilidad y calidad, el sistema utiliza una técnica de **Segmentación Dinámica de Texto**. 

### ¿Por qué segmentamos el texto?

1.  **Optimización de Recursos:** Al procesar fragmentos de aproximadamente 45 palabras, evitamos saturar la memoria RAM y la GPU/MPS del ordenador. Esto permite que el proceso sea fluido incluso en equipos locales.
2.  **Calidad de Audio Superior:** Los modelos de TTS basados en Transformers tienden a degradar la calidad, perder la entonación o generar artefactos extraños cuando la secuencia de entrada es demasiado larga. Reiniciar el contexto en cada segmento permite que cada frase mantenga la misma fidelidad y claridad que la primera.
3.  **Prevención de Errores:** Evita bloqueos del motor de inferencia causados por secuencias de longitud excesiva que podrían superar los límites del modelo base.

## Requisitos
- WordPress local (Local Sites)
- Python 3.9+ con entorno virtual (`.venv`)
- Modelo Qwen3-TTS-12Hz-1.7B-Base cargado localmente.
