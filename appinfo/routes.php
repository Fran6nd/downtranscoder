<?php

return [
    'routes' => [
        // Page routes
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // API routes
        ['name' => 'api#scan', 'url' => '/api/v1/scan', 'verb' => 'GET'],
        ['name' => 'api#getQueue', 'url' => '/api/v1/queue', 'verb' => 'GET'],
        ['name' => 'api#addToQueue', 'url' => '/api/v1/queue/{fileId}', 'verb' => 'POST'],
        ['name' => 'api#removeFromQueue', 'url' => '/api/v1/queue/{fileId}', 'verb' => 'DELETE'],
        ['name' => 'api#startTranscoding', 'url' => '/api/v1/transcode/start', 'verb' => 'POST'],
        ['name' => 'api#getStatus', 'url' => '/api/v1/transcode/status', 'verb' => 'GET'],
        ['name' => 'api#deleteOriginal', 'url' => '/api/v1/original/{fileId}', 'verb' => 'DELETE'],

        // Kanban board state management
        ['name' => 'api#getMediaItems', 'url' => '/api/v1/media', 'verb' => 'GET'],
        ['name' => 'api#updateMediaState', 'url' => '/api/v1/media/{id}/state', 'verb' => 'PUT'],
        ['name' => 'api#discardMedia', 'url' => '/api/v1/media/{id}/discard', 'verb' => 'POST'],
    ],
];
