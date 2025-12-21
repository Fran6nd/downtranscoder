<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\AppInfo;

use OCA\DownTranscoder\Middleware\AdminOnlyMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
    public const APP_ID = 'downtranscoder';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        // Register the admin-only middleware
        $context->registerMiddleware(AdminOnlyMiddleware::class);
    }

    public function boot(IBootContext $context): void {
        // Boot logic here if needed
    }
}
