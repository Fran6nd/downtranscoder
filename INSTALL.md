# Installation Guide for DownTranscoder

This guide will help you install and configure the DownTranscoder app for Nextcloud.

## Prerequisites

Before installing, ensure you have:

1. **Nextcloud 28 or later** installed and running
2. **PHP 8.1 or later**
3. **FFmpeg** installed on your server
4. **Sufficient disk space** for transcoded files

### Installing FFmpeg

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install ffmpeg
```

#### CentOS/RHEL
```bash
sudo yum install epel-release
sudo yum install ffmpeg
```

#### macOS
```bash
brew install ffmpeg
```

Verify installation:
```bash
ffmpeg -version
```

## Installation Methods

### Method 1: Manual Installation (Recommended for Development)

1. **Clone or download** the repository
   ```bash
   cd /path/to/nextcloud/apps
   git clone https://github.com/yourusername/nextcloud-downtranscoder downtranscoder
   ```

2. **Install dependencies** (if you have Composer)
   ```bash
   cd downtranscoder
   composer install
   ```

3. **Set permissions**
   ```bash
   sudo chown -R www-data:www-data /path/to/nextcloud/apps/downtranscoder
   ```

4. **Enable the app**
   ```bash
   cd /path/to/nextcloud
   sudo -u www-data php occ app:enable downtranscoder
   ```

### Method 2: From App Store (When Published)

1. Log in to Nextcloud as administrator
2. Navigate to **Apps** → **Multimedia**
3. Find **DownTranscoder**
4. Click **Download and enable**

## Configuration

### 1. Access Settings

Navigate to **Settings** → **Administration** → **DownTranscoder**

### 2. Configure Basic Settings

- **Trigger Size (GB)**: Set the minimum file size (default: 10 GB)
  - Files larger than this will be identified for transcoding

### 3. Video Settings

- **Video Codec**: Choose encoding codec
  - H.264: Widely compatible, good compression
  - H.265/HEVC: Better compression than H.264
  - VP9: Open source alternative
  - AV1: Best compression, but slower encoding

- **Video CRF**: Quality setting (default: 23)
  - Range: 0-51
  - Lower = better quality, larger file size
  - Recommended: 18-28
  - 18: Visually lossless
  - 23: Default (good balance)
  - 28: Acceptable quality for most content

### 4. Image Settings

- **Image Quality**: JPEG quality (default: 85)
  - Range: 1-100
  - Higher = better quality, larger file

- **Max Image Dimensions**: Optional resize limits
  - Set to 0 for no limit
  - Example: 1920×1080 for Full HD

### 5. Danger Zone

⚠️ **Auto-Delete Originals**: Use with extreme caution!
- When enabled, original files are automatically deleted after successful transcoding
- **Recommendation**: Keep disabled until you've tested transcoding
- **Best Practice**: Manually review transcoded files first

### 6. Save Settings

Click **Save Settings** to apply your configuration.

## First Scan

### Via Web Interface

1. In the settings page, click **Scan for Large Files**
2. Review the list of files found
3. Select files you want to transcode
4. Click **Add Selected to Queue**

### Via Command Line

```bash
# Scan for large files
sudo -u www-data php occ downtranscoder:scan

# View the list and note file IDs you want to transcode
# Add files to queue
sudo -u www-data php occ downtranscoder:transcode --add FILE_ID

# View queue
sudo -u www-data php occ downtranscoder:transcode --list

# Start transcoding
sudo -u www-data php occ downtranscoder:transcode --start
```

## Background Jobs

The app includes a background job that runs every hour to process the transcode queue automatically.

### Ensure Cron is Configured

For best performance, configure Nextcloud to use system cron:

1. **Edit crontab**
   ```bash
   sudo crontab -u www-data -e
   ```

2. **Add cron job**
   ```
   */5 * * * * php -f /path/to/nextcloud/cron.php
   ```

3. **Configure Nextcloud**
   - Go to **Settings** → **Administration** → **Basic settings**
   - Under **Background jobs**, select **Cron**

### Manual Background Job Execution

To run background jobs manually:
```bash
sudo -u www-data php /path/to/nextcloud/cron.php
```

## Testing

### Test Video Transcoding

1. Upload a test video file larger than your trigger size
2. Run scan to detect it
3. Add to queue and start transcoding
4. Monitor logs: `tail -f /path/to/nextcloud/data/nextcloud.log`
5. Check the output file quality before enabling auto-delete

### Test Image Compression

1. Upload a test image file larger than your trigger size
2. Follow the same process as video testing
3. Compare original vs compressed quality

## Troubleshooting

### FFmpeg Not Found

**Error**: "FFmpeg not found on system"

**Solution**: Ensure FFmpeg is installed and in PATH
```bash
which ffmpeg
# Should output: /usr/bin/ffmpeg or similar
```

### Permission Errors

**Error**: Permission denied errors in logs

**Solution**: Fix file permissions
```bash
sudo chown -R www-data:www-data /path/to/nextcloud/apps/downtranscoder
sudo chown -R www-data:www-data /path/to/nextcloud/data
```

### Transcoding Fails

**Check logs**:
```bash
tail -f /path/to/nextcloud/data/nextcloud.log | grep -i downtranscoder
```

**Common issues**:
- Insufficient disk space
- Corrupt input files
- Unsupported codecs
- FFmpeg missing required libraries

### High CPU Usage

Transcoding is CPU-intensive. To limit impact:

1. Run transcoding during off-peak hours
2. Use the background job system
3. Consider limiting concurrent transcodes (modify code if needed)
4. Use hardware acceleration if available (requires FFmpeg configuration)

## Performance Tips

1. **Start with high CRF values** (28-30) for testing
   - Lower CRF values take longer and produce larger files

2. **Use H.265 for best compression**
   - Takes longer than H.264 but produces smaller files

3. **Test with small files first**
   - Verify quality and settings before processing large libraries

4. **Monitor disk space**
   - Transcoding creates temporary files
   - Ensure sufficient free space

5. **Backup before using auto-delete**
   - Always have backups of original files
   - Test thoroughly before enabling auto-delete

## Uninstalling

### Via Web Interface
1. Go to **Apps** → **Your apps**
2. Find **DownTranscoder**
3. Click **Disable** then **Remove**

### Via Command Line
```bash
sudo -u www-data php occ app:disable downtranscoder
sudo -u www-data php occ app:remove downtranscoder
```

### Clean Up Data
```bash
# Remove app directory
rm -rf /path/to/nextcloud/apps/downtranscoder
```

## Support

For issues, questions, or feature requests:
- GitHub Issues: https://github.com/yourusername/nextcloud-downtranscoder/issues
- Nextcloud Community: https://help.nextcloud.com/

## License

AGPL-3.0-or-later
