<?php

return [
    'routes' => [
        // Page routes
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // API routes
        ['name' => 'api#scan', 'url' => '/api/v1/scan', 'verb' => 'GET'],
        ['name' => 'api#getScanStatus', 'url' => '/api/v1/scan/status', 'verb' => 'GET'],
        ['name' => 'api#startTranscoding', 'url' => '/api/v1/transcode/start', 'verb' => 'POST'],
        ['name' => 'api#startTranscodingSingle', 'url' => '/api/v1/transcode/start-single/{id}', 'verb' => 'POST'],
        ['name' => 'api#getStatus', 'url' => '/api/v1/transcode/status', 'verb' => 'GET'],
        ['name' => 'api#deleteOriginal', 'url' => '/api/v1/original/{fileId}', 'verb' => 'DELETE'],

        // Kanban board state management
        ['name' => 'api#getMediaItems', 'url' => '/api/v1/media', 'verb' => 'GET'],
        ['name' => 'api#updateMediaState', 'url' => '/api/v1/media/{id}/state', 'verb' => 'PUT'],
        ['name' => 'api#discardMedia', 'url' => '/api/v1/media/{id}/discard', 'verb' => 'POST'],
        ['name' => 'api#updatePreset', 'url' => '/api/v1/media/{id}/preset', 'verb' => 'PUT'],
        ['name' => 'api#resetDatabase', 'url' => '/api/v1/reset-database', 'verb' => 'POST'],
    ],
];
