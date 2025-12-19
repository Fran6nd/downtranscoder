# DownTranscoder

**DownTranscoder** is a nextcloud plugin designed to help you manage large media files by automatically identifying and transcoding them to smaller sizes.

---

## Features

* Set a **trigger size** (e.g., 10 GB) to identify large videos or images.
* The plugin will **list all media exceeding this size** and ask if you want to transcode them.
* Queue selected media for **automatic transcoding** at a scheduled time (e.g., 3 AM daily).
* After transcoding, you can **review the new files** before deleting the originals.
* Optional **automatic deletion** is available — use with caution, as this may permanently remove original media.

---

## Supported Media

* Video files
* Images

---

## Usage

1. Install the plugin in nextcloud.
2. Set your desired **trigger size**.
3. Review the large media list and select which files to transcode.
4. Schedule or run the transcoding manually.
5. Review transcoded files and delete originals if desired.

---

## ⚠️ Warning

* **Automatic deletion is risky** — always double-check before enabling it.
* Make sure you have sufficient **disk space and backups** before running large batch operations.

---

## Planned Features

* Improved scheduling options
* Detailed transcoding logs
* Enhanced image compression controls
