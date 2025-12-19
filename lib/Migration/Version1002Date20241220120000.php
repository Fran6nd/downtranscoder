<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds support for 'transcoding' state (no schema changes needed, just documentation)
 * Valid states: 'found', 'queued', 'transcoding', 'transcoded', 'aborted', 'discarded'
 */
class Version1002Date20241220120000 extends SimpleMigrationStep {
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        // No schema changes needed - the state column already supports the 'transcoding' value
        // This migration is just for versioning and documentation purposes
        return null;
    }
}
