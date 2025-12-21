<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Service;

use OCP\IConfig;
use Psr\Log\LoggerInterface;

class TranscodingService {
    private IConfig $config;
    private LoggerInterface $logger;

    public function __construct(
        IConfig $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    private ?string $lastError = null;
    private $progressCallback = null;

    /**
     * Get the last error message from transcoding operations
     *
     * @return string|null
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }

    /**
     * Set a callback function to receive progress updates
     *
     * @param callable|null $callback Function that receives progress percentage (0-100)
     */
    public function setProgressCallback(?callable $callback): void {
        $this->progressCallback = $callback;
    }

    /**
     * Check if FFmpeg is available on the system
     *
     * @return bool
     */
    public function isFfmpegAvailable(): bool {
        return $this->checkFFmpeg();
    }

    /**
     * Check if ffprobe is available on the system
     *
     * @return bool
     */
    public function isFfprobeAvailable(): bool {
        $output = [];
        $returnCode = 0;
        exec('ffprobe -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Transcode a video file using FFmpeg
     *
     * @param string $inputPath Input file path
     * @param string $outputPath Output file path
     * @param string|null $preset Optional preset (e.g., 'h265_crf23', 'h264_crf23')
     * @return bool Success
     */
    public function transcodeVideo(string $inputPath, string $outputPath, ?string $preset = null): bool {
        $this->lastError = null;

        if (!file_exists($inputPath)) {
            $this->lastError = "Input file not found: {$inputPath}";
            $this->logger->error($this->lastError);
            return false;
        }

        // Check if FFmpeg is available
        if (!$this->checkFFmpeg()) {
            $this->lastError = "FFmpeg is not installed or not in PATH. Please install FFmpeg to enable video transcoding.";
            $this->logger->error($this->lastError);
            return false;
        }

        // Parse preset or use config defaults
        [$videoCodec, $videoCRF] = $this->parsePreset($preset);

        $maxWidth = (int) $this->config->getAppValue('downtranscoder', 'max_video_width', '3840');
        $maxHeight = (int) $this->config->getAppValue('downtranscoder', 'max_video_height', '2160');
        $maxThreads = (int) $this->config->getAppValue('downtranscoder', 'max_ffmpeg_threads', '0');

        $codecName = $this->getCodecName($videoCodec);

        // Build scale filter to downscale only (never upscale)
        // Using min() ensures we only scale down if video exceeds max resolution
        $scaleFilter = '';
        if ($maxWidth > 0 && $maxHeight > 0) {
            $scaleFilter = sprintf(
                "-vf \"scale='min(%d,iw)':'min(%d,ih)':force_original_aspect_ratio=decrease\"",
                $maxWidth,
                $maxHeight
            );
        } elseif ($maxWidth > 0) {
            $scaleFilter = sprintf("-vf \"scale='min(%d,iw)':-2\"", $maxWidth);
        } elseif ($maxHeight > 0) {
            $scaleFilter = sprintf("-vf \"scale=-2:'min(%d,ih)'\"", $maxHeight);
        }

        // Build threads parameter
        $threadsParam = '';
        if ($maxThreads > 0) {
            $threadsParam = sprintf('-threads %d', $maxThreads);
        }

        // Add -progress flag to get detailed progress output
        // Note: -progress must come before -i
        $command = sprintf(
            'ffmpeg -progress pipe:2 -i %s %s -c:v %s -crf %s %s -c:a copy -movflags +faststart %s',
            escapeshellarg($inputPath),
            $threadsParam,
            escapeshellarg($codecName),
            escapeshellarg($videoCRF),
            $scaleFilter,
            escapeshellarg($outputPath)
        );

        $this->logger->info("Executing FFmpeg command: {$command}");

        // Get video duration first for progress calculation
        $duration = $this->getVideoDuration($inputPath);
        if ($duration > 0) {
            $this->logger->info("Video duration detected: {$duration} seconds - progress tracking enabled");
        } else {
            $this->logger->warning("Could not determine video duration - progress tracking will not be available");
        }

        // Execute command with proc_open to capture real-time output
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            $this->lastError = "Failed to start FFmpeg process. FFmpeg may not be installed or accessible.";
            $this->logger->error($this->lastError);
            return false;
        }

        fclose($pipes[0]); // Close stdin

        // Set non-blocking mode for reading
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $lastProgress = 0;

        // Read output in real-time
        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $stdout = fread($pipes[1], 4096);
            $stderr = fread($pipes[2], 4096);

            if ($stdout !== false && $stdout !== '') {
                $output .= $stdout;
            }

            if ($stderr !== false && $stderr !== '') {
                $output .= $stderr;

                // Parse progress from FFmpeg output (progress goes to stderr/pipe:2)
                if ($duration > 0 && preg_match('/out_time_ms=(\d+)/', $stderr, $matches)) {
                    $currentTime = (int)$matches[1] / 1000000; // Convert microseconds to seconds
                    $progress = min(99, (int)(($currentTime / $duration) * 100));

                    if ($progress > $lastProgress && $this->progressCallback) {
                        call_user_func($this->progressCallback, $progress);
                        $lastProgress = $progress;
                        $this->logger->debug("FFmpeg progress: {$progress}% ({$currentTime}s / {$duration}s)");
                    }
                }
            }

            usleep(100000); // Sleep 100ms to avoid busy loop
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            $this->lastError = "FFmpeg failed with return code {$returnCode}";
            $this->logger->error("{$this->lastError}: {$output}");
            return false;
        }

        // Log if progress tracking was working
        if ($duration > 0) {
            if ($lastProgress > 0) {
                $this->logger->info("FFmpeg progress tracking worked - last progress: {$lastProgress}%");
            } else {
                $this->logger->warning("FFmpeg progress tracking did not receive any progress updates (duration was {$duration}s). Check if -progress flag is supported.");
            }
        }

        // Call progress callback one final time with 100%
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, 100);
        }

