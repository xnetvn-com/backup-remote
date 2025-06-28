<?php
// Handles backup compression and encryption
namespace App\Backup;

use App\Utils\Logger;
use App\Utils\Helper;
use Throwable;
use Psr\Log\LoggerInterface;
use UnifiedArchive\UnifiedArchive;

class ArchiveHandler
{
    private array $config;
    private LoggerInterface $logger;
    private string $tempDir;

    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Creates a compressed and optionally encrypted archive for a user.
     *
     * @param string $username The user to back up.
     * @param string $userPath The path to the user's home directory.
     * @param bool $isDryRun If true, simulates the process.
     * @return string|null The path to the created archive, or null on failure/dry-run.
     * @throws \Exception
     */
    public function create(string $username, string $userPath, bool $isDryRun): ?string
    {
        $tempDir = $this->config['local']['temp_dir'];
        $archivePath = Helper::createArchiveName($tempDir, $username);
        $this->logger->info("Preparing to create archive for user '{$username}' at '{$archivePath}'.");

        if ($isDryRun) {
            $this->logger->info("[DRY-RUN] Skipping archive creation for {$username}.");
            return "/tmp/{$username}-dry-run.tar.gz.enc"; // Return a dummy path for simulation
        }

        try {
            $this->logger->debug("Creating archive...");
            $archive = new UnifiedArchive($archivePath, $this->config['archive']['password']);
            $archive->addPath($userPath, null, $this->config['archive']['exclude'] ?? []);
            $this->logger->info("Archive for {$username} created successfully.");
            return $archivePath;
        } catch (Throwable $e) {
            throw new \Exception("Failed to create archive for {$username}: " . $e->getMessage(), 0, $e);
        }
    }
}
