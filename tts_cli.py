import argparse
import sys
import os
import json
import torch
import soundfile as sf
import numpy as np
import re
import gc
import time
import wave
import shutil
from qwen_tts import Qwen3TTSModel

# --- CONFIGURACIÓN ---
MODEL_NAME = "Qwen/Qwen3-TTS-12Hz-1.7B-Base"
# Force CPU for absolute stability on Mac environments where MPS hangs
DEVICE = "cpu"
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

def get_safe_chunks(text, max_words=15):
    # First, split by punctuation to keep sentences together where possible
    sentences = re.split(r'(?<=[.!?]) +', text.replace('\n', ' '))
    final_chunks = []
    
    for s in sentences:
        words = s.split()
        if not words: continue
        
        # If a single "sentence" is still too long, split it by word count
        if len(words) > max_words:
            for i in range(0, len(words), max_words):
                chunk_words = words[i:i + max_words]
                final_chunks.append(" ".join(chunk_words))
        else:
            final_chunks.append(s)
            
    # Now group the small chunks into larger chunks that don't exceed max_words
    grouped_chunks = []
    current_chunk = []
    current_count = 0
    
    for c in final_chunks:
        word_count = len(c.split())
        if current_count + word_count <= max_words or not current_chunk:
            current_chunk.append(c)
            current_count += word_count
        else:
            grouped_chunks.append(" ".join(current_chunk))
            current_chunk = [c]
            current_count = word_count
            
    if current_chunk:
        grouped_chunks.append(" ".join(current_chunk))
        
    return grouped_chunks

def update_status(file_path, **kwargs):
    if not file_path:
        return
    try:
        # Load existing for persistence if possible
        data = {}
        if os.path.exists(file_path):
            try:
                with open(file_path, 'r') as f:
                    data = json.load(f)
            except: pass
            
        data.update(kwargs)
        if "status" not in data:
            data["status"] = "processing"
            
        with open(file_path, 'w') as f:
            json.dump(data, f)
    except:
        pass

def take_a_breath(file_path, current, total, start_time, message="Resting / Cleaning RAM..."):
    print(f"--- {message} ---", flush=True)
    update_status(
        file_path, 
        current=current, 
        total=total, 
        time=start_time, 
        stage="resting", 
        message=message
    )
    
    gc.collect()
    if DEVICE == "mps":
        torch.mps.empty_cache()
    elif DEVICE == "cuda":
        torch.cuda.empty_cache()
    
    time.sleep(1.0)

class ChunkManager:
    def __init__(self, output_path):
        self.output_path = output_path
        self.chunks_dir = output_path + ".chunks"
        self.manifest_path = os.path.join(self.chunks_dir, "manifest.json")
        if not os.path.exists(self.chunks_dir):
            os.makedirs(self.chunks_dir)
        
    def get_manifest(self):
        if os.path.exists(self.manifest_path):
            try:
                with open(self.manifest_path, "r") as f:
                    return json.load(f)
            except: pass
        return {"completed": []}

    def save_chunk(self, index, audio_data, samplerate):
        chunk_file = os.path.join(self.chunks_dir, f"chunk_{index}.wav")
        # Convert to int16 for easier wave-module merging and space saving
        if audio_data.dtype != np.int16:
            audio_data = (audio_data * 32767).clip(-32768, 32767).astype(np.int16)
        sf.write(chunk_file, audio_data, samplerate, subtype='PCM_16')
        
        manifest = self.get_manifest()
        if index not in manifest["completed"]:
            manifest["completed"].append(index)
        with open(self.manifest_path, "w") as f:
            json.dump(manifest, f)
        return chunk_file

    def is_chunk_done(self, index):
        manifest = self.get_manifest()
        chunk_file = os.path.join(self.chunks_dir, f"chunk_{index}.wav")
        return index in manifest["completed"] and os.path.exists(chunk_file)

    def merge_all(self, output_file, total_chunks):
        print(f"Concatenando {total_chunks} fragmentos...", flush=True)
        try:
            with wave.open(output_file, 'wb') as outfile:
                for i in range(total_chunks):
                    chunk_file = os.path.join(self.chunks_dir, f"chunk_{i}.wav")
                    if not os.path.exists(chunk_file):
                        print(f"ERROR: Falta el fragmento {i}", flush=True)
                        return False
                    with wave.open(chunk_file, 'rb') as infile:
                        if i == 0:
                            outfile.setparams(infile.getparams())
                        outfile.writeframes(infile.readframes(infile.getnframes()))
            return True
        except Exception as e:
            print(f"Error al unir WAVs: {e}", flush=True)
            return False

    def cleanup(self):
        try:
            if os.path.exists(self.chunks_dir):
                shutil.rmtree(self.chunks_dir)
        except: pass

