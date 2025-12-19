/**
 * DownTranscoder - Main Kanban Board Application
 *
 * This file initializes the Vue.js kanban board for managing media transcoding workflow.
 * The board has 4 columns:
 * 1. Media Found - scanned media files
 * 2. To Transcode - files queued for transcoding
 * 3. Transcoded (Waiting for Original Deletion) - transcoded files
 * 4. Discard - files to ignore
 */

import Vue from 'vue';
import { generateUrl } from '@nextcloud/router';
import { showError, showSuccess } from '@nextcloud/dialogs';
import axios from '@nextcloud/axios';

// Kanban Board Component
const KanbanBoard = {
    name: 'KanbanBoard',
    template: `
        <div class="kanban-board">
            <div class="kanban-header">
                <h2>Media Transcoding Board</h2>
                <div class="kanban-actions">
                    <button
                        @click="triggerScan"
                        :disabled="isScanning"
                        class="button primary">
                        <span class="icon-search"></span>
                        {{ isScanning ? 'Scanning...' : 'Scan Media' }}
                    </button>
                    <button
                        @click="startTranscoding"
                        :disabled="columns.toTranscode.items.length === 0"
                        class="button">
                        <span class="icon-play"></span>
                        Start Transcoding
                    </button>
                </div>
            </div>

            <div class="kanban-columns">
                <kanban-column
                    v-for="column in columnOrder"
                    :key="column"
                    :column="columns[column]"
                    :column-id="column"
                    @drop="handleDrop"
                    @delete-original="handleDeleteOriginal"
                ></kanban-column>
            </div>
        </div>
    `,
    data() {
        return {
            isScanning: false,
            columns: {
                mediaFound: {
                    id: 'mediaFound',
                    title: 'Media Found',
                    icon: 'icon-search',
                    items: [],
                    allowDrop: false
                },
                toTranscode: {
                    id: 'toTranscode',
                    title: 'To Transcode',
                    icon: 'icon-play',
                    items: [],
                    allowDrop: true
                },
                transcoded: {
                    id: 'transcoded',
                    title: 'Transcoded (Waiting for Deletion)',
                    icon: 'icon-checkmark',
                    items: [],
                    allowDrop: false
                },
                discard: {
                    id: 'discard',
                    title: 'Discard',
                    icon: 'icon-delete',
                    items: [],
                    allowDrop: true
                }
            },
            columnOrder: ['mediaFound', 'toTranscode', 'transcoded', 'discard']
        };
    },
    mounted() {
        this.loadMediaItems();
        // Poll for status updates every 5 seconds
        this.statusInterval = setInterval(() => {
            this.updateTranscodingStatus();
        }, 5000);
    },
    beforeDestroy() {
        if (this.statusInterval) {
            clearInterval(this.statusInterval);
        }
    },
    methods: {
        async loadMediaItems() {
            try {
                const response = await axios.get(generateUrl('/apps/downtranscoder/api/v1/media'));
                const items = response.data;

                // Clear all columns
                Object.keys(this.columns).forEach(key => {
                    this.columns[key].items = [];
                });

                // Distribute items to appropriate columns based on their state
                items.forEach(item => {
                    const columnId = this.getColumnForState(item.state);
                    if (columnId && this.columns[columnId]) {
                        this.columns[columnId].items.push(item);
                    }
                });
            } catch (error) {
                console.error('Error loading media items:', error);
                showError('Failed to load media items');
            }
        },
        async triggerScan() {
            this.isScanning = true;
            try {
                const response = await axios.get(generateUrl('/apps/downtranscoder/api/v1/scan'));
                const newFiles = response.data;

                // Add scanned files to "Media Found" column
                newFiles.forEach(file => {
                    // Check if file already exists in any column
                    const exists = Object.values(this.columns).some(col =>
                        col.items.some(item => item.id === file.id)
                    );

                    if (!exists) {
                        this.columns.mediaFound.items.push({
                            id: file.id,
                            name: file.name,
                            size: file.size,
                            path: file.path,
                            state: 'found'
                        });
                    }
                });

                showSuccess(`Found ${newFiles.length} media files`);
            } catch (error) {
                console.error('Error scanning:', error);
                showError('Failed to scan for media files');
            } finally {
                this.isScanning = false;
            }
        },
        async handleDrop(event) {
            const { itemId, fromColumnId, toColumnId } = event;

            // Find the item in the source column
            const fromColumn = this.columns[fromColumnId];
            const itemIndex = fromColumn.items.findIndex(item => item.id === itemId);

            if (itemIndex === -1) return;

            const item = fromColumn.items[itemIndex];
            const toColumn = this.columns[toColumnId];

            // Don't allow dropping on columns that don't accept drops
            if (!toColumn.allowDrop && toColumnId !== 'mediaFound') return;

            try {
                // Update state in backend
                const newState = this.getStateForColumn(toColumnId);
                await axios.put(
                    generateUrl(`/apps/downtranscoder/api/v1/media/${itemId}/state`),
                    { state: newState }
                );

                // Move item between columns
                fromColumn.items.splice(itemIndex, 1);
                toColumn.items.push({ ...item, state: newState });

                showSuccess(`Moved ${item.name} to ${toColumn.title}`);
            } catch (error) {
                console.error('Error updating item state:', error);
                showError('Failed to move item');
            }
        },
        async startTranscoding() {
            try {
                await axios.post(generateUrl('/apps/downtranscoder/api/v1/transcode/start'));
                showSuccess('Transcoding started');
            } catch (error) {
                console.error('Error starting transcoding:', error);
                showError('Failed to start transcoding');
            }
        },
        async updateTranscodingStatus() {
            try {
                const response = await axios.get(generateUrl('/apps/downtranscoder/api/v1/transcode/status'));
                const status = response.data;

                // Update items that have been transcoded
                if (status.completed && status.completed.length > 0) {
                    status.completed.forEach(fileId => {
                        const toTranscodeIndex = this.columns.toTranscode.items.findIndex(
                            item => item.id === fileId
                        );

                        if (toTranscodeIndex !== -1) {
                            const item = this.columns.toTranscode.items[toTranscodeIndex];
                            this.columns.toTranscode.items.splice(toTranscodeIndex, 1);
                            this.columns.transcoded.items.push({ ...item, state: 'transcoded' });
                        }
                    });
                }
            } catch (error) {
                console.error('Error updating status:', error);
            }
        },
        async handleDeleteOriginal(fileId) {
            try {
                await axios.delete(generateUrl(`/apps/downtranscoder/api/v1/original/${fileId}`));

                // Remove from transcoded column
                const index = this.columns.transcoded.items.findIndex(item => item.id === fileId);
                if (index !== -1) {
                    this.columns.transcoded.items.splice(index, 1);
                }

                showSuccess('Original file deleted successfully');
            } catch (error) {
                console.error('Error deleting original:', error);
                showError('Failed to delete original file');
            }
        },
        getColumnForState(state) {
            const stateMap = {
                'found': 'mediaFound',
                'queued': 'toTranscode',
                'transcoded': 'transcoded',
                'discarded': 'discard'
            };
            return stateMap[state] || 'mediaFound';
        },
        getStateForColumn(columnId) {
            const columnMap = {
                'mediaFound': 'found',
                'toTranscode': 'queued',
                'transcoded': 'transcoded',
                'discard': 'discarded'
            };
            return columnMap[columnId] || 'found';
        }
    }
};

