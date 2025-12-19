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

        <button id="save-settings" class="button primary"><?php p($l->t('Save Settings')); ?></button>

        <div id="downtranscoder-scan-section" style="margin-top: 40px;">
            <h3><?php p($l->t('Scan & Review')); ?></h3>
            <button id="scan-files" class="button"><?php p($l->t('Scan for Large Files')); ?></button>
            <div id="scan-results" style="margin-top: 20px;"></div>
        </div>
    </div>
</div>
