(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const saveButton = document.getElementById('save-settings');
        const scanButton = document.getElementById('scan-files');
        const scanResults = document.getElementById('scan-results');

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
                    auto_delete_originals: document.getElementById('auto-delete-originals').checked ? 'true' : 'false'
                };

                Object.keys(settings).forEach(function(key) {
                    OCP.AppConfig.setValue('downtranscoder', key, settings[key]);
                });

                OC.Notification.showTemporary(t('downtranscoder', 'Settings saved'));
            });
        }

        // Scan for files
        if (scanButton) {
            scanButton.addEventListener('click', function() {
                scanButton.disabled = true;
                scanButton.textContent = t('downtranscoder', 'Scanning...');
                scanResults.innerHTML = '<p>' + t('downtranscoder', 'Scanning for large files...') + '</p>';

                fetch(OC.generateUrl('/apps/downtranscoder/api/v1/scan'))
                    .then(response => response.json())
                    .then(files => {
                        displayScanResults(files);
                        scanButton.disabled = false;
                        scanButton.textContent = t('downtranscoder', 'Scan for Large Files');
                    })
                    .catch(error => {
                        console.error('Error scanning files:', error);
                        scanResults.innerHTML = '<p style="color: red;">' +
                            t('downtranscoder', 'Error scanning files. Check logs.') + '</p>';
                        scanButton.disabled = false;
                        scanButton.textContent = t('downtranscoder', 'Scan for Large Files');
                    });
            });
        }

        function displayScanResults(files) {
            if (!files || files.length === 0) {
                scanResults.innerHTML = '<p>' + t('downtranscoder', 'No large files found.') + '</p>';
                return;
            }

            let html = '<h4>' + t('downtranscoder', 'Found {count} large files', {count: files.length}) + '</h4>';
            html += '<table><thead><tr>';
            html += '<th><input type="checkbox" id="select-all-files" /></th>';
            html += '<th>' + t('downtranscoder', 'Name') + '</th>';
            html += '<th>' + t('downtranscoder', 'Size') + '</th>';
            html += '<th>' + t('downtranscoder', 'Type') + '</th>';
            html += '<th>' + t('downtranscoder', 'Path') + '</th>';
            html += '</tr></thead><tbody>';

            files.forEach(function(file) {
                const sizeGB = (file.size / (1024 * 1024 * 1024)).toFixed(2);
                html += '<tr>';
                html += '<td><input type="checkbox" class="file-checkbox" data-file-id="' + file.id + '" /></td>';
                html += '<td>' + escapeHtml(file.name) + '</td>';
                html += '<td>' + sizeGB + ' GB</td>';
                html += '<td>' + escapeHtml(file.type || 'Unknown') + '</td>';
                html += '<td>' + escapeHtml(file.path) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            html += '<button id="add-to-queue" class="button" style="margin-top: 10px;">' +
                t('downtranscoder', 'Add Selected to Queue') + '</button>';

            scanResults.innerHTML = html;

            // Add select all functionality
            document.getElementById('select-all-files').addEventListener('change', function(e) {
                document.querySelectorAll('.file-checkbox').forEach(function(checkbox) {
                    checkbox.checked = e.target.checked;
                });
            });

            // Add to queue button
            document.getElementById('add-to-queue').addEventListener('click', function() {
                const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
                    .map(cb => cb.dataset.fileId);

                if (selectedFiles.length === 0) {
                    OC.Notification.showTemporary(t('downtranscoder', 'No files selected'));
                    return;
                }

                addFilesToQueue(selectedFiles);
            });
        }

        function addFilesToQueue(fileIds) {
            const promises = fileIds.map(fileId => {
                return fetch(OC.generateUrl('/apps/downtranscoder/api/v1/queue/' + fileId), {
                    method: 'POST',
                    headers: {
                        'requesttoken': OC.requestToken
                    }
                });
            });

            Promise.all(promises)
                .then(() => {
                    OC.Notification.showTemporary(
                        t('downtranscoder', 'Added {count} files to queue', {count: fileIds.length})
                    );
                })
                .catch(error => {
                    console.error('Error adding files to queue:', error);
                    OC.Notification.showTemporary(t('downtranscoder', 'Error adding files to queue'));
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    });
})();