        if (!file_exists($outputPath)) {
            $this->lastError = "Output file was not created: {$outputPath}";
            $this->logger->error($this->lastError);
            return false;
        }

        $inputSize = filesize($inputPath);
        $outputSize = filesize($outputPath);
        $reduction = (($inputSize - $outputSize) / $inputSize) * 100;

        $this->logger->info(sprintf(
            "Video transcoding successful. Size reduction: %.2f%% (%.2f MB -> %.2f MB)",
            $reduction,
            $inputSize / (1024 * 1024),
            $outputSize / (1024 * 1024)
        ));

        return true;
    }

    /**
     * Compress an image file using FFmpeg
     *
     * @param string $inputPath Input file path
     * @param string $outputPath Output file path
     * @return bool Success
     */
    public function compressImage(string $inputPath, string $outputPath): bool {
        $this->lastError = null;

        if (!file_exists($inputPath)) {
            $this->lastError = "Input file not found: {$inputPath}";
            $this->logger->error($this->lastError);
            return false;
        }

        // Check if FFmpeg is available
        if (!$this->checkFFmpeg()) {
            $this->lastError = "FFmpeg is not installed or not in PATH. Please install FFmpeg to enable image compression.";
            $this->logger->error($this->lastError);
            return false;
        }

        $imageQuality = (int) $this->config->getAppValue('downtranscoder', 'image_quality', '85');
        $maxWidth = (int) $this->config->getAppValue('downtranscoder', 'max_image_width', '1920');
        $maxHeight = (int) $this->config->getAppValue('downtranscoder', 'max_image_height', '1080');

        // Build scale filter if dimensions are set
        $scaleFilter = '';
        if ($maxWidth > 0 && $maxHeight > 0) {
            $scaleFilter = sprintf(
                "-vf \"scale='min(%d,iw)':'min(%d,ih)':force_original_aspect_ratio=decrease\"",
                $maxWidth,
                $maxHeight
            );
        } elseif ($maxWidth > 0) {
            $scaleFilter = sprintf("-vf \"scale=%d:-1\"", $maxWidth);
        } elseif ($maxHeight > 0) {
            $scaleFilter = sprintf("-vf \"scale=-1:%d\"", $maxHeight);
        }

        // Convert quality (1-100) to FFmpeg q:v (1-31, lower is better)
        $qValue = (int) ((100 - $imageQuality) / 100 * 30) + 1;

        $command = sprintf(
            'ffmpeg -i %s %s -q:v %d %s 2>&1',
            escapeshellarg($inputPath),
            $scaleFilter,
            $qValue,
            escapeshellarg($outputPath)
        );

        $this->logger->info("Executing FFmpeg command: {$command}");

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->lastError = "FFmpeg image compression failed with return code {$returnCode}";
            $this->logger->error("{$this->lastError}: " . implode("\n", $output));
            return false;
        }

        if (!file_exists($outputPath)) {
            $this->lastError = "Output file was not created: {$outputPath}";
            $this->logger->error($this->lastError);
            return false;
        }

        $inputSize = filesize($inputPath);
        $outputSize = filesize($outputPath);
        $reduction = (($inputSize - $outputSize) / $inputSize) * 100;

        $this->logger->info(sprintf(
            "Image compression successful. Size reduction: %.2f%% (%.2f MB -> %.2f MB)",
            $reduction,
            $inputSize / (1024 * 1024),
            $outputSize / (1024 * 1024)
        ));

        return true;
    }

    /**
     * Check if FFmpeg is available
     *
     * @return bool
     */
    private function checkFFmpeg(): bool {
        $output = [];
        $returnCode = 0;
        exec('ffmpeg -version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    /**
     * Parse preset string into codec and CRF values
     *
     * @param string|null $preset Preset name (e.g., 'h265_crf23', 'h264_crf23')
     * @return array [codec, crf]
     */
    private function parsePreset(?string $preset): array {
        if ($preset === null || $preset === '') {
            // Use config defaults
            $videoCodec = $this->config->getAppValue('downtranscoder', 'video_codec', 'H265');
            $videoCRF = $this->config->getAppValue('downtranscoder', 'video_crf', '26');
            return [$videoCodec, $videoCRF];
        }

        // Parse preset format: codec_crfXX
        // Examples: h265_crf23, h265_crf26, h265_crf28, h264_crf23
        $presetMap = [
            'h265_crf23' => ['H265', '23'],
            'h265_crf26' => ['H265', '26'],
            'h265_crf28' => ['H265', '28'],
            'h264_crf23' => ['H264', '23'],
        ];

        if (isset($presetMap[$preset])) {
            return $presetMap[$preset];
        }

        // Fallback to config defaults if preset is invalid
        $this->logger->warning("Unknown preset '{$preset}', using config defaults");
        $videoCodec = $this->config->getAppValue('downtranscoder', 'video_codec', 'H265');
        $videoCRF = $this->config->getAppValue('downtranscoder', 'video_crf', '26');
        return [$videoCodec, $videoCRF];
    }

    /**
     * Get FFmpeg codec name from configuration
     *
     * @param string $codec Codec identifier
     * @return string FFmpeg codec name
     */
    private function getCodecName(string $codec): string {
        return match($codec) {
            'H264' => 'libx264',
            'H265' => 'libx265',
            'VP9' => 'libvpx-vp9',
            'AV1' => 'libaom-av1',
            default => 'libx265',
        };
    }

    /**
     * Get video duration in seconds using ffprobe
     *
     * @param string $inputPath Path to video file
     * @return float Duration in seconds, or 0 if unable to determine
     */
    private function getVideoDuration(string $inputPath): float {
        // Check if ffprobe is available
        if (!$this->isFfprobeAvailable()) {
            $this->logger->warning("ffprobe is not installed or not in PATH. Progress tracking will not be available.");
            return 0.0;
        }

        $command = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>&1',
            escapeshellarg($inputPath)
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode === 0 && !empty($output[0])) {
            return (float)$output[0];
        }

        $this->logger->warning("Could not determine video duration for {$inputPath}");
        return 0.0;
    }
}
