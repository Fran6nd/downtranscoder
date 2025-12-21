/**
 * DownTranscoder - Main Kanban Board Application
 *
 * Uses only Nextcloud's built-in libraries - NO external dependencies needed!
 * No build step, no npm, no webpack - just vanilla JavaScript + Nextcloud's OC object
 */

(function() {
	'use strict';

	// Wait for DOM to be ready
	document.addEventListener('DOMContentLoaded', function() {
		var app = new KanbanApp();
		app.init();
	});

	// Main Kanban Application
	function KanbanApp() {
		this.columns = {
			mediaFound: { id: 'mediaFound', title: t('downtranscoder', 'Media Found'), icon: 'icon-search', items: [], allowDrop: true },
			toTranscode: { id: 'toTranscode', title: t('downtranscoder', 'To Transcode'), icon: 'icon-play', items: [], allowDrop: true },
			transcoding: { id: 'transcoding', title: t('downtranscoder', 'Transcoding In Progress'), icon: 'icon-loading-small', items: [], allowDrop: false },
			transcoded: { id: 'transcoded', title: t('downtranscoder', 'Transcoded (Waiting for Deletion)'), icon: 'icon-checkmark', items: [], allowDrop: false },
			aborted: { id: 'aborted', title: t('downtranscoder', 'Aborted'), icon: 'icon-error', items: [], allowDrop: true },
			discard: { id: 'discard', title: t('downtranscoder', 'Discard'), icon: 'icon-delete', items: [], allowDrop: true }
		};
		this.columnOrder = ['mediaFound', 'toTranscode', 'transcoding', 'transcoded', 'aborted', 'discard'];
		this.isScanning = false;
		this.statusInterval = null;
	}

	KanbanApp.prototype.init = function() {
		this.render();
		this.loadMediaItems();
		this.checkInitialScanStatus();
		this.startStatusPolling();
	};

	KanbanApp.prototype.render = function() {
		var container = document.getElementById('downtranscoder-app');
		if (!container) return;

		var html = '<div class="kanban-board">' +
			'<div class="kanban-header">' +
				'<h2>' + t('downtranscoder', 'Media Transcoding Board') + '</h2>' +
				'<div class="kanban-actions">' +
					'<button id="btn-scan" class="button primary">' +
						'<span class="icon-search"></span> ' + t('downtranscoder', 'Scan Media') +
					'</button>' +
					'<button id="btn-transcode" class="button">' +
						'<span class="icon-play"></span> ' + t('downtranscoder', 'Start Transcoding') +
					'</button>' +
				'</div>' +
			'</div>' +
			'<div class="kanban-columns"></div>' +
		'</div>';

		container.innerHTML = html;

		// Bind events
		var self = this;
		document.getElementById('btn-scan').addEventListener('click', function() {
			self.triggerScan();
		});
		document.getElementById('btn-transcode').addEventListener('click', function() {
			self.startTranscoding();
		});

		this.renderColumns();
	};

	KanbanApp.prototype.renderColumns = function() {
		var columnsContainer = document.querySelector('.kanban-columns');
		if (!columnsContainer) return;

		columnsContainer.innerHTML = '';
		var self = this;

		console.log('DownTranscoder: Rendering columns:', this.columnOrder);
		this.columnOrder.forEach(function(columnId) {
			var column = self.columns[columnId];
			console.log('DownTranscoder: Rendering column:', columnId, column);
			var columnEl = self.createColumnElement(column);
			columnsContainer.appendChild(columnEl);
		});

		this.updateTranscodeButton();
	};

	KanbanApp.prototype.createColumnElement = function(column) {
		var div = document.createElement('div');
		div.className = 'kanban-column';
		div.dataset.columnId = column.id;

		var header = '<div class="column-header">' +
			'<span class="' + column.icon + '"></span>' +
			'<h3>' + this.escapeHtml(column.title) + '</h3>' +
			'<span class="item-count">' + column.items.length + '</span>' +
		'</div>';

		var content = '<div class="column-content">';
		if (column.items.length === 0) {
			content += '<div class="empty-column"><p>' + t('downtranscoder', 'No items') + '</p></div>';
		} else {
			column.items.forEach(function(item) {
				content += this.createMediaItemHtml(item, column.id);
			}.bind(this));
		}
		content += '</div>';

		div.innerHTML = header + content;

		// Setup drag and drop
		this.setupColumnDragDrop(div, column);

		return div;
	};

	KanbanApp.prototype.createMediaItemHtml = function(item, columnId) {
		var actionButtons = '';
		var presetDropdown = '';
		var progressBar = '';

		// Delete button for transcoded items
		if (columnId === 'transcoded') {
			actionButtons = '<div class="media-actions">' +
				'<button class="button-vue button-vue--error btn-delete" data-file-id="' + item.id + '" title="' + t('downtranscoder', 'Delete original file') + '">' +
					'<span class="icon-delete"></span>' +
				'</button>' +
			'</div>';
		}

		// Progress bar for transcoding items
		if (columnId === 'transcoding') {
			// Use real progress from database if available, otherwise estimate
			var progress = item.transcodeProgress || this.estimateTranscodeProgress(item);
			var progressColor = progress < 30 ? '#0082c9' : progress < 70 ? '#0082c9' : '#46ba61';
			progressBar = '<div class="transcode-progress-container" style="margin-top: 6px; background: #f0f0f0; border-radius: 3px; height: 18px; overflow: hidden; position: relative;">' +
				'<div class="transcode-progress-bar" style="background: ' + progressColor + '; height: 100%; width: ' + progress + '%; transition: width 0.5s ease;"></div>' +
				'<div class="transcode-progress-text" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; color: #333;">' +
					progress + '%' +
				'</div>' +
			'</div>';
		}

		// Preset dropdown for mediaFound and toTranscode items - now under filename
		if (columnId === 'mediaFound' || columnId === 'toTranscode') {
			var currentPreset = item.transcodePreset || '';
			var defaultEstimate = this.formatSize(this.estimateTranscodedSize(item.size, null));
			var h265_23_estimate = this.formatSize(this.estimateTranscodedSize(item.size, 'h265_crf23'));
			var h265_26_estimate = this.formatSize(this.estimateTranscodedSize(item.size, 'h265_crf26'));
			var h265_28_estimate = this.formatSize(this.estimateTranscodedSize(item.size, 'h265_crf28'));
			var h264_23_estimate = this.formatSize(this.estimateTranscodedSize(item.size, 'h264_crf23'));

			presetDropdown = '<select class="preset-select" data-item-id="' + item.id + '" title="' + t('downtranscoder', 'Transcode Preset') + '">' +
				'<option value="" ' + (currentPreset === '' ? 'selected' : '') + '>' + t('downtranscoder', 'Default - H.265 CRF 26 (~{size}, High Quality)', {size: defaultEstimate}) + '</option>' +
				'<option value="h265_crf23" ' + (currentPreset === 'h265_crf23' ? 'selected' : '') + '>' + t('downtranscoder', 'H.265 CRF 23 (~{size}, Highest Quality)', {size: h265_23_estimate}) + '</option>' +
				'<option value="h265_crf26" ' + (currentPreset === 'h265_crf26' ? 'selected' : '') + '>' + t('downtranscoder', 'H.265 CRF 26 (~{size}, High Quality)', {size: h265_26_estimate}) + '</option>' +
				'<option value="h265_crf28" ' + (currentPreset === 'h265_crf28' ? 'selected' : '') + '>' + t('downtranscoder', 'H.265 CRF 28 (~{size}, Good Quality, Smaller)', {size: h265_28_estimate}) + '</option>' +
				'<option value="h264_crf23" ' + (currentPreset === 'h264_crf23' ? 'selected' : '') + '>' + t('downtranscoder', 'H.264 CRF 23 (~{size}, High Quality, Compatible)', {size: h264_23_estimate}) + '</option>' +
			'</select>';
		}

		var presetAttr = item.transcodePreset ? this.escapeHtml(item.transcodePreset) : '';

		// Show original size
		var sizeInfo = '<div class="media-size">' + this.formatSize(item.size) + '</div>';

		// Show abort reason for aborted items
		var abortReasonInfo = '';
		if (columnId === 'aborted') {
			if (item.abortReason) {
				abortReasonInfo = '<div class="media-abort-reason" style="color: #e9322d; font-size: 11px; margin-top: 4px; word-wrap: break-word;">' + t('downtranscoder', 'Error: {reason}', {reason: this.escapeHtml(item.abortReason)}) + '</div>';
			} else {
				abortReasonInfo = '<div class="media-abort-reason" style="color: #e9322d; font-size: 11px; margin-top: 4px; word-wrap: break-word;">' + t('downtranscoder', 'Error: Transcoding failed (check logs for details)') + '</div>';
			}
		}

		// Build title attribute - show full path or abort reason
		var titleAttr = this.escapeHtml(item.path);

		return '<div class="media-item" draggable="true" data-item-id="' + item.id + '" data-column-id="' + columnId + '" data-size="' + item.size + '" data-preset="' + presetAttr + '" title="' + titleAttr + '">' +
			'<div class="media-icon"><span class="icon-video"></span></div>' +
			'<div class="media-info">' +
				'<div class="media-name">' + this.escapeHtml(item.name) + '</div>' +
				sizeInfo +
				abortReasonInfo +
				progressBar +
				presetDropdown +
			'</div>' +
			actionButtons +
		'</div>';
	};

	KanbanApp.prototype.setupColumnDragDrop = function(columnEl, column) {
		var self = this;

		// Handle drag over
		columnEl.addEventListener('dragover', function(e) {
			e.preventDefault();
			columnEl.classList.add('drag-over');
		});

		columnEl.addEventListener('dragleave', function(e) {
			if (e.target === columnEl) {
				columnEl.classList.remove('drag-over');
			}
		});

		// Handle drop
		columnEl.addEventListener('drop', function(e) {
			e.preventDefault();
			columnEl.classList.remove('drag-over');

			var data = e.dataTransfer.getData('application/json');
			if (!data) return;

			var dragData = JSON.parse(data);
			self.handleDrop(dragData.itemId, dragData.columnId, column.id);
		});

		// Setup draggable items
		var items = columnEl.querySelectorAll('.media-item');
		items.forEach(function(item) {
			item.addEventListener('dragstart', function(e) {
				var itemId = parseInt(item.dataset.itemId);
				var columnId = item.dataset.columnId;
				e.dataTransfer.setData('application/json', JSON.stringify({ itemId: itemId, columnId: columnId }));
				e.dataTransfer.effectAllowed = 'move';
			});

			// Setup preset dropdown for mediaFound and toTranscode items
			if (column.id === 'mediaFound' || column.id === 'toTranscode') {
				var presetSelect = item.querySelector('.preset-select');
				if (presetSelect) {
					presetSelect.addEventListener('change', function(e) {
						e.stopPropagation();
						var itemId = parseInt(presetSelect.dataset.itemId);
						var preset = presetSelect.value || null;
						self.updatePreset(itemId, preset);
					});
				}
			}
		});

		// Setup delete buttons
		var deleteBtns = columnEl.querySelectorAll('.btn-delete');
		deleteBtns.forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.stopPropagation();
				var fileId = parseInt(btn.dataset.fileId);
				self.handleDeleteOriginal(fileId);
			});
		});
	};

	KanbanApp.prototype.loadMediaItems = function() {
		var self = this;
		this.ajax('GET', OC.generateUrl('/apps/downtranscoder/api/v1/media'))
			.then(function(items) {
				// Clear all columns
				Object.keys(self.columns).forEach(function(key) {
					self.columns[key].items = [];
				});

				// Distribute items to columns
				items.forEach(function(item) {
					var columnId = self.getColumnForState(item.state);
					if (columnId && self.columns[columnId]) {
						self.columns[columnId].items.push(item);
					}
				});

				self.renderColumns();
			})
			.catch(function(error) {
				console.error('Error loading media items:', error);
				OC.Notification.showTemporary(t('downtranscoder', 'Failed to load media items'), { type: 'error' });
			});
	};

	KanbanApp.prototype.checkInitialScanStatus = function() {
		var self = this;
		this.ajax('GET', OC.generateUrl('/apps/downtranscoder/api/v1/scan/status'))
			.then(function(scanStatus) {
				self.updateScanButton(scanStatus);
			})
			.catch(function(error) {
				console.error('Error getting initial scan status:', error);
			});
	};

	KanbanApp.prototype.triggerScan = function() {
		if (this.isScanning) {
			OC.Notification.showTemporary(t('downtranscoder', 'A scan is already in progress'), { type: 'info' });
			return;
		}

		var btn = document.getElementById('btn-scan');
		var self = this;

		console.log('DownTranscoder: triggerScan called');

		// Trigger immediate background scan
		this.ajax('GET', OC.generateUrl('/apps/downtranscoder/api/v1/scan'))
			.then(function(response) {
				console.log('DownTranscoder: Scan response:', response);
				if (response.success) {
					self.isScanning = true;
					btn.disabled = true;
					btn.innerHTML = '<span class="icon-loading-small"></span> ' + t('downtranscoder', 'Scanning...');
					OC.Notification.showTemporary(t('downtranscoder', 'Scan started in background'));
				} else {
					// Scan already in progress (status 409 is handled by ajax as resolve)
					OC.Notification.showTemporary(response.message || t('downtranscoder', 'Scan already in progress'), { type: 'info' });
				}
			})
			.catch(function(error) {
				console.error('DownTranscoder: Error starting scan:', error);
				OC.Notification.showTemporary(t('downtranscoder', 'Failed to start scan: {error}', {error: error}), { type: 'error' });
			});
	};

	KanbanApp.prototype.handleDrop = function(itemId, fromColumnId, toColumnId) {
		if (fromColumnId === toColumnId) return;

		var fromColumn = this.columns[fromColumnId];
		var toColumn = this.columns[toColumnId];

		// Don't allow dropping on columns that don't accept drops
		if (!toColumn.allowDrop) return;

		var itemIndex = fromColumn.items.findIndex(function(item) { return item.id === itemId; });
		if (itemIndex === -1) return;

		var item = fromColumn.items[itemIndex];

		// Confirm if dragging out of transcoding column
		if (fromColumnId === 'transcoding') {
			if (!confirm(t('downtranscoder', 'This file is currently being transcoded. Do you want to abort the transcoding process?'))) {
				return;
			}
		}

		var newState = this.getStateForColumn(toColumnId);

		var self = this;
		this.ajax('PUT', OC.generateUrl('/apps/downtranscoder/api/v1/media/' + itemId + '/state'), { state: newState })
			.then(function() {
				// Move item
				fromColumn.items.splice(itemIndex, 1);
				item.state = newState;
				toColumn.items.push(item);

				self.renderColumns();
				OC.Notification.showTemporary(t('downtranscoder', 'Moved {name} to {column}', {name: item.name, column: toColumn.title}));
			})
			.catch(function(error) {
				console.error('Error updating item state:', error);
				OC.Notification.showTemporary(t('downtranscoder', 'Failed to move item'), { type: 'error' });
			});
	};

	KanbanApp.prototype.startTranscoding = function() {
		if (this.columns.toTranscode.items.length === 0) return;

		var self = this;
		var firstItem = this.columns.toTranscode.items[0];

		// Move first item to transcoding state
		this.ajax('PUT', OC.generateUrl('/apps/downtranscoder/api/v1/media/' + firstItem.id + '/state'), { state: 'transcoding' })
			.then(function() {
				// Move item from toTranscode to transcoding
				self.columns.toTranscode.items.shift();
				firstItem.state = 'transcoding';
				self.columns.transcoding.items.push(firstItem);
				self.renderColumns();

				// Start immediate transcoding for this specific item using the single-item endpoint
				return self.ajax('POST', OC.generateUrl('/apps/downtranscoder/api/v1/transcode/start-single/' + firstItem.id));
			})
			.then(function() {
				OC.Notification.showTemporary(t('downtranscoder', 'Transcoding started instantly for {name}', {name: firstItem.name}));
			})
			.catch(function(error) {
				console.error('Error starting transcoding:', error);
				OC.Notification.showTemporary(t('downtranscoder', 'Failed to start transcoding'), { type: 'error' });
			});
	};

	KanbanApp.prototype.handleDeleteOriginal = function(fileId) {
		if (!confirm(t('downtranscoder', 'Are you sure you want to delete the original file?'))) return;

		var self = this;
		this.ajax('DELETE', OC.generateUrl('/apps/downtranscoder/api/v1/original/' + fileId))
			.then(function() {
				var index = self.columns.transcoded.items.findIndex(function(item) { return item.id === fileId; });
				if (index !== -1) {
					self.columns.transcoded.items.splice(index, 1);
					self.renderColumns();
				}
				OC.Notification.showTemporary(t('downtranscoder', 'Original file deleted successfully'));
			})
			.catch(function(error) {
				console.error('Error deleting original:', error);
				OC.Notification.showTemporary(t('downtranscoder', 'Failed to delete original file'), { type: 'error' });
			});
	};

	KanbanApp.prototype.startStatusPolling = function() {
		var self = this;
		this.statusInterval = setInterval(function() {
			self.updateTranscodingStatus();
		}, 5000);
	};

	KanbanApp.prototype.updateTranscodingStatus = function() {
		var self = this;

		// Check scan status
		this.ajax('GET', OC.generateUrl('/apps/downtranscoder/api/v1/scan/status'))
			.then(function(scanStatus) {
				self.updateScanButton(scanStatus);
			})
			.catch(function(error) {
				console.error('Error getting scan status:', error);
			});

		// Check transcoding status
		this.ajax('GET', OC.generateUrl('/apps/downtranscoder/api/v1/transcode/status'))
			.then(function(status) {
				var needsRefresh = false;

				// Handle items that are now transcoding
				if (status.transcoding && status.transcoding.length > 0) {
					status.transcoding.forEach(function(itemId) {
						// Check if item is in toTranscode and move it to transcoding
						var toTranscodeIndex = self.columns.toTranscode.items.findIndex(function(item) { return item.id === itemId; });
						if (toTranscodeIndex !== -1) {
							var item = self.columns.toTranscode.items[toTranscodeIndex];
							self.columns.toTranscode.items.splice(toTranscodeIndex, 1);
							item.state = 'transcoding';
							self.columns.transcoding.items.push(item);
							needsRefresh = true;
						}
					});
				}

				// Handle items that are now completed
				if (status.completed && status.completed.length > 0) {
					status.completed.forEach(function(itemId) {
						// Check if item is in transcoding and move it to transcoded
						var transcodingIndex = self.columns.transcoding.items.findIndex(function(item) { return item.id === itemId; });
						if (transcodingIndex !== -1) {
							var item = self.columns.transcoding.items[transcodingIndex];
							self.columns.transcoding.items.splice(transcodingIndex, 1);
							item.state = 'transcoded';
							self.columns.transcoded.items.push(item);
							needsRefresh = true;
						}

						// Also check toTranscode in case status update was missed
						var toTranscodeIndex = self.columns.toTranscode.items.findIndex(function(item) { return item.id === itemId; });
						if (toTranscodeIndex !== -1) {
							var item = self.columns.toTranscode.items[toTranscodeIndex];
							self.columns.toTranscode.items.splice(toTranscodeIndex, 1);
							item.state = 'transcoded';
							self.columns.transcoded.items.push(item);
							needsRefresh = true;
						}
					});
				}

				if (needsRefresh) {
					self.renderColumns();
				}
			})
			.catch(function(error) {
				// Silently fail for polling
				console.error('Error updating status:', error);
			});
	};

	KanbanApp.prototype.updateScanButton = function(scanStatus) {
		var btn = document.getElementById('btn-scan');
		if (!btn) return;

		var wasScanning = this.isScanning;
		this.isScanning = scanStatus.is_scanning || false;

		if (this.isScanning) {
			btn.disabled = true;
			btn.innerHTML = '<span class="icon-loading-small"></span> ' + t('downtranscoder', 'Scanning...');
		} else {
			btn.disabled = false;
			btn.innerHTML = '<span class="icon-search"></span> ' + t('downtranscoder', 'Scan Media');

			// If scan just completed, reload media items and show notification
			if (wasScanning && !this.isScanning) {
				this.loadMediaItems();
				var filesFound = scanStatus.files_found || 0;
				OC.Notification.showTemporary(t('downtranscoder', 'Scan complete. Found {count} files', {count: filesFound}));
			}
		}
	};

	KanbanApp.prototype.updateTranscodeButton = function() {
		var btn = document.getElementById('btn-transcode');
		if (btn) {
			btn.disabled = this.columns.toTranscode.items.length === 0;
		}
	};

	// Helper methods
	KanbanApp.prototype.getColumnForState = function(state) {
		var stateMap = {
			'found': 'mediaFound',
			'queued': 'toTranscode',
			'transcoding': 'transcoding',
			'transcoded': 'transcoded',
			'aborted': 'aborted',
			'discarded': 'discard'
		};
		return stateMap[state] || 'mediaFound';
	};

	KanbanApp.prototype.getStateForColumn = function(columnId) {
		var columnMap = {
			'mediaFound': 'found',
			'toTranscode': 'queued',
			'transcoding': 'transcoding',
			'transcoded': 'transcoded',
			'aborted': 'aborted',
			'discard': 'discarded'
		};
		return columnMap[columnId] || 'found';
	};

	KanbanApp.prototype.formatSize = function(bytes) {
		if (!bytes) return '0 B';
		var k = 1024;
		var sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
		var i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
	};

	KanbanApp.prototype.estimateTranscodedSize = function(originalSize, preset) {
		// Compression ratios based on typical results for different presets
		// These are conservative estimates
		var compressionRatios = {
			'h265_crf23': 0.35,  // High quality, ~35% of original
			'h265_crf26': 0.25,  // Recommended, ~25% of original
			'h265_crf28': 0.18,  // Smaller, ~18% of original
			'h264_crf23': 0.50,  // H.264 is less efficient, ~50% of original
			'default': 0.30      // Default setting, ~30% of original
		};

		var ratio = compressionRatios[preset] || compressionRatios['default'];
		return Math.round(originalSize * ratio);
	};

	KanbanApp.prototype.estimateTranscodeProgress = function(item) {
		// Estimate progress based on file size and elapsed time
		// This is a rough approximation since we don't have real FFmpeg progress

		var now = Math.floor(Date.now() / 1000); // Current time in seconds
		var updatedAt = item.updatedAt || now; // When item moved to transcoding state
		var elapsedSeconds = now - updatedAt;

		// Estimate total transcode time based on file size
		// Rough estimate: ~60 seconds per GB (can vary widely based on CPU, codec, etc.)
		var sizeGB = item.size / (1024 * 1024 * 1024);
		var estimatedTotalSeconds = sizeGB * 60;

		// Adjust for very small or very large files
		if (sizeGB < 1) {
			estimatedTotalSeconds = 30; // Minimum 30 seconds for small files
		} else if (sizeGB > 100) {
			// For very large files, scale more conservatively
			estimatedTotalSeconds = 60 * 60 + (sizeGB - 100) * 30; // 60 min base + 30s per GB over 100
		}

		// Calculate progress percentage
		var progress = Math.min(95, Math.floor((elapsedSeconds / estimatedTotalSeconds) * 100));

		// Never show 0% if transcoding has started
		if (progress < 5 && elapsedSeconds > 0) {
			progress = 5;
		}

		return progress;
	};

	KanbanApp.prototype.escapeHtml = function(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	};

	KanbanApp.prototype.updatePreset = function(itemId, preset) {
		var self = this;
		this.ajax('PUT', OC.generateUrl('/apps/downtranscoder/api/v1/media/' + itemId + '/preset'), { preset: preset })
			.then(function() {
				// Update the item in the columns (could be in mediaFound or toTranscode)
				var item = self.columns.mediaFound.items.find(function(i) { return i.id === itemId; });
				if (!item) {
					item = self.columns.toTranscode.items.find(function(i) { return i.id === itemId; });
				}
				if (item) {
					item.transcodePreset = preset;
					self.renderColumns();
				}
				OC.Notification.showTemporary(t('downtranscoder', 'Preset updated'));
			})
			.catch(function(error) {
				console.error('Error updating preset:', error);
				OC.Notification.showTemporary(t('downtranscoder', 'Failed to update preset'), { type: 'error' });
			});
	};

	KanbanApp.prototype.ajax = function(method, url, data) {
		return new Promise(function(resolve, reject) {
			var xhr = new XMLHttpRequest();
			xhr.open(method, url, true);
			xhr.setRequestHeader('Content-Type', 'application/json');
			xhr.setRequestHeader('requesttoken', OC.requestToken);

			xhr.onload = function() {
				if (xhr.status >= 200 && xhr.status < 300) {
					try {
						resolve(JSON.parse(xhr.responseText));
					} catch (e) {
						resolve(xhr.responseText);
					}
				} else if (xhr.status === 409) {
					// Handle conflict (scan already in progress)
					try {
						var response = JSON.parse(xhr.responseText);
						// Resolve with success=false so the caller can show the message
						resolve(response);
					} catch (e) {
						reject(new Error('HTTP ' + xhr.status));
					}
				} else {
					reject(new Error('HTTP ' + xhr.status));
				}
			};

			xhr.onerror = function() {
				reject(new Error('Network error'));
			};

			if (data) {
				xhr.send(JSON.stringify(data));
			} else {
				xhr.send();
			}
		});
	};

})();
