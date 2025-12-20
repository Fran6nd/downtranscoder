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

    /**
     * Transcode a video file using FFmpeg
     *
     * @param string $inputPath Input file path
     * @param string $outputPath Output file path
     * @return bool Success
     */
    public function transcodeVideo(string $inputPath, string $outputPath): bool {
        if (!file_exists($inputPath)) {
            $this->logger->error("Input file not found: {$inputPath}");
            return false;
        }

        // Check if FFmpeg is available
        if (!$this->checkFFmpeg()) {
            $this->logger->error("FFmpeg not found on system");
            return false;
        }

        $videoCodec = $this->config->getAppValue('downtranscoder', 'video_codec', 'H265');
        $videoCRF = $this->config->getAppValue('downtranscoder', 'video_crf', '23');
        $maxWidth = (int) $this->config->getAppValue('downtranscoder', 'max_video_width', '3840');
        $maxHeight = (int) $this->config->getAppValue('downtranscoder', 'max_video_height', '2160');

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

        $command = sprintf(
            'ffmpeg -i %s -c:v %s -crf %s %s -c:a copy -movflags +faststart %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($codecName),
            escapeshellarg($videoCRF),
            $scaleFilter,
            escapeshellarg($outputPath)
        );

        $this->logger->info("Executing FFmpeg command: {$command}");

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->logger->error("FFmpeg failed with return code {$returnCode}: " . implode("\n", $output));
            return false;
        }

        if (!file_exists($outputPath)) {
            $this->logger->error("Output file was not created: {$outputPath}");
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
        if (!file_exists($inputPath)) {
            $this->logger->error("Input file not found: {$inputPath}");
            return false;
        }

        // Check if FFmpeg is available
        if (!$this->checkFFmpeg()) {
            $this->logger->error("FFmpeg not found on system");
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
            $this->logger->error("FFmpeg failed with return code {$returnCode}: " . implode("\n", $output));
            return false;
        }

        if (!file_exists($outputPath)) {
            $this->logger->error("Output file was not created: {$outputPath}");
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
}
