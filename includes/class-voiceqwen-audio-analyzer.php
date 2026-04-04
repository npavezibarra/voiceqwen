<?php
/**
 * Service class for Audio Analysis using ffmpeg/ffprobe.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class VoiceQwen_Audio_Analyzer {

    private $ffmpeg_path;
    private $ffprobe_path;

    public function __construct() {
        $this->ffmpeg_path = $this->find_binary( 'ffmpeg' );
        $this->ffprobe_path = $this->find_binary( 'ffprobe' );
    }

    /**
     * Check if dependencies are available.
     */
    public static function check_dependencies() {
        $analyzer = new self();
        return array(
            'ffmpeg'  => ! empty( $analyzer->ffmpeg_path ),
            'ffprobe' => ! empty( $analyzer->ffprobe_path ),
            'paths'   => array(
                'ffmpeg'  => $analyzer->ffmpeg_path,
                'ffprobe' => $analyzer->ffprobe_path,
            )
        );
    }

    /**
     * Locate a binary on the system.
     */
    private function find_binary( $name ) {
        // Try common Mac/Linux paths
        $paths = array(
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/opt/homebrew/bin/' . $name,
        );

        foreach ( $paths as $path ) {
            if ( @is_executable( $path ) ) {
                return $path;
            }
        }

        // Fallback to 'which'
        $output = array();
        $rc = -1;
        @exec( "which " . escapeshellarg( $name ), $output, $rc );
        if ( $rc === 0 && ! empty( $output[0] ) ) {
            return $output[0];
        }

        return '';
    }

    /**
     * Analyze a single WAV file.
     */
    public function analyze_file( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return array( 'success' => false, 'error' => 'Archivo no encontrado: ' . basename($file_path) );
        }

        if ( empty( $this->ffprobe_path ) || empty( $this->ffmpeg_path ) ) {
            return array( 'success' => false, 'error' => 'ffmpeg/ffprobe no instalados.' );
        }

        $results = array(
            'filename'      => basename( $file_path ),
            'path'          => $file_path,
            'success'       => true,
            'duration'      => 0,
            'sample_rate'   => 0,
            'channels'      => 0,
            'bit_depth'     => 'Unknown',
            'peak_db'       => -99,
            'rms_db'        => -99,
            'lufs'          => -99,
            'noise_floor'   => -99,
            'clipping'      => false,
            'checks'        => array(),
            'pass'          => true,
        );

        // 1. Basic probe
        $probe_cmd = sprintf(
            '%s -v error -show_format -show_streams -of json %s',
            escapeshellarg( $this->ffprobe_path ),
            escapeshellarg( $file_path )
        );
        $probe_json = shell_exec( $probe_cmd );
        $probe_data = json_decode( $probe_json, true );

        if ( isset( $probe_data['streams'][0] ) ) {
            $stream = $probe_data['streams'][0];
            $results['duration']    = (float) ( $stream['duration'] ?? 0 );
            $results['sample_rate'] = (int) ( $stream['sample_rate'] ?? 0 );
            $results['channels']    = (int) ( $stream['channels'] ?? 0 );
            $results['bit_depth']   = (string) ( $stream['bits_per_sample'] ?? 'Unknown' );
        }

        // 2. Advanced filters (volumedetect, ebur128, astats)
        $ffmpeg_cmd = sprintf(
            '%s -i %s -af "volumedetect,ebur128,astats" -f null - 2>&1',
            escapeshellarg( $this->ffmpeg_path ),
            escapeshellarg( $file_path )
        );
        $output = shell_exec( $ffmpeg_cmd );

        // Regex parsing
        // Peak Level
        if ( preg_match( '/Peak level dB: ([\-\d\.]+)/', $output, $matches ) ) {
            $results['peak_db'] = (float) $matches[1];
        }
        // RMS Level
        if ( preg_match( '/RMS level dB: ([\-\d\.]+)/', $output, $matches ) ) {
            $results['rms_db'] = (float) $matches[1];
        }
        // Integrated Loudness (LUFS)
        if ( preg_match( '/I:\s+([\-\d\.]+)\s+LUFS/', $output, $matches ) ) {
            $results['lufs'] = (float) $matches[1];
        }
        // Noise Floor (from astats)
        if ( preg_match( '/Noise floor dB: ([\-\d\.inf]+)/', $output, $matches ) ) {
            if ( $matches[1] === '-inf' ) {
                $results['noise_floor'] = -100.0; // Digital silence
            } else {
                $results['noise_floor'] = (float) $matches[1];
            }
        }

        // Clipping check (0.0 dB max volume)
        if ( preg_match( '/max_volume: ([\-\d\.]+)/', $output, $matches ) ) {
             $max_vol = (float)$matches[1];
             if ($max_vol >= 0.0) $results['clipping'] = true;
        }

        // Determine PASS/FAIL
        $fails = array();
        
        // RMS Check (-23 to -18)
        if ( $results['rms_db'] < -23 ) {
            $fails[] = 'RMS too low (below -23 dB)';
        } elseif ( $results['rms_db'] > -18 ) {
            $fails[] = 'RMS too high (above -18 dB)';
        }

        // Peak Check (below -3)
        if ( $results['peak_db'] > -3 ) {
            $fails[] = 'Peak exceeds -3 dB';
        }

        // Noise Floor Check (below -60)
        if ( $results['noise_floor'] > -60 && $results['noise_floor'] !== -99.0 ) {
             // -inf shows up as something else or fails regex
             $fails[] = 'Noise floor too high (above -60 dB)';
        }

        if ( ! empty( $fails ) ) {
            $results['pass']   = false;
            $results['checks'] = $fails;
        }

        return $results;
    }

    /**
     * Compute batch summary and recommendations.
     */
    public function calculate_batch_summary( $all_results ) {
        $total = count( $all_results );
        $passing = 0;
        $rms_values = array();
        $loudest = null;
        $quietest = null;

        foreach ( $all_results as $res ) {
            if ( isset($res['error']) ) continue;
            if ( $res['pass'] ) $passing++;
            $rms_values[] = $res['rms_db'];

            if ( $loudest === null || $res['rms_db'] > $loudest['rms_db'] ) {
                $loudest = $res;
            }
            if ( $quietest === null || $res['rms_db'] < $quietest['rms_db'] ) {
                $quietest = $res;
            }
        }

        if ( empty( $rms_values ) ) return null;

        sort( $rms_values );
        $count = count( $rms_values );
        $median_rms = $rms_values[floor( $count / 2 )];
        $avg_rms = array_sum( $rms_values ) / $count;

        $is_consistent = ( max( $rms_values ) - min( $rms_values ) <= 3.0 );

        // Normalization Recommendation
        $target_rms = -20.0; // Standard target
        $rec_text = '';
        $next_step = '';

        if ( ! $is_consistent ) {
            $rec_text = "The batch is uneven. Variation exceeds 3dB.";
            $next_step = "Normalize all files to the batch median RMS (" . round($median_rms, 2) . " dB).";
        } else {
            $rec_text = "The batch is consistent enough for production.";
            if ( $passing === $total ) {
                $next_step = "All files are compliant. Ready for export.";
            } else {
                $next_step = "Adjust individual outliers then re-analyze.";
            }
        }

        return array(
            'total_files'   => $total,
            'files_passing' => $passing,
            'files_failing' => $total - $passing,
            'loudest'       => $loudest['filename'],
            'quietest'      => $quietest['filename'],
            'median_rms'    => round( $median_rms, 2 ),
            'avg_rms'       => round( $avg_rms, 2 ),
            'is_consistent' => $is_consistent,
            'recommendation_text' => $rec_text,
            'next_step'     => $next_step
        );
    }
}
