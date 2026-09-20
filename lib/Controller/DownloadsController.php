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

class DownloadsController extends Controller
{
    private $aria2;
    private $ytdl;
    private $dbHelper;
    private $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        Aria2 $aria2,
        Ytdl $ytdl,
        DbHelper $dbHelper,
        ?string $userId
    ) {
        parent::__construct($appName, $request);
        $this->aria2 = $aria2;
        $this->ytdl = $ytdl;
        $this->dbHelper = $dbHelper;
        $this->userId = $userId;
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

            $this->aria2->startDaemon();
            
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

            $this->aria2->stopDaemon();
            
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
        $user = \OC::$server->getUserSession()->getUser();
        if (!$user) {
            return false;
        }
        return \OC::$server->getGroupManager()->isAdmin($user->getUID());
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
     * Delete a completed download
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function deleteDownload(string $gid)
    {
        try {
            if (!$this->userId) {
                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            // Remove from aria2 results
            $result = $this->aria2->removeDownloadResult($gid);
            
            if ($result === true || (is_array($result) && isset($result['result']) && $result['result'] === 'OK')) {
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download deleted'
                ]);
            }

            return new JSONResponse(
                ['error' => 'Failed to delete download: ' . json_encode($result)],
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
            if (!$this->userId) {

                return new JSONResponse(
                    ['error' => 'User not authenticated'],
                    \OCP\AppFramework\Http::STATUS_UNAUTHORIZED
                );
            }

            $result = $this->aria2->unpause($gid);
            if ($result && isset($result['result'])) {
                return new JSONResponse([
                    'status' => 'success',
                    'message' => 'Download resumed'
                ]);
            }

            return new JSONResponse(
                ['error' => 'Failed to resume download'],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        } catch (\Exception $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                \OCP\AppFramework\Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }
    }
}


