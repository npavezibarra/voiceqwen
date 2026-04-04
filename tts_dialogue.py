import argparse
import sys
import os
import json
import torch
import soundfile as sf
import numpy as np
import re
import unicodedata
from qwen_tts import Qwen3TTSModel

# --- CONFIGURACIÓN ---
MODEL_NAME = "Qwen/Qwen3-TTS-12Hz-1.7B-Base"
DEVICE = "mps" if torch.backends.mps.is_available() else "cpu"
SR = 24000 
SPEAKER_PAUSE = 0.9  # Silencio entre diferentes hablantes
CHUNK_PAUSE = 0.5    # Silencio entre fragmentos del mismo hablante (si el texto es muy largo)
MAX_WORDS = 45       # Máximo de palabras por fragmento para estabilidad

# Paths to assets
ASSETS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'assets', 'voices')

def get_available_voices():
    voices = {}
    if not os.path.exists(ASSETS_DIR):
        return voices
        
    for f in os.listdir(ASSETS_DIR):
        if f.endswith("-referencia.txt"):
            voice_id = f.replace("-referencia.txt", "")
            audio_path = os.path.join(ASSETS_DIR, f"{voice_id}-sample.wav")
            text_path = os.path.join(ASSETS_DIR, f)
            
            if os.path.exists(audio_path):
                voices[voice_id] = {
                    "audio": audio_path,
                    "text": text_path
                }
    return voices

VOICES = get_available_voices()

def clean_text(text):
    """
    Elimina caracteres Unicode decorativos (negritas, cursivas matemáticas) 
    que pueden confundir al tokenizador o al modelo.
    """
    # Normalizamos a forma NFKD para separar acentos si los hubiera, 
    # pero aquí nos interesa más eliminar estilos "fancy".
    # Una forma simple es filtrar solo caracteres que el modelo suele entender (ASCII + acentos normales)
    text = unicodedata.normalize('NFKC', text)
    
    # También podemos usar regex para quitar rangos de "Mathematical Alphanumeric Symbols" (U+1D400 a U+1D7FF)
    # pero NFKC suele convertir la mayoría a su forma normal. 
    return text

def parse_dialogue(text):
    """
    Parses text like '[Fernando]Hola[/Fernando] [Mary Rose]Hola Fernando[/Mary Rose]'
    Returns a list of (voice_id, segment_text)
    """
    # Regex to find tags like [Name]Content[/Name]. Matches anything between [ and ]
    pattern = r'\[([^\]]+)\](.*?)\[\/\1\]'
    matches = re.findall(pattern, text, re.DOTALL)
    
    # Pre-calculate normalized mapping for available voices
    def normalize(s):
        return re.sub(r'[^a-zA-Z0-9]', '', s.lower())

    normalized_to_id = {normalize(v): v for v in VOICES.keys()}
    
    segments = []
    for voice_tag, content in matches:
        norm_tag = normalize(voice_tag)
        if norm_tag in normalized_to_id:
            cleaned_content = clean_text(content.strip())
            segments.append((normalized_to_id[norm_tag], cleaned_content))
        else:
            print(f"WARNING: Voice '{voice_tag}' (normalized: '{norm_tag}') not found. Skipping segment.")
            
    return segments

def get_safe_chunks(text, max_words=MAX_WORDS):
    """Divide el texto buscando puntos para no cortar frases a la mitad."""
    sentences = re.split(r'(?<=[.!?]) +', text.replace('\n', ' '))
    chunks = []
    current_chunk = []
    current_count = 0
    
    for s in sentences:
        word_count = len(s.split())
        if current_count + word_count <= max_words or not current_chunk:
            current_chunk.append(s)
            current_count += word_count
        else:
            chunks.append(" ".join(current_chunk))
            current_chunk = [s]
            current_count = word_count
            
    if current_chunk:
        chunks.append(" ".join(current_chunk))
    return chunks

def update_status(file_path, current, total, start_time):
    if not file_path:
        return
    try:
        data = {
            "status": "processing",
            "current": int(current),
            "total": int(total),
            "time": int(start_time)
        }
        with open(file_path, 'w') as f:
            json.dump(data, f)
    except:
        pass

def main():
    try:
        parser = argparse.ArgumentParser(description="Qwen TTS Dialogue CLI")
        parser.add_argument("--text", type=str, required=True, help="Dialogue text with tags")
        parser.add_argument("--output", type=str, required=True, help="Output wav file path")
        parser.add_argument("--status_file", type=str, help="Path to status.json file")
        args = parser.parse_args()

        segments = parse_dialogue(args.text)
        if not segments:
            print("ERROR: No valid dialogue tags found. Format: [VoiceName]Text[/VoiceName]", flush=True)
            exit(1)

        total_segments = len(segments)
        print(f"Cargando modelo en {DEVICE} (float32)...", flush=True)
        
        # Get start time
        start_time = 0
        if args.status_file and os.path.exists(args.status_file):
            try:
                with open(args.status_file, 'r') as f:
                    sd = json.load(f)
                    start_time = sd.get("time", 0)
            except:
                pass

        tts = Qwen3TTSModel.from_pretrained(
            MODEL_NAME, 
            device_map=DEVICE, 
            dtype=torch.float32,
            attn_implementation="eager"
        )

        all_audios = []
        speaker_pause = np.zeros(int(SR * SPEAKER_PAUSE))
        chunk_pause = np.zeros(int(SR * CHUNK_PAUSE))

        for i, (voice_id, text) in enumerate(segments):
            current_num = i + 1
            print(f"Procesando segmento {current_num}/{total_segments} con voz '{voice_id}'...", flush=True)
            update_status(args.status_file, current_num, total_segments, start_time)
            
            voice_config = VOICES[voice_id]
            with open(voice_config["text"], "r", encoding="utf-8") as f:
                ref_text_content = f.read().strip()

            # Split segment into safe chunks if it's too long
            chunks = get_safe_chunks(text)
            for j, chunk in enumerate(chunks):
                print(f"  - Generando fragmento {j+1}/{len(chunks)}...", flush=True)
                wavs, _ = tts.generate_voice_clone(
                    text=chunk,
                    ref_audio=[voice_config["audio"]],
                    ref_text=[ref_text_content],
                    x_vector_only_mode=False,
                    do_sample=True,
                    temperature=0.5,
                    top_p=0.8
                )
                all_audios.append(wavs[0])
                
                # Pausa entre fragmentos del MISMO personaje
                if j < len(chunks) - 1:
                    all_audios.append(chunk_pause)
            
            # Pausa entre DIFERENTES personajes
            if i < len(segments) - 1:
                all_audios.append(speaker_pause)

        if all_audios:
            print("Concatenando y guardando diálogo completo...", flush=True)
            final_wav = np.concatenate(all_audios)
            sf.write(args.output, final_wav, SR)
            
            # Final status update
            if args.status_file:
                try:
                    with open(args.status_file, 'w') as f:
                        json.dump({
                            "status": "completed", 
                            "current": total_segments, 
                            "total": total_segments, 
                            "time": start_time
                        }, f)
                except:
                    pass
                    
            print(f"DONE: {args.output}", flush=True)
            
    except Exception as e:
        print(f"ERROR: {str(e)}", flush=True)
        import traceback
        traceback.print_exc()
        exit(1)

if __name__ == "__main__":
    main()
