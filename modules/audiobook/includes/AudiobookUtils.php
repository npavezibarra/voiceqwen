<?php
namespace VoiceQwen\Audiobook;

if (!defined('ABSPATH')) {
    exit;
}

class AudiobookUtils
{
    /**
     * Get cover URL for a post.
     */
    public static function get_cover_url($post_id): string
    {
        $key = get_post_meta($post_id, '_vq_cover_key', true);
        if (!$key) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/voiceqwen-audiobook/' . $key;
    }

    /**
     * Get background URL for a post.
     */
    public static function get_background_url($post_id): string
    {
        $key = get_post_meta($post_id, '_vq_background_key', true);
        if (!$key) {
            return '';
        }

        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/voiceqwen-audiobook/' . $key;
    }

    /**
     * Helper to get WAV duration in "MM:SS" format.
     */
    public static function get_wav_duration_formatted($file_path): string {
        $seconds = self::get_wav_duration($file_path);
        if ($seconds <= 0) return '00:00';
        
        $mins = floor($seconds / 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d', $mins, $secs);
    }

    /**
     * Helper to read WAV header and calculate duration in seconds.
     */
    public static function get_wav_duration($file): float {
        if (!file_exists($file) || !is_readable($file)) return 0;
        
        $fp = fopen($file, 'r');
        if (!$fp) return 0;
        
        $header = fread($fp, 12);
        if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
            fclose($fp);
            return 0;
        }

        $duration = 0;
        $bytes_per_sec = 0;
        while (!feof($fp)) {
            $chunk_header = fread($fp, 8);
            if (strlen($chunk_header) < 8) break;
            
            $chunk_id = substr($chunk_header, 0, 4);
            $chunk_size_raw = substr($chunk_header, 4, 4);
            $chunk_size_arr = unpack('V', $chunk_size_raw);
            $chunk_size = $chunk_size_arr[1];

            if ($chunk_id === 'fmt ') {
                $fmt_data = fread($fp, $chunk_size);
                $fmt = unpack('vformat/vchannels/Vsamplerate/Vbytespersec/vblockalign/vbitspersample', substr($fmt_data, 0, 16));
                $bytes_per_sec = $fmt['bytespersec'];
            } elseif ($chunk_id === 'data') {
                if ($bytes_per_sec > 0) {
                    $duration = $chunk_size / $bytes_per_sec;
                }
                fseek($fp, $chunk_size, SEEK_CUR);
            } else {
                fseek($fp, $chunk_size, SEEK_CUR);
            }
            
            if ($chunk_size % 2 !== 0) {
                fseek($fp, 1, SEEK_CUR);
            }
        }
        
        fclose($fp);
        return (float)$duration;
    }
}
