(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const saveButton = document.getElementById('save-settings');

        // Save settings
        if (saveButton) {
            saveButton.addEventListener('click', function() {
                // Convert resolution preset to width/height
                const resolutionPreset = document.getElementById('max-video-resolution').value;
                const resolutionMap = {
                    'unlimited': { width: '0', height: '0' },
                    '8k': { width: '7680', height: '4320' },
                    '4k': { width: '3840', height: '2160' },
                    '1440p': { width: '2560', height: '1440' },
                    '1080p': { width: '1920', height: '1080' },
                    '720p': { width: '1280', height: '720' },
                    '480p': { width: '854', height: '480' }
                };

                const resolution = resolutionMap[resolutionPreset] || resolutionMap['4k'];

                const settings = {
                    trigger_size_gb: document.getElementById('trigger-size-gb').value,
                    video_codec: document.getElementById('video-codec').value,
                    video_crf: document.getElementById('video-crf').value,
                    max_video_width: resolution.width,
                    max_video_height: resolution.height,
                    image_quality: document.getElementById('image-quality').value,
                    max_image_width: document.getElementById('max-image-width').value,
                    max_image_height: document.getElementById('max-image-height').value,
                    auto_delete_originals: document.getElementById('auto-delete-originals').checked ? 'true' : 'false',
                    concurrent_limit: document.getElementById('concurrent-limit').value,
                    enable_schedule: document.getElementById('enable-schedule').checked ? 'true' : 'false',
                    schedule_start: document.getElementById('schedule-start').value
                };

                Object.keys(settings).forEach(function(key) {
                    OCP.AppConfig.setValue('downtranscoder', key, settings[key]);
                });

                OC.Notification.showTemporary(t('downtranscoder', 'Settings saved'));
            });
        }
    });
})();
