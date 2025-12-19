# DownTranscoder - Usage Guide

## 🎯 Quick Start

### Installation (No Build Required!)

```bash
# Clone the app
cd /path/to/nextcloud/apps
git clone https://github.com/Fran6nd/downtranscoder.git

# Enable it
php occ app:enable downtranscoder
```

**That's it!** No Node.js, npm, webpack, or any build tools needed.

### Updating

```bash
cd /path/to/nextcloud/apps/downtranscoder
git pull origin main

# Re-enable to run migrations
php occ app:disable downtranscoder
php occ app:enable downtranscoder
```

---

## 📱 Using the Kanban Board

### Accessing the App

Click **DownTranscoder** in your Nextcloud navigation menu (left sidebar).

### The 4 Columns

```
┌─────────────┬──────────────┬────────────────────┬───────────┐
│ Media Found │ To Transcode │     Transcoded     │  Discard  │
└─────────────┴──────────────┴────────────────────┴───────────┘
```

1. **Media Found** - Files discovered by scanning
2. **To Transcode** - Files queued for transcoding
3. **Transcoded** - Files successfully transcoded (ready to delete originals)
4. **Discard** - Files you want to ignore

### Basic Workflow

1. **Scan for media**
   - Click "Scan Media" button (top right)
   - Large files appear in "Media Found" column

2. **Queue files for transcoding**
   - Drag files from "Media Found" to "To Transcode"
   - Or drag directly to "Discard" to ignore them

3. **Start transcoding**
   - Click "Start Transcoding" button
   - Files automatically move to "Transcoded" when done

4. **Delete originals**
   - Review transcoded files
   - Click delete button (🗑️) to remove the original
   - Keeps only the smaller transcoded version

### Drag and Drop

- **Click and hold** any media item
- **Drag** to another column
- **Release** to drop
- Changes are **automatically saved** to database

---

## ⚙️ Configuration

### Admin Settings

Go to **Settings → Administration → DownTranscoder**:

- **Trigger Size** - Minimum file size to scan for (e.g., 10 GB)
- **Video Codec** - H.264, H.265, VP9, or AV1
- **Video CRF** - Quality (18-28, lower = better quality)
- **Image Quality** - JPEG quality (1-100)
- **Auto-Delete** - ⚠️ Dangerous! Use with caution

### Scan Paths

Configure specific folders to scan (optional):
- Leave empty to scan all user files
- Specify paths like: `username/files/Movies`

---

## 🔧 Technical Details

### How It Works

1. **Scanning**: PHP scans Nextcloud files using IRootFolder API
2. **State Tracking**: Items saved to `downtranscoder_media` database table
3. **UI Updates**: Vanilla JavaScript polls API every 5 seconds
4. **Transcoding**: Background jobs process queue using FFmpeg

### API Endpoints

```bash
GET  /apps/downtranscoder/api/v1/media           # Get all items
PUT  /apps/downtranscoder/api/v1/media/{id}/state  # Update state
POST /apps/downtranscoder/api/v1/transcode/start   # Start transcoding
GET  /apps/downtranscoder/api/v1/transcode/status  # Get status
```

### Database Schema

```sql
Table: downtranscoder_media
- id (primary key)
- file_id (Nextcloud file ID)
- user_id
- name, path, size
- state ('found', 'queued', 'transcoded', 'discarded')
- created_at, updated_at
```

### Technologies Used

- **Backend**: PHP 8.1+, Nextcloud OCP framework
- **Frontend**: Vanilla JavaScript (NO external dependencies)
- **Database**: Nextcloud's database (MySQL/PostgreSQL/SQLite)
- **Transcoding**: FFmpeg
- **UI Library**: Nextcloud's built-in `OC` object

**No Node.js, Vue, React, or webpack!** Pure vanilla JS using only Nextcloud's built-in APIs.

---

## 🐛 Troubleshooting

### App doesn't appear in navigation
```bash
php occ app:list | grep downtranscoder  # Check if enabled
php occ app:enable downtranscoder       # Enable it
```

### JavaScript errors in console
- Hard refresh browser (Ctrl+Shift+R)
- Check that `js/downtranscoder-main.js` exists
- Verify Nextcloud is loading correctly

### Scan finds no files
- Check trigger size in admin settings (might be too high)
- Verify you have media files larger than the threshold
- Check logs: `tail -f /path/to/nextcloud/data/nextcloud.log`

### Database errors
```bash
# Run migrations manually
php occ migrations:execute downtranscoder latest

# Check table exists
php occ db:query "SELECT COUNT(*) FROM oc_downtranscoder_media"
```

### Drag and drop not working
- Use a modern browser (Chrome, Firefox, Safari, Edge)
- Check JavaScript console for errors
- Ensure you're not in mobile/touch mode

---

## 📚 Additional Info

### Files Structure

```
downtranscoder/
├── js/
│   └── downtranscoder-main.js     # Vanilla JS kanban board
├── css/
│   └── main.css                    # Responsive styles
├── lib/
│   ├── Controller/
│   │   ├── PageController.php      # Main page
│   │   └── ApiController.php       # API endpoints
│   ├── Db/
│   │   ├── MediaItem.php           # Entity
│   │   └── MediaItemMapper.php     # Database queries
│   ├── Service/
│   │   ├── MediaScannerService.php
│   │   ├── MediaStateService.php
│   │   └── TranscodingService.php
│   └── Migration/
│       └── Version1000Date20241219000000.php
└── templates/
    └── main.php                    # Page template
```

### Command Line Usage

```bash
# Scan for large files
php occ downtranscoder:scan

# Start transcoding
php occ downtranscoder:transcode
```

---

## 📝 License

AGPL-3.0-or-later

## 🆘 Support

- GitHub Issues: https://github.com/Fran6nd/downtranscoder/issues
- Nextcloud Community: https://help.nextcloud.com
