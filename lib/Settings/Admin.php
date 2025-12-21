<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class Admin implements ISettings {
    private IConfig $config;

    public function __construct(IConfig $config) {
        $this->config = $config;
    }

    public function getForm(): TemplateResponse {
        // Determine current resolution preset from width/height
        $maxWidth = $this->config->getAppValue('downtranscoder', 'max_video_width', '3840');
        $maxHeight = $this->config->getAppValue('downtranscoder', 'max_video_height', '2160');

        $resolutionPreset = $this->getResolutionPreset($maxWidth, $maxHeight);

        $parameters = [
            'trigger_size_gb' => $this->config->getAppValue('downtranscoder', 'trigger_size_gb', '10'),
            'video_codec' => $this->config->getAppValue('downtranscoder', 'video_codec', 'H265'),
            'video_crf' => $this->config->getAppValue('downtranscoder', 'video_crf', '23'),
            'max_video_resolution' => $resolutionPreset,
            'max_ffmpeg_threads' => $this->config->getAppValue('downtranscoder', 'max_ffmpeg_threads', '0'),
            'image_quality' => $this->config->getAppValue('downtranscoder', 'image_quality', '85'),
            'max_image_width' => $this->config->getAppValue('downtranscoder', 'max_image_width', '1920'),
            'max_image_height' => $this->config->getAppValue('downtranscoder', 'max_image_height', '1080'),
            'auto_delete_originals' => $this->config->getAppValue('downtranscoder', 'auto_delete_originals', 'false'),
            'concurrent_limit' => $this->config->getAppValue('downtranscoder', 'concurrent_limit', '1'),
            'enable_schedule' => $this->config->getAppValue('downtranscoder', 'enable_schedule', 'false'),
            'schedule_start' => $this->config->getAppValue('downtranscoder', 'schedule_start', '02:00'),
        ];

        return new TemplateResponse('downtranscoder', 'settings/admin', $parameters);
    }

    private function getResolutionPreset(string $width, string $height): string {
        $w = (int) $width;
        $h = (int) $height;

        if ($w === 0 || $h === 0) {
            return 'unlimited';
        } elseif ($w === 7680 && $h === 4320) {
            return '8k';
        } elseif ($w === 3840 && $h === 2160) {
            return '4k';
        } elseif ($w === 2560 && $h === 1440) {
            return '1440p';
        } elseif ($w === 1920 && $h === 1080) {
            return '1080p';
        } elseif ($w === 1280 && $h === 720) {
            return '720p';
        } elseif ($w === 854 && $h === 480) {
            return '480p';
        }

        return '4k'; // default
    }

    public function getSection(): string {
        return 'downtranscoder';
    }

    public function getPriority(): int {
        return 50;
    }
}
