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
        $parameters = [
            'trigger_size_gb' => $this->config->getAppValue('downtranscoder', 'trigger_size_gb', '10'),
            'video_codec' => $this->config->getAppValue('downtranscoder', 'video_codec', 'H265'),
            'video_crf' => $this->config->getAppValue('downtranscoder', 'video_crf', '23'),
            'image_quality' => $this->config->getAppValue('downtranscoder', 'image_quality', '85'),
            'max_image_width' => $this->config->getAppValue('downtranscoder', 'max_image_width', '1920'),
            'max_image_height' => $this->config->getAppValue('downtranscoder', 'max_image_height', '1080'),
            'auto_delete_originals' => $this->config->getAppValue('downtranscoder', 'auto_delete_originals', 'false'),
        ];

        return new TemplateResponse('downtranscoder', 'settings/admin', $parameters);
    }

    public function getSection(): string {
        return 'downtranscoder';
    }

    public function getPriority(): int {
        return 50;
    }
}
