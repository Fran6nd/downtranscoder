<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        // Load the main app JavaScript and CSS
        Util::addScript('downtranscoder', 'downtranscoder-main');
        Util::addStyle('downtranscoder', 'main');

        return new TemplateResponse(
            'downtranscoder',
            'main',
            []
        );
    }
}
