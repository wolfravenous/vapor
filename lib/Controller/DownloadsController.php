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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function getByStatus(string $status = 'active'): JSONResponse
    {
        try {
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
                $dbStatus = (int) ($record['status'] ?? 1);
                
                // Map status codes: 1=waiting, 2=active, 3=complete, 4=error
                $statusMap = [1 => 'waiting', 2 => 'active', 3 => 'complete', 4 => 'failed'];
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */

    /**
     * Cancel/Remove a download
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function cancelDownload(string $gid)
    {
        try {
            if (!$this->userId) {
                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            // Remove download via aria2
            $result = $this->aria2->remove($gid);
            
            if ($result === true || (is_array($result) && isset($result['result']) && $result['result'] === 'OK')) {
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download cancelled'
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
                $userFolder->getStorage()->getScanner()->scan($relativeFolder, true);
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
     * @NoAdminRequired
     * @NoCSRFRequired
     */
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
  }
