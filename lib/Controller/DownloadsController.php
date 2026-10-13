<?php

namespace OCA\Vapor\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCA\Vapor\Api\DownloadNormalizer;
use OCA\Vapor\Aria2\Aria2;
use OCA\Vapor\Ytdl\Ytdl;
use OCA\Vapor\Db\Helper as DbHelper;
use OCA\Vapor\Tools\Helper;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

class DownloadsController extends Controller
{
    private $aria2;
    private $ytdl;
    private $dbHelper;
    private $userId;
    private $userSession;
    private $groupManager;

    public function __construct(
        string $appName,
        IRequest $request,
        Aria2 $aria2,
        Ytdl $ytdl,
        DbHelper $dbHelper,
        ?string $userId,
        IUserSession $userSession,
        IGroupManager $groupManager
    ) {
        parent::__construct($appName, $request);
        $this->aria2 = $aria2;
        $this->ytdl = $ytdl;
        $this->dbHelper = $dbHelper;
        $this->userId = $userId;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
    }

    /**
     * Get downloads by status (combined Aria2 + Ytdl)
     * 
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]

    public function getByStatus(string $status = 'active'): JSONResponse
    {
        try {
            // Before returning the Active list, sweep any ytdl downloads
            // whose detached yt-dlp process has finished. This is the only
            // mechanism that transitions async ytdl rows from ACTIVE to
            // COMPLETE/ERROR, since the spawn no longer blocks the HTTP
            // request. Running it here means the row is resolved before the
            // same request reads it back, so the response is consistent.
            if ($status === 'active') {
                $this->sweepYtdlDownloads();
            }

            $downloads = [];



            // Get Aria2 downloads from daemon
            $aria2Downloads = $this->getAria2ByStatus($status);
            foreach ($aria2Downloads as $download) {
                $downloads[] = DownloadNormalizer::normalizeAria2($download);
            }

            // Get Ytdl downloads from database
            $ytdlDownloads = $this->getYtdlByStatus($status);
            foreach ($ytdlDownloads as $download) {
                $downloads[] = DownloadNormalizer::normalizeYtdl($download);
            }

            return new JSONResponse([
                'status' => 'success',
                'count' => count($downloads),
                'downloads' => $downloads,
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all downloads (all statuses combined)
     * 
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getAll(): JSONResponse
    {
        try {
            $statuses = ['active', 'waiting', 'failed', 'complete'];
            $allDownloads = [];

            foreach ($statuses as $status) {
                $response = $this->getByStatus($status);
                $data = json_decode($response->render(), true);
                if (isset($data['downloads'])) {
                    $allDownloads = array_merge($allDownloads, $data['downloads']);
                }
            }

            return new JSONResponse([
                'status' => 'success',
                'count' => count($allDownloads),
                'downloads' => $allDownloads,
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get counters for each status
     * 
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getCounters(): JSONResponse
    {
        try {
            $counters = [
                'active' => 0,
                'waiting' => 0,
                'failed' => 0,
                'complete' => 0,
            ];

            foreach ($counters as $status => &$count) {
                $response = $this->getByStatus($status);
                $data = json_decode($response->render(), true);
                $count = $data['count'] ?? 0;
            }

            return new JSONResponse([
                'status' => 'success',
                'counters' => $counters,
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Private helper: Get Aria2 downloads by status
     */
    private function getAria2ByStatus(string $status): array
    {
        try {
            switch ($status) {
                case 'active':
                    return $this->aria2->tellActive([]) ?? [];
                case 'waiting':
                    return $this->aria2->tellWaiting([0, 999]) ?? [];
                case 'failed':
                    return $this->aria2->tellFail([0, 999]) ?? [];
                case 'complete':
                    return $this->aria2->tellStopped([0, 999]) ?? [];
                default:
                    return [];
            }
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Private helper: Get Ytdl downloads by status from database
     */
    private function getYtdlByStatus(string $status): array
    {
        try {
            if (!$this->userId) {
                return [];
            }

            // Get all Ytdl downloads for this user from database
            $records = $this->dbHelper->getYtdlByUid($this->userId) ?? [];
            
            // Map database status codes to string status
            $downloads = [];
            foreach ($records as $record) {
               


                $dbStatus = (int) ($record['status'] ?? Helper::STATUS['ACTIVE']);

                // Map DB status codes using the authoritative constant map
                // from OCA\Vapor\Tools\Helper::STATUS.
                // ACTIVE=1, PAUSED=2, COMPLETE=3, WAITING=4, ERROR=5
                $statusMap = [
                    Helper::STATUS['ACTIVE']   => 'active',
                    Helper::STATUS['PAUSED']   => 'paused',
                    Helper::STATUS['COMPLETE'] => 'complete',
                    Helper::STATUS['WAITING']  => 'waiting',
                    Helper::STATUS['ERROR']    => 'failed',
                ];
                $mappedStatus = $statusMap[$dbStatus] ?? 'unknown';


 
                if ($mappedStatus === $status) {
                    $downloads[] = [
                        'gid' => $record['gid'] ?? '',
                        'filename' => $record['filename'] ?? 'unknown',
                        'status' => $mappedStatus,
                        'errorMessage' => null,
                        'timestamp' => (int) ($record['timestamp'] ?? time()),
                    ];
                }
            }

            return $downloads;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Start Aria2 daemon
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function startAria2()
    {
        try {
            if (!$this->isAdmin()) {
                return new JSONResponse(
                    ['error' => 'Only admins can control aria2'],
                    \OCP\AppFramework\Http::STATUS_FORBIDDEN
                );
            }

            $this->aria2->start();
            
            return new JSONResponse([
                'status' => 'success',
                'running' => true,
                'message' => 'Aria2 started'
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Stop Aria2 daemon
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function stopAria2()
    {
        try {
            if (!$this->isAdmin()) {
                return new JSONResponse(
                    ['error' => 'Only admins can control aria2'],
                    \OCP\AppFramework\Http::STATUS_FORBIDDEN
                );
            }

            $this->aria2->stop();
            
            return new JSONResponse([
                'status' => 'success',
                'running' => false,
                'message' => 'Aria2 stopped'
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get Aria2 daemon status
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function getAria2Status()
    {
        try {
            $isRunning = $this->aria2->isRunning();
            
            return new JSONResponse([
                'status' => 'success',
                'running' => $isRunning
            ]);
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Helper: Check if current user is admin
     */
    private function isAdmin(): bool
    {
        $user = $this->userSession->getUser();
        if (!$user) {
            return false;
        }
        return $this->groupManager->isAdmin($user->getUID());
    }




    /**
     * Cancel/Remove a download
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function cancelDownload(string $gid)
    {
        try {
            if (!$this->userId) {
                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            $row = $this->dbHelper->getByGid($gid);
            if ($row && ($row['uid'] ?? null) !== $this->userId) {
                return new JSONResponse(
                    ['error' => 'Not authorized to cancel this download'],
                    \OCP\AppFramework\Http::STATUS_FORBIDDEN
                );
            }
            $type = $row ? (int) ($row['type'] ?? 0) : 0;

            // ytdl downloads are not managed by aria2; cancel them by
            // clearing the DB row. (Killing the actual yt-dlp process is
            // a follow-up; see comment in the code.)
            if ($type === Helper::DOWNLOADTYPE['YOUTUBE-DL']) {
                $this->dbHelper->deleteByGid($gid);
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download cancelled',
                ]);
            }

            // aria2 download
            $result = $this->aria2->remove($gid);
            if ($result === true || (is_array($result) && isset($result['result']) && $result['result'] === 'OK')) {
                $this->dbHelper->deleteByGid($gid);
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download cancelled',
                ]);
            }

            return new JSONResponse(
                ['error' => 'Failed to cancel download: ' . json_encode($result)],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => 'Exception: ' . $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }








    /**
     * Delete a completed download: removes the file from the user's configured
     * download location, rescans that folder, cleans up DB + aria2 result
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function deleteDownload(string $gid)
    {
        $logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);
        try {
            if (!$this->userId) {
                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            $row = $this->dbHelper->getByGid($gid);
            if ($row && ($row['uid'] ?? null) !== $this->userId) {
                return new JSONResponse(
                    ['error' => 'Not authorized to delete this download'],
                    \OCP\AppFramework\Http::STATUS_FORBIDDEN
                );
            }

            // The user's chosen download location — SAME source the daemon uses
            $realDownloadDir = Helper::getLocalFolder(Helper::getDownloadDir());

            // Candidate absolute paths, best first:
            // 1) DB record: filename + user's download dir (covers ytdl too)
            // 2) aria2's own metadata (covers torrents / renamed files / missing DB rows)
            $paths = [];
            if ($row && !empty($row['filename']) && $row['filename'] !== 'unknown') {
                $paths[] = rtrim($realDownloadDir, '/') . '/' . $row['filename'];
            }
            foreach ($this->getAria2Paths($gid) as $p) {
                $paths[] = $p;
            }
            $paths = array_values(array_unique(array_filter($paths)));
            $logger->warning('VAPOR DELETE: gid=' . $gid . ' | realDownloadDir=' . $realDownloadDir
                . ' | candidates=' . json_encode($paths));
            

$root = \OC::$server->get(\OCP\Files\IRootFolder::class);
$userFolder = $root->getUserFolder($this->userId);
$fileDeleted = false;

// Real on-disk root of this user's files (e.g. /var/www/nextcloud/data/steve/files).
// This is authoritative — unlike $userFolder->getPath(), which returns the
// logical path (/steve/files) and cannot be compared to filesystem paths.
$storage = $userFolder->getStorage();
$realBase = $storage->getLocalFile('');
if ($realBase === false || $realBase === null || $realBase === '') {
    $logger->warning('VAPOR DELETE: storage has no local base path; aborting file removal');
    $realBase = null;
} else {
    $realBase = rtrim($realBase, '/').'/files';   //<-- append /files
    $logger->warning('VAPOR DELETE: realBase=' . $realBase);
}

foreach ($paths as $filePath) {
    $logger->warning('VAPOR DELETE: checking path: ' . $filePath
        . ' | exists=' . (file_exists($filePath) ? 'YES' : 'NO'));

    if ($realBase === null || !file_exists($filePath)) {
        continue;
    }

    $fileReal = realpath($filePath);
    if ($fileReal === false) {
        $logger->warning('VAPOR DELETE: realpath failed for ' . $filePath);
        continue;
    }

    // Must live strictly inside the user's files root — never touch anything else.
    if (strpos($fileReal, $realBase . DIRECTORY_SEPARATOR) !== 0) {
        $logger->warning('VAPOR DELETE: refusing, outside user folder: ' . $fileReal);
        continue;
    }

    $relativePath = ltrim(substr($fileReal, strlen($realBase)), DIRECTORY_SEPARATOR);
    $logger->warning('VAPOR DELETE: deleting relative=' . $relativePath
        . ' real=' . $fileReal);

    try {
        $userFolder->get($relativePath)->delete();  // disk + filecache, atomically
        $fileDeleted = true;
    } catch (\OCP\Files\NotFoundException $e) {
        // Not in the filecache (e.g. filename on disk != cache entry).
        // Delete from disk; the rescan below will purge the cache.
        if (@unlink($fileReal)) {
            $fileDeleted = true;
        } else {
            $logger->warning('VAPOR DELETE: unlink failed for ' . $fileReal);
        }
    } catch (\Throwable $e) {
        $logger->warning('VAPOR DELETE: node delete threw: ' . $e->getMessage()
            . ' — falling back to unlink');
        if (@unlink($fileReal)) {
            $fileDeleted = true;
        }
    }
}




            // Rescan the download folder so NC Files drops the entry even in
            // the unlikely event the delete path above missed the cache


try {
    if ($realBase !== null) {
        $realDir = realpath($realDownloadDir);
        if ($realDir !== false && strpos($realDir, $realBase . DIRECTORY_SEPARATOR) === 0) {
            $relativeFolder = ltrim(substr($realDir, strlen($realBase)), DIRECTORY_SEPARATOR);
            if ($relativeFolder !== '') {
                $userFolder->getStorage()->getScanner()->scan($relativeFolder, \OC\Files\Cache\Scanner::SCAN_RECURSIVE);
                $logger->warning('VAPOR DELETE: rescanned folder=' . $relativeFolder);
            }
        } else {
            $logger->warning('VAPOR DELETE: folder not under user base, skipping rescan');
        }
    }
} catch (\Exception $e) {
    $logger->warning('VAPOR DELETE: folder rescan failed: ' . $e->getMessage());
}


            $this->dbHelper->deleteByGid($gid);
            $this->aria2->removeDownloadResult($gid);

            return new JSONResponse([
                'status' => 'success',
                'fileDeleted' => $fileDeleted,
                'message' => $fileDeleted
                    ? 'Download and file deleted'
                    : 'Download removed from list (no file found on disk)',
            ]);
        } catch (\Exception $e) {
            $logger->warning('VAPOR DELETE: EXCEPTION ' . $e->getMessage());
            return new JSONResponse(
                ['error' => 'Exception: ' . $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Absolute file path(s) reported by aria2 for a gid
     */
    private function getAria2Paths(string $gid): array
    {
        $paths = [];
        try {
            $status = $this->aria2->tellStatus($gid);
            $info = is_array($status) ? reset($status) : [];
            $dir = $info['dir'] ?? '';
            foreach (($info['files'] ?? []) as $file) {
                $p = $file['path'] ?? '';
                if ($p === '') {
                    continue;
                }
                if ($p[0] !== '/' && $dir !== '') {
                    $p = rtrim($dir, '/') . '/' . ltrim($p, '/');
                }
                $paths[] = $p;
            }
        } catch (\Exception $e) {
            // not an aria2 gid (e.g. ytdl)
        }
        return $paths;
    }

    /**
     * Retry a failed download (Ytdl only)
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function retryDownload(string $gid)
    {
        try {
            if (!$this->userId) {
                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            // Retry ytdl download - call the Ytdl Redownload method
            $result = $this->ytdl->redownload($gid);
            
            if ($result) {
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download retry started'
                ]);
            }

            return new JSONResponse(
                ['error' => 'Failed to retry download'],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => 'Exception: ' . $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Pause a download
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function pauseDownload(string $gid)
    {
        try {
            if (!$this->userId) {
                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            $result = $this->aria2->pause($gid);
            
            if ($result === true || (is_array($result) && isset($result['result']) && $result['result'] === 'OK')) {
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download paused'
                ]);
            }

            return new JSONResponse(
                ['error' => 'Failed to pause download: ' . json_encode($result)],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => 'Exception: ' . $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Resume a paused download
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function resumeDownload(string $gid)
    {
        try {
            if (!$this->userId) {
                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            $result = $this->aria2->unpause($gid);
            
            if ($result === true || (is_array($result) && isset($result['result']) && $result['result'] === 'OK')) {
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download resumed'
                ]);
            }

            return new JSONResponse(
                ['error' => 'Failed to resume download: ' . json_encode($result)],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => 'Exception: ' . $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }


    // =====================================================================
    // Async ytdl sweep
    // =====================================================================

    /**
     * For every ACTIVE ytdl row belonging to the current user, check whether
     * the detached yt-dlp process (whose PID is stored in the row's data
     * column) is still running. If it has exited, read its log file and
     * transition the row to COMPLETE or ERROR accordingly, then delete the
     * log file.
     *
     * Called at the top of getByStatus('active'), which runs every ~3
     * seconds while the Vapor app is open. This is the sole mechanism that
     * un-sticks async ytdl rows.
     */
    private function sweepYtdlDownloads(): void
    {
        if (!$this->userId) {
            return;
        }

        try {
            $rows = $this->dbHelper->getYtdlByUid($this->userId) ?? [];
        } catch (\Throwable $e) {
            \OC::$server->get(\Psr\Log\LoggerInterface::class)->warning(
                'VAPOR SWEEP: failed to load rows: ' . $e->getMessage()
            );
            return;
        }

        foreach ($rows as $row) {
            try {
                $this->sweepOneYtdlRow($row);
            } catch (\Throwable $e) {
                // One bad row must not stop the sweep for the others.
                \OC::$server->get(\Psr\Log\LoggerInterface::class)->warning(
                    'VAPOR SWEEP: row threw: ' . $e->getMessage()
                    . ' gid=' . ($row['gid'] ?? '?')
                );
            }
        }
    }

    /**
     * Process a single ytdl row: skip if not ACTIVE, skip if the process is
     * still alive, otherwise read the log and resolve the row.
     */
    private function sweepOneYtdlRow(array $row): void
    {
        $logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);

        $status = (int) ($row['status'] ?? 0);
        if ($status !== Helper::STATUS['ACTIVE']) {
            return;
        }

        $gid = $row['gid'] ?? '';
        if ($gid === '') {
            return;
        }

        // Decode the row's data column to get pid + log
        $data = [];
        if (!empty($row['data'])) {
            $decoded = $this->dbHelper->getExtra($row['data']);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $pid = isset($data['pid']) ? (int) $data['pid'] : 0;
        $log = $data['log'] ?? null;

        // Pre-async row: no pid stored. Skip — cleanup of these is a
        // separate concern (see commit note).
        if ($pid <= 0 || $log === null) {
            return;
        }

        // Still running: leave it alone.
        if (Helper::isRunning($pid)) {
            return;
        }

        // Process is gone. Read the log and resolve.
        $logger->warning(
            'VAPOR SWEEP: pid=' . $pid . ' dead, parsing log gid=' . $gid
            . ' log=' . $log
        );

        $outcome = $this->readYtdlOutcome($log);

        if ($outcome['state'] === 'complete' && !empty($outcome['file'])) {
            $this->dbHelper->updateFilename($gid, $outcome['file']);
            $this->dbHelper->updateStatus($gid, Helper::STATUS['COMPLETE']);

            $path = $data['path'] ?? Helper::getDownloadDir();
            $this->rescanYtdlFile($path, $outcome['file']);

            $logger->warning(
                'VAPOR SWEEP: gid=' . $gid . ' resolved COMPLETE file=' . $outcome['file']
            );
        } else {
            $this->dbHelper->updateStatus($gid, Helper::STATUS['ERROR']);

            $logger->warning(
                'VAPOR SWEEP: gid=' . $gid . ' resolved ERROR: '
                . ($outcome['error'] ?? 'unknown')
            );
        }

        // Delete the log file now that we've read it.
        if (is_file($log)) {
            @unlink($log);
        }
    }

    /**
     * Read a yt-dlp log file and decide the outcome.
     *
     * Returns one of:
     *   ['state' => 'complete', 'file' => <basename>, 'error' => null]
     *   ['state' => 'error',    'file' => null,       'error' => <reason>]
     *
     * Rules:
     *   - If any line starts with "ERROR:", treat as failure (even if a
     *     Destination line is also present — partial downloads with
     *     --ignore-errors should not be reported as success).
     *   - Otherwise, the last "[download] Destination:" or
     *     "[ExtractAudio] Destination:" line gives the file basename.
     *   - If neither, treat as failure with a diagnostic message.
     */
    private function readYtdlOutcome(string $logFile): array
    {
        if (!is_file($logFile) || !is_readable($logFile)) {
            return [
                'state' => 'error',
                'file' => null,
                'error' => 'log file missing or unreadable: ' . $logFile,
            ];
        }

        $content = @file_get_contents($logFile);
        if ($content === false) {
            return [
                'state' => 'error',
                'file' => null,
                'error' => 'failed to read log file: ' . $logFile,
            ];
        }

        // yt-dlp writes errors to stderr, which is redirected into the same
        // log file. Its error lines all begin with "ERROR:".
        $errorLines = [];
        foreach (preg_split('/\R/', $content) as $line) {
            if (strpos($line, 'ERROR:') === 0) {
                $errorLines[] = trim($line);
            }
        }

        if (!empty($errorLines)) {
            return [
                'state' => 'error',
                'file' => null,
                'error' => end($errorLines),
            ];
        }

        // No error: find the last Destination line.
        // Matches:
        //   [download] Destination: /path/to/file.ext
        //   [ExtractAudio] Destination: /path/to/file.ext
        //   [ffmpeg] Destination: /path/to/file.ext  (defensive)
        $lastDest = null;
        if (preg_match_all(
            '/\[(?:download|ExtractAudio|ffmpeg)\]\s+Destination:\s+(.+)$/m',
            $content,
            $m
        )) {
            $lastDest = end($m[1]);
        }

        if ($lastDest === null) {
            return [
                'state' => 'error',
                'file' => null,
                'error' => 'no ERROR and no Destination line in log',
            ];
        }

        return [
            'state' => 'complete',
            'file' => basename(trim($lastDest)),
            'error' => null,
        ];
    }

    /**
     * Tell Nextcloud's scanner to register a newly downloaded file so it
     * appears in the Files app and its filecache entry is created.
     *
     * $dir  is the NC-relative directory (e.g. "/Music/Fresh Pulls")
     * $file is the basename (e.g. "Sabrina Carpenter - Manchild.opus")
     */
    private function rescanYtdlFile(string $dir, string $file): void
    {
        if ($dir === '' || $file === '') {
            return;
        }
        $relativeDir = ltrim($dir, '/');
        $relativePath = $relativeDir . '/' . $file;
        $scanTarget = 'files/' . $relativePath;

        $logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);

        // The scanner can collide with a concurrent NC scan (Files app
        // navigating the folder, dav sync, etc.) and fail with a transient
        // lock error. Retry once after a short delay before treating it as
        // a real failure.
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $rootFolder = \OC::$server->get(\OCP\Files\IRootFolder::class);
                $userFolder = $rootFolder->getUserFolder($this->userId);
                $userFolder->getStorage()->getScanner()->scan($scanTarget, true);
                return;
            } catch (\Throwable $e) {
                if ($attempt === 2) {
                    $logger->warning(
                        'VAPOR SWEEP: rescan failed (2 attempts) for '
                        . $relativePath . ': ' . $e->getMessage()
                    );
                } else {
                    usleep(500000); // 500ms
                }
            }
        }
    }


}
