<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds transcode_preset column for per-item preset overrides
 */
class Version1001Date20241220000000 extends SimpleMigrationStep {
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

            if (!$table->hasColumn('transcode_preset')) {
                $table->addColumn('transcode_preset', Types::STRING, [
                    'notnull' => false,
                    'length' => 50,
                    'default' => null,
                ]);
            }

            return $schema;
        }

        return null;
    }
}
