<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Controller;

use OCA\DownTranscoder\Service\MediaScannerService;
use OCA\DownTranscoder\Service\TranscodingQueueService;
use OCA\DownTranscoder\Service\MediaStateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ApiController extends Controller {
    private MediaScannerService $scannerService;
    private TranscodingQueueService $queueService;
    private MediaStateService $stateService;

    public function __construct(
        string $appName,
        IRequest $request,
        MediaScannerService $scannerService,
        TranscodingQueueService $queueService,
        MediaStateService $stateService
    ) {
        parent::__construct($appName, $request);
        $this->scannerService = $scannerService;
        $this->queueService = $queueService;
        $this->stateService = $stateService;
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function scan(): JSONResponse {
        try {
            $files = $this->scannerService->scanForLargeFiles();
            return new JSONResponse($files);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getQueue(): JSONResponse {
        try {
            $queue = $this->queueService->getQueue();
            return new JSONResponse($queue);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     */
    public function addToQueue(int $fileId): JSONResponse {
        try {
            $this->queueService->addToQueue($fileId);
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     */
    public function removeFromQueue(int $fileId): JSONResponse {
        try {
            $this->queueService->removeFromQueue($fileId);
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     */
    public function startTranscoding(): JSONResponse {
        try {
            $this->queueService->startTranscoding();
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getStatus(): JSONResponse {
        try {
            $status = $this->queueService->getStatus();
            return new JSONResponse($status);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @NoAdminRequired
     */
    public function deleteOriginal(int $fileId): JSONResponse {
        try {
            $this->queueService->deleteOriginal($fileId);
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get all media items with their current states for the kanban board
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getMediaItems(): JSONResponse {
        try {
            $items = $this->stateService->getAllMediaItems();
            return new JSONResponse($items);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update the state of a media item (for kanban board drag-and-drop)
     *
     * @NoAdminRequired
     */
    public function updateMediaState(int $fileId): JSONResponse {
        try {
            $state = $this->request->getParam('state');
            if (!in_array($state, ['found', 'queued', 'transcoded', 'discarded'])) {
                return new JSONResponse(
                    ['error' => 'Invalid state'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $this->stateService->updateMediaState($fileId, $state);
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Discard a media item (mark it as discarded)
     *
     * @NoAdminRequired
     */
    public function discardMedia(int $fileId): JSONResponse {
        try {
            $this->stateService->updateMediaState($fileId, 'discarded');
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
