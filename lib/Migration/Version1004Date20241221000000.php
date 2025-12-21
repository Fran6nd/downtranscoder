<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds transcode_progress column to track real-time transcoding progress
 */
class Version1004Date20241221000000 extends SimpleMigrationStep {
    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     * @return null|ISchemaWrapper
     */
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('downtranscoder_media')) {
            $table = $schema->getTable('downtranscoder_media');

            if (!$table->hasColumn('transcode_progress')) {
                $table->addColumn('transcode_progress', Types::INTEGER, [
                    'notnull' => false,
                    'default' => null,
                    'comment' => 'Progress percentage (0-100) when state is transcoding'
                ]);
            }
        }

        return $schema;
    }
}
