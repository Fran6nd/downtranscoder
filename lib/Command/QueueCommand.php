<?php

declare(strict_types=1);

namespace OCA\DownTranscoder\Command;

use OCA\DownTranscoder\Service\MediaStateService;
use OCA\DownTranscoder\Db\MediaItemMapper;
use OCA\DownTranscoder\Util\MediaState;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class QueueCommand extends Command {
    private MediaStateService $stateService;
    private MediaItemMapper $mapper;
    private IUserManager $userManager;

    public function __construct(
        MediaStateService $stateService,
        MediaItemMapper $mapper,
        IUserManager $userManager
    ) {
        parent::__construct();
        $this->stateService = $stateService;
        $this->mapper = $mapper;
        $this->userManager = $userManager;
    }

    protected function configure(): void {
        $this
            ->setName('downtranscoder:queue')
            ->setDescription('Add media files to the transcode queue')
            ->addArgument(
                'file-id',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'One or more file IDs to add to the queue'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Queue all files in "' . MediaState::FOUND . '" state'
            )
            ->addOption(
                'user',
                'u',
                InputOption::VALUE_REQUIRED,
                'User ID (required for CLI context)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $fileIds = $input->getArgument('file-id');
        $queueAll = $input->getOption('all');
        $userId = $input->getOption('user');

        // User ID is required for CLI context
        if ($userId === null) {
            $output->writeln('<error>Error: --user option is required when running from CLI.</error>');
            $output->writeln('Usage: occ downtranscoder:queue --user USERNAME [file-id...]');
            $output->writeln('   or: occ downtranscoder:queue --user USERNAME --all');
            return Command::FAILURE;
        }

        // Validate user exists
        if (!$this->userManager->userExists($userId)) {
            $output->writeln(sprintf('<error>Error: User "%s" does not exist.</error>', $userId));
            return Command::FAILURE;
        }

        // Queue all found files
        if ($queueAll) {
            return $this->queueAllFoundFiles($output, $userId);
        }

        // Queue specific files
        if (empty($fileIds)) {
            $output->writeln('<comment>Please specify file IDs to queue or use --all to queue all found files.</comment>');
            $output->writeln('');
            $output->writeln('Usage:');
            $output->writeln('  occ downtranscoder:queue --user USERNAME <file-id> [<file-id>...]');
            $output->writeln('  occ downtranscoder:queue --user USERNAME --all');
            $output->writeln('');
            $output->writeln('Examples:');
            $output->writeln('  occ downtranscoder:queue --user admin 123 456 789');
            $output->writeln('  occ downtranscoder:queue --user admin --all');
            return Command::INVALID;
        }

        return $this->queueSpecificFiles($output, $fileIds, $userId);
    }

    /**
     * Queue all files in "found" state for the specified user
     *
     * @param OutputInterface $output Console output for user feedback
     * @param string $userId User ID to queue files for
     * @return int Command exit code (SUCCESS or FAILURE)
     */
    private function queueAllFoundFiles(OutputInterface $output, string $userId): int {
        $output->writeln('<info>Queueing all files in "' . MediaState::FOUND . '" state...</info>');
        $output->writeln('');

        // Get all items in found state
        $items = $this->mapper->findByState($userId, MediaState::FOUND);

        if (empty($items)) {
            $output->writeln('<comment>No files found in "' . MediaState::FOUND . '" state.</comment>');
            return Command::SUCCESS;
        }

        $queuedCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $this->stateService->updateMediaState($item->getId(), MediaState::QUEUED);
                $queuedCount++;
            } catch (\Exception $e) {
                $errors[] = sprintf('File ID %d: %s', $item->getFileId(), $e->getMessage());
            }
        }

        $output->writeln(sprintf('<info>✓ Successfully queued %d files</info>', $queuedCount));

        if (!empty($errors)) {
            $output->writeln('');
            $output->writeln('<error>Errors occurred:</error>');
            foreach ($errors as $error) {
                $output->writeln('  <error>' . $error . '</error>');
            }
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('Use <comment>occ downtranscoder:transcode --start</comment> to begin transcoding.');

        return Command::SUCCESS;
    }

    /**
     * Queue specific files by their file IDs
     *
     * Validates each file ID, looks up the corresponding media item,
     * and updates its state to queued. Displays a table with results.
     *
     * @param OutputInterface $output Console output for user feedback
     * @param array $fileIds Array of file IDs (strings) to queue
     * @param string $userId User ID to queue files for
     * @return int Command exit code (SUCCESS if all succeed, FAILURE if any errors)
     */
    private function queueSpecificFiles(OutputInterface $output, array $fileIds, string $userId): int {
        $output->writeln(sprintf('<info>Queueing %d file(s)...</info>', count($fileIds)));
        $output->writeln('');

        $table = new Table($output);
        $table->setHeaders(['File ID', 'Name', 'Status']);

        $queuedCount = 0;
        $errors = 0;

        foreach ($fileIds as $fileId) {
            try {
                // Validate file ID is numeric
                if (!is_numeric($fileId)) {
                    $table->addRow([
                        $fileId,
                        'N/A',
                        '<error>✗ Invalid file ID (not a number)</error>'
                    ]);
                    $errors++;
                    continue;
                }

                // Find the media item by file ID
                $item = $this->mapper->findByFileId((int)$fileId, $userId);

                // Update state to queued
                $this->stateService->updateMediaState($item->getId(), MediaState::QUEUED);

                $table->addRow([
                    $fileId,
                    $item->getName(),
                    '<info>✓ Queued</info>'
                ]);
                $queuedCount++;
            } catch (\Exception $e) {
                $table->addRow([
                    $fileId,
                    'N/A',
                    '<error>✗ ' . $e->getMessage() . '</error>'
                ]);
                $errors++;
            }
        }

        $table->render();

        $output->writeln('');
        $output->writeln(sprintf('<info>Successfully queued: %d</info>', $queuedCount));
        if ($errors > 0) {
            $output->writeln(sprintf('<error>Failed: %d</error>', $errors));
        }

        if ($queuedCount > 0) {
            $output->writeln('');
            $output->writeln('Use <comment>occ downtranscoder:transcode --start</comment> to begin transcoding.');
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
