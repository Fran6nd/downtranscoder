<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Controller;

use OCA\DownTranscoder\BackgroundJob\ScanJob;
use OCA\DownTranscoder\Service\MediaScannerService;
use OCA\DownTranscoder\Service\TranscodingQueueService;
use OCA\DownTranscoder\Service\MediaStateService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\BackgroundJob\IJobList;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class ApiController extends Controller {
    private MediaScannerService $scannerService;
    private TranscodingQueueService $queueService;
    private MediaStateService $stateService;
    private IJobList $jobList;
    private LoggerInterface $logger;

    public function __construct(
        string $appName,
        IRequest $request,
        MediaScannerService $scannerService,
        TranscodingQueueService $queueService,
        MediaStateService $stateService,
        IJobList $jobList,
        LoggerInterface $logger
    ) {
        parent::__construct($appName, $request);
        $this->scannerService = $scannerService;
        $this->queueService = $queueService;
        $this->stateService = $stateService;
        $this->jobList = $jobList;
        $this->logger = $logger;
    }

    /**
     * Queue a background scan job
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function scan(): JSONResponse {
        try {
            // Check if a scan is already running
            $status = $this->scannerService->getScanStatus();
            if ($status['is_scanning'] ?? false) {
                return new JSONResponse([
                    'success' => false,
                    'message' => 'A scan is already in progress',
                    'status' => $status
                ]);
            }

            // Queue the scan job to run in the background
            $this->jobList->add(ScanJob::class);
            $this->logger->info('Scan job queued successfully');

            return new JSONResponse([
                'success' => true,
                'message' => 'Scan job queued successfully'
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to queue scan job: ' . $e->getMessage());
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get scan status
     *
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getScanStatus(): JSONResponse {
        try {
            $status = $this->scannerService->getScanStatus();
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
     * Start transcoding a specific item immediately
     *
     * @NoAdminRequired
     */
    public function startTranscodingSingle(int $id): JSONResponse {
        try {
            $this->queueService->startTranscodingSingle($id);
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

            // Add information about files that have changed state
            $transcodingItems = $this->stateService->getMediaItemsByState('transcoding');
            $transcodedItems = $this->stateService->getMediaItemsByState('transcoded');

            $status['transcoding'] = array_map(fn($item) => $item['id'], $transcodingItems);
            $status['completed'] = array_map(fn($item) => $item['id'], $transcodedItems);

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
    public function updateMediaState(int $id): JSONResponse {
        try {
            $state = $this->request->getParam('state');

            if (!$state) {
                return new JSONResponse(
                    ['error' => 'State parameter is required'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            if (!in_array($state, ['found', 'queued', 'transcoding', 'transcoded', 'aborted', 'discarded'])) {
                return new JSONResponse(
                    ['error' => 'Invalid state: ' . $state],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $this->stateService->updateMediaState($id, $state);
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Error updating media state for record ' . $id . ': ' . $e->getMessage(), ['app' => 'downtranscoder']);
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
    public function discardMedia(int $id): JSONResponse {
        try {
            $this->stateService->updateMediaState($id, 'discarded');
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update the transcode preset for a media item
     *
     * @NoAdminRequired
     */
    public function updatePreset(int $id): JSONResponse {
        try {
            $preset = $this->request->getParam('preset');

            // Validate preset value
            $allowedPresets = ['h265_crf23', 'h265_crf26', 'h265_crf28', 'h264_crf23', null];
            if ($preset === '') {
                $preset = null; // Normalize empty string to null
            }

            if ($preset !== null && !in_array($preset, $allowedPresets, true)) {
                return new JSONResponse(
                    ['error' => 'Invalid preset value'],
                    Http::STATUS_BAD_REQUEST
                );
            }

            $this->stateService->updateTranscodePreset($id, $preset);
            return new JSONResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Error updating preset for record ' . $id . ': ' . $e->getMessage(), ['app' => 'downtranscoder']);
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}
