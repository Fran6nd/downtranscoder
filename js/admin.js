(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const saveButton = document.getElementById('save-settings');
        const resetButton = document.getElementById('reset-database');

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
                    admin_only: document.getElementById('admin-only').checked ? 'true' : 'false',
                    trigger_size_gb: document.getElementById('trigger-size-gb').value,
                    video_codec: document.getElementById('video-codec').value,
                    video_crf: document.getElementById('video-crf').value,
                    max_video_width: resolution.width,
                    max_video_height: resolution.height,
                    max_ffmpeg_threads: document.getElementById('max-ffmpeg-threads').value,
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

        // Reset database
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                if (!confirm(t('downtranscoder', 'Are you sure you want to reset the database? This will clear all media items. This action cannot be undone!'))) {
                    return;
                }

                resetButton.disabled = true;
                resetButton.textContent = t('downtranscoder', 'Resetting...');

                const url = OC.generateUrl('/apps/downtranscoder/api/v1/reset-database');
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'requesttoken': OC.requestToken
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        OC.Notification.showTemporary(data.message || t('downtranscoder', 'Database reset successfully'));
                    } else {
                        OC.Notification.showTemporary(t('downtranscoder', 'Error: ') + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Reset error:', error);
                    OC.Notification.showTemporary(t('downtranscoder', 'Failed to reset database'));
                })
                .finally(() => {
                    resetButton.disabled = false;
                    resetButton.textContent = t('downtranscoder', '🗑️ Reset Database');
                });
            });
        }
    });
})();
