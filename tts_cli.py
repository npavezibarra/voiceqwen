import torch
import soundfile as sf
import numpy as np
import re
import os
import argparse
from qwen_tts import Qwen3TTSModel

# --- CONFIGURACIÓN ---
MODEL_NAME = "Qwen/Qwen3-TTS-12Hz-1.7B-Base"
DEVICE = "mps" if torch.backends.mps.is_available() else "cpu"
SR = 24000 

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

def get_safe_chunks(text, max_words=45):
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

def main():
    try:
        parser = argparse.ArgumentParser(description="Qwen TTS CLI")
        parser.add_argument("--text", type=str, required=True, help="Text to convert to speech")
        parser.add_argument("--voice", type=str, required=True, help="Voice to use")
        parser.add_argument("--output", type=str, required=True, help="Output wav file path")
        args = parser.parse_args()

        if args.voice not in VOICES:
            print(f"ERROR: Voice '{args.voice}' not found. Available: {', '.join(VOICES.keys())}", flush=True)
            exit(1)

        voice_config = VOICES[args.voice]
        
        with open(voice_config["text"], "r", encoding="utf-8") as f:
            ref_text_content = f.read().strip()

        print(f"Cargando modelo en {DEVICE} (float32)...", flush=True)
        # Matches original working code from Desktop
        tts = Qwen3TTSModel.from_pretrained(
            MODEL_NAME, 
            device_map=DEVICE, 
            dtype=torch.float32,
            attn_implementation="eager"
        )

        chunks = get_safe_chunks(args.text)
        print(f"Dividido en {len(chunks)} fragmentos.", flush=True)
        all_audios = []

        for i, chunk in enumerate(chunks):
            print(f"Procesando fragmento {i+1}/{len(chunks)}...", flush=True)
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
            
            # Silence
            pause = np.zeros(int(SR * 1.2))
            all_audios.append(pause)

        if all_audios:
            print("Concatenando y guardando...", flush=True)
            final_wav = np.concatenate(all_audios)
            sf.write(args.output, final_wav, SR)
            print(f"DONE: {args.output}", flush=True)
            
    except Exception as e:
        print(f"ERROR: {str(e)}", flush=True)
        import traceback
        traceback.print_exc()
        exit(1)

if __name__ == "__main__":
    main()