def main():
    try:
        parser = argparse.ArgumentParser(description="Qwen TTS CLI")
        parser.add_argument("--text", type=str, required=True, help="Text to convert to speech")
        parser.add_argument("--voice", type=str, required=True, help="Voice to use")
        parser.add_argument("--output", type=str, required=True, help="Output wav file path")
        parser.add_argument("--status_file", type=str, help="Path to status.json file")
        parser.add_argument("--stability", type=float, default=0.7, help="Stability (0.1-1.0)")
        parser.add_argument("--max_words", type=int, default=30, help="Máximo de palabras por segmento")
        parser.add_argument("--pause_time", type=float, default=0.5, help="Pausa entre segmentos (segundos)")
        args = parser.parse_args()

        if args.voice not in VOICES:
            print(f"ERROR: Voice '{args.voice}' not found. Available: {', '.join(VOICES.keys())}", flush=True)
            exit(1)

        voice_config = VOICES[args.voice]
        
        with open(voice_config["text"], "r", encoding="utf-8") as f:
            ref_text_content = f.read().strip()

        print(f"Cargando modelo en {DEVICE} (float32)...", flush=True)
        tts = Qwen3TTSModel.from_pretrained(
            MODEL_NAME, 
            device_map=DEVICE, 
            dtype=torch.float32,
            attn_implementation="eager"
        )
        
        # Proactive cleanup
        gc.collect()

        # Process chunking with user defined max_words
        chunks = get_safe_chunks(args.text, max_words=args.max_words)
        total_chunks = len(chunks)
        print(f"Dividido en {total_chunks} fragmentos.", flush=True)
        
        manager = ChunkManager(args.output)
        
        start_time = int(time.time())
        if args.status_file and os.path.exists(args.status_file):
            try:
                with open(args.status_file, 'r') as f:
                    sd = json.load(f)
                    if sd.get("time"): start_time = sd.get("time")
            except: pass

        for i, chunk in enumerate(chunks):
            current_num = i + 1
            
            # Resumption Check
            if manager.is_chunk_done(i):
                print(f"Fragmento {current_num}/{total_chunks} ya existe en disco. Saltando...", flush=True)
                continue

            msg = f"Generando fragmento {current_num}/{total_chunks}"
            print(f"Procesando {msg}...", flush=True)
            update_status(
                args.status_file, 
                current=current_num, 
                total=total_chunks, 
                stage="generating",
                message=msg,
                time=start_time
            )

            current_temp = max(0.01, 1.1 - args.stability)
            current_top_p = max(0.1, 0.92 - (args.stability * 0.22))

            wavs, _ = tts.generate_voice_clone(
                text=chunk,
                ref_audio=[voice_config["audio"]],
                ref_text=[ref_text_content],
                x_vector_only_mode=False,
                do_sample=True,
                temperature=current_temp,
                top_p=current_top_p,
                max_new_tokens=1024 # Safety cap
            )
            
            # Prepare chunk with pause
            pause = np.zeros(int(SR * args.pause_time))
            complete_audio = np.concatenate([wavs[0], pause])
            manager.save_chunk(i, complete_audio, SR)
            
            # RAM Cleanup
            del wavs
            del complete_audio
            take_a_breath(args.status_file, current_num, total_chunks, start_time)

        # Final Merging
        print("Finalizando y uniendo archivos...", flush=True)
        update_status(
            args.status_file,
            current=total_chunks,
            total=total_chunks,
            stage="concatenating",
            message="Limpiando y uniendo audio final...",
            time=start_time
        )
        
        if manager.merge_all(args.output, total_chunks):
            manager.cleanup()
            update_status(
                args.status_file, 
                status="completed", 
                current=total_chunks, 
                total=total_chunks, 
                time=start_time
            )
            print(f"DONE: {args.output}", flush=True)
        else:
            update_status(args.status_file, status="error", message="Error al unir fragmentos de audio")
            exit(1)
            
    except Exception as e:
        print(f"ERROR: {str(e)}", flush=True)
        import traceback
        traceback.print_exc()
        exit(1)

if __name__ == "__main__":
    main()

