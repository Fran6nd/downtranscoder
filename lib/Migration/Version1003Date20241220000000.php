<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Adds abort_reason column to store error messages when transcoding fails
 */
class Version1003Date20241220000000 extends SimpleMigrationStep {
    private IDBConnection $connection;

    public function __construct(IDBConnection $connection) {
        $this->connection = $connection;
    }

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

            if (!$table->hasColumn('abort_reason')) {
                $table->addColumn('abort_reason', Types::TEXT, [
                    'notnull' => false,
                    'default' => null,
                    'comment' => 'Error message or reason when state is aborted'
                ]);
            }
        }

        return $schema;
    }

    /**
     * @param IOutput $output
     * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
     * @param array $options
     */
    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        // Update existing aborted items to have a default reason
        $qb = $this->connection->getQueryBuilder();
        $qb->update('downtranscoder_media')
            ->set('abort_reason', $qb->createNamedParameter('Transcoding failed (occurred before error tracking was added)'))
            ->where($qb->expr()->eq('state', $qb->createNamedParameter('aborted')))
            ->andWhere($qb->expr()->isNull('abort_reason'));

        $affectedRows = $qb->executeStatement();
        if ($affectedRows > 0) {
            $output->info("Updated {$affectedRows} existing aborted items with default reason");
        }
    }
}
