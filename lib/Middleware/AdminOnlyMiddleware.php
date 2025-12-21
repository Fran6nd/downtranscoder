<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Middleware;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Middleware to restrict app access to admin users when admin_only setting is enabled
 */
class AdminOnlyMiddleware extends Middleware {
    private IConfig $config;
    private IUserSession $userSession;
    private IGroupManager $groupManager;
    private LoggerInterface $logger;

    public function __construct(
        IConfig $config,
        IUserSession $userSession,
        IGroupManager $groupManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->logger = $logger;
    }

    /**
     * Check if the app is in admin-only mode and if the user is an admin
     *
     * @param \OCP\AppFramework\Controller $controller
     * @param string $methodName
     * @throws \OCP\AppFramework\Middleware\Security\Exceptions\NotAdminException
     */
    public function beforeController($controller, $methodName): void {
        // Check if admin-only mode is enabled (default: true)
        $adminOnly = $this->config->getAppValue('downtranscoder', 'admin_only', 'true') === 'true';

        if (!$adminOnly) {
            // Admin-only mode is disabled, allow all users
            return;
        }

        // Get current user
        $user = $this->userSession->getUser();
        if ($user === null) {
            // No user session - shouldn't happen with @NoAdminRequired, but check anyway
            throw new \Exception('User not logged in');
        }

        // Check if user is admin
        $isAdmin = $this->groupManager->isAdmin($user->getUID());

        if (!$isAdmin) {
            $this->logger->warning(
                'Access denied: User ' . $user->getUID() . ' attempted to access ' . get_class($controller) . '::' . $methodName . ' but admin_only mode is enabled',
                ['app' => 'downtranscoder']
            );
            throw new \Exception('This app is restricted to administrators only. Please contact your Nextcloud administrator.');
        }
    }

    /**
     * Handle exceptions from beforeController
     *
     * @param \OCP\AppFramework\Controller $controller
     * @param string $methodName
     * @param \Exception $exception
     * @return Response
     * @throws \Exception
     */
    public function afterException($controller, $methodName, \Exception $exception): Response {
        if (strpos($exception->getMessage(), 'restricted to administrators') !== false) {
            // Return appropriate error response
            $controllerClass = get_class($controller);

            // Check if this is an API controller (expects JSON response)
            if (strpos($controllerClass, 'ApiController') !== false) {
                return new JSONResponse(
                    ['error' => $exception->getMessage()],
                    Http::STATUS_FORBIDDEN
                );
            }

            // For page controller, return HTML error
            return new JSONResponse(
                ['error' => $exception->getMessage()],
                Http::STATUS_FORBIDDEN
            );
        }

        throw $exception;
    }
}
