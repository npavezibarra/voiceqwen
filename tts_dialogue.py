import argparse
import sys
import os
import json
import torch
import soundfile as sf
import numpy as np
import re
import unicodedata
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
SPEAKER_PAUSE = 0.2  # Silencio entre diferentes hablantes
CHUNK_PAUSE = 0.5    # Silencio entre fragmentos del mismo hablante (si el texto es muy largo)
MAX_WORDS = 15       # Máximo de palabras por fragmento para estabilidad (reducido para brillo constante)

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
    text = unicodedata.normalize('NFKC', text)
    return text

def parse_dialogue(text):
    """
    Parses text like '[Fernando]Hola[/Fernando] [Mary Rose]Hola Fernando[/Mary Rose]'
    Returns a list of (voice_id, segment_text)
    """
    pattern = r'\[([^\]]+)\](.*?)\[\/\1\]'
    matches = re.findall(pattern, text, re.DOTALL)
    
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

def update_status(file_path, **kwargs):
    if not file_path:
        return
    try:
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
        parser = argparse.ArgumentParser(description="Qwen TTS Dialogue CLI")
        parser.add_argument("--text", type=str, required=True, help="Dialogue text with tags")
        parser.add_argument("--output", type=str, required=True, help="Output wav file path")
        parser.add_argument("--status_file", type=str, help="Path to status.json file")
        parser.add_argument("--stability", type=float, default=0.7, help="Stability (0.1-1.0)")
        args = parser.parse_args()

        segments = parse_dialogue(args.text)
        if not segments:
            print("ERROR: No valid dialogue tags found. Format: [VoiceName]Text[/VoiceName]", flush=True)
            exit(1)

        total_segments = len(segments)
        print(f"Cargando modelo en {DEVICE} (float32)...", flush=True)
        
        # Get start time
        manager = ChunkManager(args.output)
        start_time = int(time.time())
        if args.status_file and os.path.exists(args.status_file):
            try:
                with open(args.status_file, 'r') as f:
                    sd = json.load(f)
                    if sd.get("time"): start_time = sd.get("time")
            except: pass

        print(f"Cargando modelo en {DEVICE} (float32)...", flush=True)
        tts = Qwen3TTSModel.from_pretrained(
            MODEL_NAME, 
            device_map=DEVICE, 
            dtype=torch.float32,
            attn_implementation="eager"
        )
        
        # Proactive cleanup
        gc.collect()
        if DEVICE == "mps": torch.mps.empty_cache()

        speaker_pause = np.zeros(int(SR * SPEAKER_PAUSE))
        chunk_pause = np.zeros(int(SR * CHUNK_PAUSE))

        # Flatten segments into a simple list of task chunks with pauses built-in
        # to simplify resumption logic (one manifest entry per 'physical' wav chunk)
        generation_tasks = []
        for i, (voice_id, text) in enumerate(segments):
            chunks = get_safe_chunks(text)
            for j, chunk_text in enumerate(chunks):
                is_last_chunk = (j == len(chunks) - 1)
                is_last_segment = (i == len(segments) - 1)
                
                # Determine pause after this chunk
                if not is_last_chunk:
                    p = chunk_pause
                elif not is_last_segment:
                    p = speaker_pause
                else:
                    p = None
                
                generation_tasks.append({
                    "voice_id": voice_id,
                    "text": chunk_text,
                    "pause": p,
                    "segment_idx": i+1,
                    "chunk_idx": j+1,
                    "total_chunks": len(chunks)
                })

        total_tasks = len(generation_tasks)
        for i, task in enumerate(generation_tasks):
            current_num = i + 1
            
            if manager.is_chunk_done(i):
                print(f"Tarea {current_num}/{total_tasks} ya existe. Saltando...", flush=True)
                continue

            msg = f"Generando diálogo {task['segment_idx']}/{total_segments} (parte {task['chunk_idx']}/{task['total_chunks']}) - Voz: {task['voice_id']}"
            print(f"  - {msg}...", flush=True)
            
            update_status(
                args.status_file, 
                current=task['segment_idx'], 
                total=total_segments, 
                sub_current=task['chunk_idx'],
                sub_total=task['total_chunks'],
                voice=task['voice_id'],
                stage="generating",
                message=msg,
                time=start_time
            )

            voice_config = VOICES[task['voice_id']]
            with open(voice_config["text"], "r", encoding="utf-8") as f:
                ref_text_content = f.read().strip()

            current_temp = max(0.01, 1.1 - args.stability)
            current_top_p = max(0.1, 0.92 - (args.stability * 0.22))

            wavs, _ = tts.generate_voice_clone(
                text=task['text'],
                ref_audio=[voice_config["audio"]],
                ref_text=[ref_text_content],
                x_vector_only_mode=False,
                do_sample=True,
                temperature=current_temp,
                top_p=current_top_p,
                max_new_tokens=1024 # Safety cap
            )
            
            audio = wavs[0]
            if task['pause'] is not None:
                audio = np.concatenate([audio, task['pause']])
            
            manager.save_chunk(i, audio, SR)
            
            del wavs
            del audio
            take_a_breath(args.status_file, task['segment_idx'], total_segments, start_time)

        # Final Merging
        print("Finalizando y uniendo diálogo completo...", flush=True)
        update_status(
            args.status_file,
            current=total_segments,
            total=total_segments,
            stage="concatenating",
            message="Uniendo fragmentos de diálogo...",
            time=start_time
        )
        
        if manager.merge_all(args.output, total_tasks):
            manager.cleanup()
            update_status(
                args.status_file, 
                status="completed", 
                current=total_segments, 
                total=total_segments, 
                time=start_time
            )
            print(f"DONE: {args.output}", flush=True)
        else:
            update_status(args.status_file, status="error", message="Error al unir diálogo")
            exit(1)
            
    except Exception as e:
        print(f"ERROR: {str(e)}", flush=True)
        import traceback
        traceback.print_exc()
        exit(1)

if __name__ == "__main__":
    main()

