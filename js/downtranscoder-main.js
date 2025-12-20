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
			mediaFound: { id: 'mediaFound', title: 'Media Found', icon: 'icon-search', items: [], allowDrop: true },
			toTranscode: { id: 'toTranscode', title: 'To Transcode', icon: 'icon-play', items: [], allowDrop: true },
			transcoding: { id: 'transcoding', title: 'Transcoding In Progress', icon: 'icon-loading-small', items: [], allowDrop: false },
			transcoded: { id: 'transcoded', title: 'Transcoded (Waiting for Deletion)', icon: 'icon-checkmark', items: [], allowDrop: false },
			aborted: { id: 'aborted', title: 'Aborted', icon: 'icon-error', items: [], allowDrop: true },
			discard: { id: 'discard', title: 'Discard', icon: 'icon-delete', items: [], allowDrop: true }
		};
		this.columnOrder = ['mediaFound', 'toTranscode', 'transcoding', 'transcoded', 'aborted', 'discard'];
		this.isScanning = false;
		this.statusInterval = null;
	}

	KanbanApp.prototype.init = function() {
		this.render();
		this.loadMediaItems();
		this.startStatusPolling();
	};

	KanbanApp.prototype.render = function() {
		var container = document.getElementById('downtranscoder-app');
		if (!container) return;

		var html = '<div class="kanban-board">' +
			'<div class="kanban-header">' +
				'<h2>Media Transcoding Board</h2>' +
				'<div class="kanban-actions">' +
					'<button id="btn-scan" class="button primary">' +
						'<span class="icon-search"></span> Scan Media' +
					'</button>' +
					'<button id="btn-transcode" class="button">' +
						'<span class="icon-play"></span> Start Transcoding' +
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
			content += '<div class="empty-column"><p>No items</p></div>';
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

		// Delete button for transcoded items
		if (columnId === 'transcoded') {
			actionButtons = '<div class="media-actions">' +
				'<button class="button-vue button-vue--error btn-delete" data-file-id="' + item.id + '" title="Delete original file">' +
					'<span class="icon-delete"></span>' +
				'</button>' +
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

			presetDropdown = '<select class="preset-select" data-item-id="' + item.id + '" title="Transcode Preset">' +
				'<option value="" ' + (currentPreset === '' ? 'selected' : '') + '>Default - H.265 CRF 26 (~' + defaultEstimate + ', High Quality)</option>' +
				'<option value="h265_crf23" ' + (currentPreset === 'h265_crf23' ? 'selected' : '') + '>H.265 CRF 23 (~' + h265_23_estimate + ', Highest Quality)</option>' +
				'<option value="h265_crf26" ' + (currentPreset === 'h265_crf26' ? 'selected' : '') + '>H.265 CRF 26 (~' + h265_26_estimate + ', High Quality)</option>' +
				'<option value="h265_crf28" ' + (currentPreset === 'h265_crf28' ? 'selected' : '') + '>H.265 CRF 28 (~' + h265_28_estimate + ', Good Quality, Smaller)</option>' +
				'<option value="h264_crf23" ' + (currentPreset === 'h264_crf23' ? 'selected' : '') + '>H.264 CRF 23 (~' + h264_23_estimate + ', High Quality, Compatible)</option>' +
			'</select>';
		}

		var presetAttr = item.transcodePreset ? this.escapeHtml(item.transcodePreset) : '';

		// Show original size
		var sizeInfo = '<div class="media-size">' + this.formatSize(item.size) + '</div>';

		// Build title attribute - show abort reason for aborted items, otherwise show path
		var titleAttr = '';
		if (columnId === 'aborted' && item.abortReason) {
			titleAttr = 'Error: ' + this.escapeHtml(item.abortReason);
		} else {
			titleAttr = this.escapeHtml(item.path);
		}

		return '<div class="media-item" draggable="true" data-item-id="' + item.id + '" data-column-id="' + columnId + '" data-size="' + item.size + '" data-preset="' + presetAttr + '" title="' + titleAttr + '">' +
			'<div class="media-icon"><span class="icon-video"></span></div>' +
			'<div class="media-info">' +
				'<div class="media-name">' + this.escapeHtml(item.name) + '</div>' +
				sizeInfo +
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
				OC.Notification.showTemporary('Failed to load media items', { type: 'error' });
			});
	};

	KanbanApp.prototype.triggerScan = function() {
		if (this.isScanning) return;

		this.isScanning = true;
		var btn = document.getElementById('btn-scan');
		btn.disabled = true;
		btn.innerHTML = '<span class="icon-loading-small"></span> Scanning...';

		var self = this;
		this.ajax('GET', OC.generateUrl('/apps/downtranscoder/api/v1/scan'))
			.then(function(newFiles) {
				// After scanning, reload all media items from database
				// This ensures we have the correct database IDs
				return self.loadMediaItems();
			})
			.then(function() {
				OC.Notification.showTemporary('Scan complete');
			})
			.catch(function(error) {
				console.error('Error scanning:', error);
				OC.Notification.showTemporary('Failed to scan for media files', { type: 'error' });
			})
			.finally(function() {
				self.isScanning = false;
				btn.disabled = false;
				btn.innerHTML = '<span class="icon-search"></span> Scan Media';
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
			if (!confirm('This file is currently being transcoded. Do you want to abort the transcoding process?')) {
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
				OC.Notification.showTemporary('Moved ' + item.name + ' to ' + toColumn.title);
			})
			.catch(function(error) {
				console.error('Error updating item state:', error);
				OC.Notification.showTemporary('Failed to move item', { type: 'error' });
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
				OC.Notification.showTemporary('Transcoding started instantly for ' + firstItem.name);
			})
			.catch(function(error) {
				console.error('Error starting transcoding:', error);
				OC.Notification.showTemporary('Failed to start transcoding', { type: 'error' });
			});
	};

	KanbanApp.prototype.handleDeleteOriginal = function(fileId) {
		if (!confirm('Are you sure you want to delete the original file?')) return;

		var self = this;
		this.ajax('DELETE', OC.generateUrl('/apps/downtranscoder/api/v1/original/' + fileId))
			.then(function() {
				var index = self.columns.transcoded.items.findIndex(function(item) { return item.id === fileId; });
				if (index !== -1) {
					self.columns.transcoded.items.splice(index, 1);
					self.renderColumns();
				}
				OC.Notification.showTemporary('Original file deleted successfully');
			})
			.catch(function(error) {
				console.error('Error deleting original:', error);
				OC.Notification.showTemporary('Failed to delete original file', { type: 'error' });
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
				OC.Notification.showTemporary('Preset updated');
			})
			.catch(function(error) {
				console.error('Error updating preset:', error);
				OC.Notification.showTemporary('Failed to update preset', { type: 'error' });
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