// Kanban Column Component
const KanbanColumn = {
    name: 'KanbanColumn',
    template: `
        <div
            class="kanban-column"
            @dragover.prevent="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
            :class="{ 'drag-over': isDragOver }">
            <div class="column-header">
                <span :class="column.icon"></span>
                <h3>{{ column.title }}</h3>
                <span class="item-count">{{ column.items.length }}</span>
            </div>
            <div class="column-content">
                <media-item
                    v-for="item in column.items"
                    :key="item.id"
                    :item="item"
                    :column-id="columnId"
                    @dragstart="handleDragStart"
                    @delete-original="$emit('delete-original', item.id)"
                ></media-item>
                <div v-if="column.items.length === 0" class="empty-column">
                    <p>No items</p>
                </div>
            </div>
        </div>
    `,
    props: {
        column: {
            type: Object,
            required: true
        },
        columnId: {
            type: String,
            required: true
        }
    },
    data() {
        return {
            isDragOver: false
        };
    },
    methods: {
        handleDragStart(event) {
            this.draggedItem = event;
        },
        handleDragOver(event) {
            event.preventDefault();
            this.isDragOver = true;
        },
        handleDragLeave() {
            this.isDragOver = false;
        },
        handleDrop(event) {
            event.preventDefault();
            this.isDragOver = false;

            const data = event.dataTransfer.getData('application/json');
            if (!data) return;

            const dragData = JSON.parse(data);

            this.$emit('drop', {
                itemId: dragData.itemId,
                fromColumnId: dragData.columnId,
                toColumnId: this.columnId
            });
        }
    }
};

// Media Item Component
const MediaItem = {
    name: 'MediaItem',
    template: `
        <div
            class="media-item"
            draggable="true"
            @dragstart="handleDragStart">
            <div class="media-icon">
                <span class="icon-video"></span>
            </div>
            <div class="media-info">
                <div class="media-name" :title="item.path">{{ item.name }}</div>
                <div class="media-size">{{ formatSize(item.size) }}</div>
            </div>
            <div v-if="columnId === 'transcoded'" class="media-actions">
                <button
                    @click.stop="$emit('delete-original')"
                    class="button-vue button-vue--error"
                    title="Delete original file">
                    <span class="icon-delete"></span>
                </button>
            </div>
        </div>
    `,
    props: {
        item: {
            type: Object,
            required: true
        },
        columnId: {
            type: String,
            required: true
        }
    },
    methods: {
        handleDragStart(event) {
            const dragData = {
                itemId: this.item.id,
                columnId: this.columnId
            };
            event.dataTransfer.setData('application/json', JSON.stringify(dragData));
            event.dataTransfer.effectAllowed = 'move';
        },
        formatSize(bytes) {
            if (!bytes) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    }
};

// Initialize the Vue app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Register components
    Vue.component('kanban-board', KanbanBoard);
    Vue.component('kanban-column', KanbanColumn);
    Vue.component('media-item', MediaItem);

    // Create Vue instance
    new Vue({
        el: '#downtranscoder-app',
        template: '<kanban-board />'
    });
});
