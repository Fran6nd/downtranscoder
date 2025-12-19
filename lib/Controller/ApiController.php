<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Controller;

use OCA\DownTranscoder\Service\MediaScannerService;
use OCA\DownTranscoder\Service\TranscodingQueueService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class ApiController extends Controller {
    private MediaScannerService $scannerService;
    private TranscodingQueueService $queueService;

    public function __construct(
        string $appName,
        IRequest $request,
        MediaScannerService $scannerService,
        TranscodingQueueService $queueService
    ) {
        parent::__construct($appName, $request);
        $this->scannerService = $scannerService;
        $this->queueService = $queueService;
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
}
