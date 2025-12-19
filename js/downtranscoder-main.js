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
			mediaFound: { id: 'mediaFound', title: 'Media Found', icon: 'icon-search', items: [], allowDrop: false },
			toTranscode: { id: 'toTranscode', title: 'To Transcode', icon: 'icon-play', items: [], allowDrop: true },
			transcoded: { id: 'transcoded', title: 'Transcoded (Waiting for Deletion)', icon: 'icon-checkmark', items: [], allowDrop: false },
			discard: { id: 'discard', title: 'Discard', icon: 'icon-delete', items: [], allowDrop: true }
		};
		this.columnOrder = ['mediaFound', 'toTranscode', 'transcoded', 'discard'];
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

		this.columnOrder.forEach(function(columnId) {
			var column = self.columns[columnId];
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
		var deleteBtn = '';
		if (columnId === 'transcoded') {
			deleteBtn = '<div class="media-actions">' +
				'<button class="button-vue button-vue--error btn-delete" data-file-id="' + item.id + '" title="Delete original file">' +
					'<span class="icon-delete"></span>' +
				'</button>' +
			'</div>';
		}

		return '<div class="media-item" draggable="true" data-item-id="' + item.id + '" data-column-id="' + columnId + '">' +
			'<div class="media-icon"><span class="icon-video"></span></div>' +
			'<div class="media-info">' +
				'<div class="media-name" title="' + this.escapeHtml(item.path) + '">' + this.escapeHtml(item.name) + '</div>' +
				'<div class="media-size">' + this.formatSize(item.size) + '</div>' +
			'</div>' +
			deleteBtn +
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
				// Add scanned files to Media Found column
				newFiles.forEach(function(file) {
					var exists = Object.values(self.columns).some(function(col) {
						return col.items.some(function(item) { return item.id === file.id; });
					});

					if (!exists) {
						self.columns.mediaFound.items.push({
							id: file.id,
							name: file.name,
							size: file.size,
							path: file.path,
							state: 'found'
						});
					}
				});

				self.renderColumns();
				OC.Notification.showTemporary('Found ' + newFiles.length + ' media files');
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

		if (!toColumn.allowDrop && toColumnId !== 'mediaFound') return;

		var itemIndex = fromColumn.items.findIndex(function(item) { return item.id === itemId; });
		if (itemIndex === -1) return;

		var item = fromColumn.items[itemIndex];
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
		this.ajax('POST', OC.generateUrl('/apps/downtranscoder/api/v1/transcode/start'))
			.then(function() {
				OC.Notification.showTemporary('Transcoding started');
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
				if (status.completed && status.completed.length > 0) {
					status.completed.forEach(function(fileId) {
						var index = self.columns.toTranscode.items.findIndex(function(item) { return item.id === fileId; });
						if (index !== -1) {
							var item = self.columns.toTranscode.items[index];
							self.columns.toTranscode.items.splice(index, 1);
							item.state = 'transcoded';
							self.columns.transcoded.items.push(item);
						}
					});
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
			'transcoded': 'transcoded',
			'discarded': 'discard'
		};
		return stateMap[state] || 'mediaFound';
	};

	KanbanApp.prototype.getStateForColumn = function(columnId) {
		var columnMap = {
			'mediaFound': 'found',
			'toTranscode': 'queued',
			'transcoded': 'transcoded',
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

	KanbanApp.prototype.escapeHtml = function(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
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
