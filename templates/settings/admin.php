<?php
script('downtranscoder', 'admin');
style('downtranscoder', 'admin');
?>

<div id="downtranscoder-admin" class="section">
    <h2><?php p($l->t('DownTranscoder Settings')); ?></h2>

    <div class="downtranscoder-settings">
        <h3><?php p($l->t('General Settings')); ?></h3>

        <p>
            <label for="trigger-size-gb"><?php p($l->t('Trigger Size (GB)')); ?></label>
            <input type="number" id="trigger-size-gb" name="trigger_size_gb"
                   value="<?php p($_['trigger_size_gb']); ?>" min="1" />
            <em><?php p($l->t('Files larger than this will be identified for transcoding')); ?></em>
        </p>

        <h3><?php p($l->t('Video Settings')); ?></h3>

        <p>
            <label for="video-codec"><?php p($l->t('Video Codec')); ?></label>
            <select id="video-codec" name="video_codec">
                <option value="H264" <?php if ($_['video_codec'] === 'H264') p('selected'); ?>>
                    <?php p($l->t('H.264 (Widely Compatible)')); ?>
                </option>
                <option value="H265" <?php if ($_['video_codec'] === 'H265') p('selected'); ?>>
                    <?php p($l->t('H.265/HEVC (Better Compression)')); ?>
                </option>
                <option value="VP9" <?php if ($_['video_codec'] === 'VP9') p('selected'); ?>>
                    <?php p($l->t('VP9 (Open Source)')); ?>
                </option>
                <option value="AV1" <?php if ($_['video_codec'] === 'AV1') p('selected'); ?>>
                    <?php p($l->t('AV1 (Best Compression, Slower)')); ?>
                </option>
            </select>
        </p>

        <p>
            <label for="video-crf"><?php p($l->t('Video CRF (Quality)')); ?></label>
            <input type="number" id="video-crf" name="video_crf"
                   value="<?php p($_['video_crf']); ?>" min="0" max="51" />
            <em><?php p($l->t('Lower = better quality, larger file (Recommended: 18-28)')); ?></em>
        </p>

        <p>
            <label for="max-video-resolution"><?php p($l->t('Max Video Resolution')); ?></label>
            <select id="max-video-resolution" name="max_video_resolution">
                <option value="unlimited" <?php if ($_['max_video_resolution'] === 'unlimited') p('selected'); ?>>
                    <?php p($l->t('Unlimited (No Downscaling)')); ?>
                </option>
                <option value="8k" <?php if ($_['max_video_resolution'] === '8k') p('selected'); ?>>
                    <?php p($l->t('8K (7680x4320)')); ?>
                </option>
                <option value="4k" <?php if ($_['max_video_resolution'] === '4k') p('selected'); ?>>
                    <?php p($l->t('4K (3840x2160) - Default')); ?>
                </option>
                <option value="1440p" <?php if ($_['max_video_resolution'] === '1440p') p('selected'); ?>>
                    <?php p($l->t('1440p (2560x1440)')); ?>
                </option>
                <option value="1080p" <?php if ($_['max_video_resolution'] === '1080p') p('selected'); ?>>
                    <?php p($l->t('1080p (1920x1080)')); ?>
                </option>
                <option value="720p" <?php if ($_['max_video_resolution'] === '720p') p('selected'); ?>>
                    <?php p($l->t('720p (1280x720)')); ?>
                </option>
                <option value="480p" <?php if ($_['max_video_resolution'] === '480p') p('selected'); ?>>
                    <?php p($l->t('480p (854x480)')); ?>
                </option>
            </select>
            <em><?php p($l->t('Videos above this resolution will be downscaled. Lower resolution videos will NOT be upscaled.')); ?></em>
        </p>

        <p>
            <label for="max-ffmpeg-threads"><?php p($l->t('Max FFmpeg Threads')); ?></label>
            <input type="number" id="max-ffmpeg-threads" name="max_ffmpeg_threads"
                   value="<?php p($_['max_ffmpeg_threads']); ?>" min="0" />
            <em><?php p($l->t('Maximum CPU threads for FFmpeg transcoding. 0 = auto (use all available threads)')); ?></em>
        </p>

        <h3><?php p($l->t('Image Settings')); ?></h3>

        <p>
            <label for="image-quality"><?php p($l->t('Image Quality')); ?></label>
            <input type="number" id="image-quality" name="image_quality"
                   value="<?php p($_['image_quality']); ?>" min="1" max="100" />
            <em><?php p($l->t('Higher = better quality, larger file (1-100)')); ?></em>
        </p>

        <p>
            <label for="max-image-width"><?php p($l->t('Max Image Width (pixels)')); ?></label>
            <input type="number" id="max-image-width" name="max_image_width"
                   value="<?php p($_['max_image_width']); ?>" min="0" />
            <em><?php p($l->t('0 = no limit')); ?></em>
        </p>

        <p>
            <label for="max-image-height"><?php p($l->t('Max Image Height (pixels)')); ?></label>
            <input type="number" id="max-image-height" name="max_image_height"
                   value="<?php p($_['max_image_height']); ?>" min="0" />
            <em><?php p($l->t('0 = no limit')); ?></em>
        </p>

        <h3><?php p($l->t('Transcoding Queue Settings')); ?></h3>

        <p>
            <label for="concurrent-limit"><?php p($l->t('Concurrent Transcoding Limit')); ?></label>
            <input type="number" id="concurrent-limit" name="concurrent_limit"
                   value="<?php p($_['concurrent_limit']); ?>" min="1" max="10" />
            <em><?php p($l->t('Maximum number of files to transcode simultaneously in scheduled tasks (Default: 1)')); ?></em>
        </p>

        <h3><?php p($l->t('Schedule Settings')); ?></h3>

        <p>
            <input type="checkbox" id="enable-schedule" name="enable_schedule"
                   class="checkbox" <?php if ($_['enable_schedule'] === 'true') p('checked'); ?> />
            <label for="enable-schedule">
                <?php p($l->t('Enable Scheduled Transcoding')); ?>
            </label>
            <br>
            <em><?php p($l->t('When enabled, automatic transcoding will only run at the specified time')); ?></em>
        </p>

        <p>
            <label for="schedule-start"><?php p($l->t('Scheduled Time')); ?></label>
            <input type="time" id="schedule-start" name="schedule_start"
                   value="<?php p($_['schedule_start']); ?>" />
            <em><?php p($l->t('Time to start transcoding (processes concurrent limit items then stops until next day)')); ?></em>
        </p>

        <h3><?php p($l->t('Danger Zone')); ?></h3>

        <p>
            <input type="checkbox" id="auto-delete-originals" name="auto_delete_originals"
                   class="checkbox" <?php if ($_['auto_delete_originals'] === 'true') p('checked'); ?> />
            <label for="auto-delete-originals" style="color: #f00;">
                <?php p($l->t('⚠️ Auto-Delete Originals')); ?>
            </label>
            <br>
            <em style="color: #f00;">
                <?php p($l->t('WARNING: This will permanently delete original files after successful transcoding!')); ?>
            </em>
        </p>

        <p style="margin-top: 20px;">
            <button id="reset-database" class="button" style="background-color: #d9534f; color: white;">
                <?php p($l->t('🗑️ Reset Database')); ?>
            </button>
            <br>
            <em style="color: #f00;">
                <?php p($l->t('WARNING: This will clear all media items from the database. Use this if you have database migration issues or want to start fresh.')); ?>
            </em>
        </p>

        <button id="save-settings" class="button primary"><?php p($l->t('Save Settings')); ?></button>

        <div style="margin-top: 40px;">
            <h3><?php p($l->t('Manage Media')); ?></h3>
            <p><?php p($l->t('Use the main DownTranscoder page to scan for media, manage the transcoding workflow, and view progress.')); ?></p>
            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('downtranscoder.page.index')); ?>" class="button">
                <?php p($l->t('Go to DownTranscoder')); ?>
            </a>
        </div>
    </div>
</div>
