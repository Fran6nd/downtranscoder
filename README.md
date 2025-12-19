# DownTranscoder for Nextcloud

**DownTranscoder** is a Nextcloud app designed to help you manage large media files by automatically identifying and transcoding them to smaller sizes.

## Features

* Set a **trigger size** (e.g., 10 GB) to identify large videos or images
* Scan your Nextcloud files and list all media exceeding the trigger size
* Queue selected media for transcoding
* Background job support for automatic transcoding
* Review transcoded files before deleting originals
* Optional automatic deletion with warnings
* RESTful API for external integrations

## Supported Media

* Video files (MP4, MKV, AVI, MOV, etc.)
* Image files (JPG, PNG, etc.)

## Requirements

* Nextcloud 28 or later
* PHP 8.1 or later
* FFmpeg installed on the server

## Installation

### From App Store (when published)

1. Log in to your Nextcloud as admin
2. Go to **Apps** → **Multimedia**
3. Find **DownTranscoder** and click **Download and enable**

### Manual Installation

1. Clone or download this repository
2. Place the `downtranscoder` folder in your Nextcloud `apps/` directory
3. Run `cd apps/downtranscoder && composer install` (if you have composer)
4. Enable the app in Nextcloud: **Apps** → **Multimedia** → **DownTranscoder** → **Enable**

## Configuration

1. Go to **Settings** → **Administration** → **DownTranscoder**
2. Configure your settings:
   - **Trigger Size**: Files larger than this will be identified for transcoding
   - **Video Codec**: Choose H.264, H.265, VP9, or AV1
   - **Video CRF**: Quality setting (18-28 recommended, lower = better quality)
   - **Image Quality**: JPEG quality (1-100, higher = better quality)
   - **Max Image Dimensions**: Optional resize limits
   - **Auto-Delete Originals**: ⚠️ Use with extreme caution

## Usage

### Via Web Interface

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

The app registers a background job that runs periodically to process the transcode queue. You can configure Nextcloud's cron settings to control how often background jobs run.

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
