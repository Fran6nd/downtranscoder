# DownTranscoder for Nextcloud

# WORK IN PROGRESS

**DownTranscoder** is a Nextcloud app designed to help you manage large media files by automatically identifying and transcoding them to smaller sizes.

## Features

* **Kanban Board UI** - Visual drag-and-drop interface for managing media workflow
* Set a **trigger size** (e.g., 10 GB) to identify large videos or images
* Scan your Nextcloud files and list all media exceeding the trigger size
* **4-Column Workflow**:
  - Media Found - Scanned files
  - To Transcode - Queued for processing
  - Transcoded - Waiting for original deletion
  - Discard - Files to ignore
* Drag and drop files between columns with persistent state
* Background job support for automatic transcoding
* Review transcoded files before deleting originals
* Optional automatic deletion with warnings
* RESTful API for external integrations
* Responsive mobile-friendly design

## Supported Media

* Video files (MP4, MKV, AVI, MOV, etc.)
* Image files (JPG, PNG, etc.)

## Requirements

* Nextcloud 25 or later
* PHP 8.1 or later
* FFmpeg installed on the server (for transcoding)

**No Node.js or build tools required!**

## Installation

### From App Store (when published)

1. Log in to your Nextcloud as admin
2. Go to **Apps** → **Multimedia**
3. Find **DownTranscoder** and click **Download and enable**

### Manual Installation

1. Clone or download this repository
2. Place the `downtranscoder` folder in your Nextcloud `apps/` directory
3. (Optional) Run `cd apps/downtranscoder && composer install` if you have composer
4. Enable the app in Nextcloud: **Apps** → **Multimedia** → **DownTranscoder** → **Enable**

**No Node.js, npm, or build step required!** The app uses vanilla JavaScript and Nextcloud's built-in libraries.

### Updating the App

To update to the latest version:

```bash
cd apps/downtranscoder
git pull origin main
# Optional: if you use composer
composer install
# Re-enable the app to run any new migrations
php occ app:disable downtranscoder
php occ app:enable downtranscoder
```

That's it! No build step needed.

## Configuration

1. Go to **Settings** → **Administration** → **DownTranscoder**
2. Configure your settings:
   - **Trigger Size**: Files larger than this will be identified for transcoding (e.g., 10 GB)
   - **Video Codec**: Choose H.265 (HEVC) for best compression
   - **Video CRF**: 23-28 recommended (higher = smaller file, lower quality)
   - **Image Quality**: JPEG quality (1-100, higher = better quality)
   - **Max Image Dimensions**: Optional resize limits
   - **Concurrent Transcoding Limit**: Maximum number of files to process per scheduled run (1-10, default: 1)
   - **Enable Scheduled Transcoding**: When enabled, automatic transcoding only runs at the specified time
   - **Scheduled Time**: Daily time to start transcoding (e.g., 02:00)
   - **Auto-Delete Originals**: ⚠️ Use with extreme caution

### Recommended FFmpeg Settings for Size Reduction

To reliably get files below your trigger size while preserving quality, subtitles, and audio tracks:

```bash
# For H.265 (HEVC) - Best compression
ffmpeg -i input.mkv -c:v libx265 -crf 26 -preset medium \
  -c:a copy -c:s copy -map 0 \
  "input.transcoded.mkv"

# For H.264 - Better compatibility
ffmpeg -i input.mkv -c:v libx264 -crf 23 -preset medium \
  -c:a copy -c:s copy -map 0 \
  "input.transcoded.mkv"
```

**Key options:**
- `-c:v libx265` - Use H.265 codec (50% smaller than H.264)
- `-crf 26` - Constant Rate Factor (23-28 for good quality/size balance)
- `-preset medium` - Encoding speed (slower = better compression)
- `-c:a copy` - Copy all audio tracks without re-encoding
- `-c:s copy` - Copy all subtitle tracks
- `-map 0` - Include all streams (video, audio, subtitles)
- `"input.transcoded.mkv"` - Standard naming: original name + `.transcoded` + extension

**CRF Guide:**
- CRF 18-23: High quality, larger files
- CRF 23-26: Good balance (recommended)
- CRF 26-28: Smaller files, acceptable quality
- CRF 28+: Very small, noticeable quality loss

The app automatically uses these settings and naming conventions.

## Usage

### Manual Transcoding Workflow

Use the Kanban Board interface for manual, on-demand transcoding:

1. Click **DownTranscoder** in the Nextcloud navigation menu
2. Click **"Scan Media"** to find large files
   - **Scanning runs in the background** - you can continue using the app while scanning
   - The button shows "Scanning..." with a loading spinner during the scan
   - You'll get a notification when the scan completes with the number of files found
   - Scan status is checked every 5 seconds to update the UI automatically
