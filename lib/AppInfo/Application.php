<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\AppInfo;

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
        // Register navigation entry for the main kanban board page
        $context->registerNavigationEntry(function () {
            return [
                'id' => self::APP_ID,
                'order' => 10,
                'href' => \OC::$server->getURLGenerator()->linkToRoute('downtranscoder.page.index'),
                'icon' => \OC::$server->getURLGenerator()->imagePath(self::APP_ID, 'app.svg'),
                'name' => 'DownTranscoder',
            ];
        });
    }

    public function boot(IBootContext $context): void {
        // Boot logic here if needed
    }
}
