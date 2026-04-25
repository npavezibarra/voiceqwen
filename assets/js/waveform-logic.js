/**
 * VoiceQwen Waveform Editor Module
 * Handles AudioBuffer manipulation, joining, splitting, and undo state.
 */

window.VoiceQwen = window.VoiceQwen || {};

(function($) {
    // Shared State
    window.VoiceQwen.activeAudioBuffer = null;
    window.VoiceQwen.copiedAudioBuffer = null;
    window.VoiceQwen.waveUndoStack = [];
    window.VoiceQwen.audioCtx = null;

    /**
     * Get or initialize AudioContext (singleton)
     */
    window.VoiceQwen.getAudioCtx = function() {
        if (!window.VoiceQwen.audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                console.error("VoiceQwen: AudioContext not supported in this browser.");
                return null;
            }
            window.VoiceQwen.audioCtx = new AudioContextClass({
                latencyHint: 'balanced'
            });
        }
        if (window.VoiceQwen.audioCtx.state === 'suspended') {
            window.VoiceQwen.audioCtx.resume();
        }
        return window.VoiceQwen.audioCtx;
    };

    /**
     * Inserts one AudioBuffer into another at a specific time (seconds)
     */
    window.VoiceQwen.insertAudioAt = async function(orig, insert, time) {
        console.log("WaveformEditor: insertAudioAt called. Orig samples:", orig ? orig.length : 0, "Insert samples:", insert.length, "at time:", time);
        if (!orig) return insert;
        
        const sampleRate = orig.sampleRate;
        const frameStart = Math.floor(time * sampleRate);
        const newLength = orig.length + insert.length;
        console.log("WaveformEditor: New buffer length will be:", newLength, "at SR:", sampleRate);
        const newBuffer = window.VoiceQwen.getAudioCtx().createBuffer(orig.numberOfChannels, newLength, sampleRate);

        for (let i = 0; i < orig.numberOfChannels; i++) {
            const chan = newBuffer.getChannelData(i);
            const origChan = orig.getChannelData(i);
            const insertChan = i < insert.numberOfChannels ? insert.getChannelData(i) : insert.getChannelData(0);

            // 1. Copy original before the split point
            chan.set(origChan.subarray(0, frameStart));
            // 2. Copy the new clip at the split point
            chan.set(insertChan, frameStart);
            // 3. Copy the rest of the original after the new clip
            chan.set(origChan.subarray(frameStart), frameStart + insert.length);
        }
        return newBuffer;
    };

    /**
     * Deletes a segment from an AudioBuffer
     */
    window.VoiceQwen.deleteSegment = function(orig, start, end) {
        if (!orig) return null;
        
        const sampleRate = orig.sampleRate;
        const frameStart = Math.floor(start * sampleRate);
        const frameEnd = Math.floor(end * sampleRate);
        const deleteCount = frameEnd - frameStart;
        const newLength = orig.length - deleteCount;
        
        if (newLength <= 0) return null;

        const newBuffer = window.VoiceQwen.getAudioCtx().createBuffer(orig.numberOfChannels, newLength, sampleRate);

        for (let i = 0; i < orig.numberOfChannels; i++) {
            const chan = newBuffer.getChannelData(i);
            const origChan = orig.getChannelData(i);
            chan.set(origChan.subarray(0, frameStart));
            chan.set(origChan.subarray(frameEnd), frameStart);
        }
        return newBuffer;
    };

    /**
     * Extracts a segment from an AudioBuffer
     */
    window.VoiceQwen.extractSegment = function(orig, start, end) {
        if (!orig) return null;
        
        const sampleRate = orig.sampleRate;
        const frameStart = Math.floor(start * sampleRate);
        const frameEnd = Math.floor(end * sampleRate);
        const length = frameEnd - frameStart;
        
        if (length <= 0) return null;

        const newBuffer = window.VoiceQwen.getAudioCtx().createBuffer(orig.numberOfChannels, length, sampleRate);

        for (let i = 0; i < orig.numberOfChannels; i++) {
            const chan = newBuffer.getChannelData(i);
            const origChan = orig.getChannelData(i);
            chan.set(origChan.subarray(frameStart, frameEnd));
        }
        return newBuffer;
    };

    /**
     * Converts AudioBuffer to WAV Blob
     */
    window.VoiceQwen.audioBufferToWav = function(buffer) {
        let numOfChan = buffer.numberOfChannels,
            length = buffer.length * numOfChan * 2 + 44, // 16-bit PCM
            bufferArr = new ArrayBuffer(length),
            view = new DataView(bufferArr),
            channels = [], i, sample,
            offset = 0,
            pos = 0;

        function setUint16(data) { view.setUint16(pos, data, true); pos += 2; }
        function setUint32(data) { view.setUint32(pos, data, true); pos += 4; }

        setUint32(0x46464952); setUint32(length - 8); setUint32(0x45564157); // RIFF/WAVE
        setUint32(0x20746d66); setUint32(16); setUint16(1); // fmt chunk
        setUint16(numOfChan);
        setUint32(Math.round(buffer.sampleRate));
        setUint32(Math.round(buffer.sampleRate) * 2 * numOfChan);
        setUint16(numOfChan * 2); setUint16(16);
        setUint32(0x61746164); setUint32(length - pos - 4); // data chunk

        for(i = 0; i < buffer.numberOfChannels; i++) channels.push(buffer.getChannelData(i));

        while(pos < length) {
            for(i = 0; i < numOfChan; i++) {
                sample = Math.max(-1, Math.min(1, channels[i][offset]));
                sample = (sample < 0 ? sample * 0x8000 : sample * 0x7FFF);
                view.setInt16(pos, sample, true); 
                pos += 2;
            }
            offset++;
        }
        return new Blob([bufferArr], {type: "audio/wav"});
    };

})(jQuery);