3. **Drag and drop** files between columns:
   - Move files to **"To Transcode"** to queue them
   - Click **"Start Transcoding"** to begin processing **one file at a time**
   - Files automatically move to **"Transcoded"** when complete
   - Delete originals by clicking the delete button
   - Move unwanted files to **"Discard"**
4. All changes are automatically saved and persist across sessions

**Manual transcoding** processes **one file at a time** and can be triggered anytime by clicking the "Start Transcoding" button.

### Scheduled Automatic Transcoding Workflow

Configure automatic transcoding to run at specific times:

1. Go to **Settings** → **Administration** → **DownTranscoder**
2. Enable **"Enable Scheduled Transcoding"** checkbox
3. Set **"Scheduled Time"** (e.g., 02:00 for 2 AM)
4. Set **"Concurrent Transcoding Limit"** (e.g., 3 to process 3 files per run)
5. Save settings

**How it works:**
- Background job runs every hour but only transcodes at the scheduled time
- At the scheduled time (e.g., 2 AM), it processes N files from the "To Transcode" queue (where N = concurrent limit)
- Processes the files, then stops until the next day at the same time
- Runs once per day at the scheduled time

**Example:**
- Scheduled Time: `02:00`
- Concurrent Limit: `3`
- Result: Every day at 2 AM, 3 files are transcoded, then the system waits until 2 AM the next day

See [USAGE.md](USAGE.md) for detailed usage instructions and troubleshooting.

### Via Admin Settings (Legacy)

1. Go to **Settings** → **Administration** → **DownTranscoder**
2. Click **Scan for Large Files**
3. Review the list of large files
4. Select files you want to transcode
5. Click **Add Selected to Queue**
6. Files will be transcoded by the background job

### Via occ Command

```bash
# Scan for large files
php occ downtranscoder:scan

# Start transcoding queued files
php occ downtranscoder:transcode
```

### Via API

```bash
# Scan for large files
curl -X GET https://your-nextcloud.com/apps/downtranscoder/api/v1/scan

# Get transcode queue
curl -X GET https://your-nextcloud.com/apps/downtranscoder/api/v1/queue

# Add file to queue
curl -X POST https://your-nextcloud.com/apps/downtranscoder/api/v1/queue/FILE_ID

# Start transcoding
curl -X POST https://your-nextcloud.com/apps/downtranscoder/api/v1/transcode/start

# Get status
curl -X GET https://your-nextcloud.com/apps/downtranscoder/api/v1/transcode/status
```

## Background Jobs

The app registers a background job that runs **every hour** to check for transcoding work.

### Scheduling Behavior

**When scheduling is DISABLED (default):**
- Background job processes queued items every hour
- Processes up to N items per run (where N = concurrent limit, default: 1)

**When scheduling is ENABLED:**
- Background job runs every hour but only transcodes at the scheduled time
- At the scheduled time, processes N items from the queue (where N = concurrent limit)
- Runs once per day, then waits until the next scheduled time
- Uses a tracker to prevent duplicate runs within the same hour

You can configure Nextcloud's cron settings to control how often background jobs run.

## ⚠️ Warnings

* **Automatic deletion is risky** — always double-check before enabling it
* Make sure you have sufficient **disk space and backups** before running large batch operations
* Transcoding is CPU-intensive and may affect server performance
* Large files may take hours to transcode

## Development

### Project Structure

```
downtranscoder/
├── appinfo/
│   ├── info.xml          # App metadata
│   └── routes.php        # API routes
├── lib/
│   ├── AppInfo/
│   │   └── Application.php
│   ├── Controller/
│   │   └── ApiController.php
│   ├── Service/
│   │   ├── MediaScannerService.php      ✅
│   │   ├── TranscodingQueueService.php  ✅
│   │   └── TranscodingService.php       ✅
│   ├── Command/
│   │   ├── ScanCommand.php              ✅
│   │   └── TranscodeCommand.php         ✅
│   ├── BackgroundJob/
│   │   └── TranscodeJob.php             ✅
│   └── Settings/
│       ├── Admin.php                    ✅
│       └── AdminSection.php             ✅
├── templates/
│   └── settings/
│       └── admin.php                    ✅
├── js/
│   └── admin.js                         ✅
├── css/
│   └── admin.css                        ✅
├── composer.json
├── README.md
└── INSTALL.md
```

### Implementation Status

✅ **Complete** - All core features implemented:

- ✅ Media scanner service (scans Nextcloud files for large media)
- ✅ Transcoding queue management
- ✅ FFmpeg transcoding service (videos and images)
- ✅ occ CLI commands (`scan`, `transcode`)
- ✅ Background job for automatic transcoding
- ✅ REST API endpoints
- ✅ Admin settings page with scan & review UI
- ✅ Web interface for queue management

### Ready to Deploy

The app is fully functional and ready for installation on Nextcloud!

## License

AGPL-3.0-or-later

## Contributing

Contributions are welcome! Please submit issues and pull requests on GitHub.
