<?php

namespace OCA\Vapor\Api;

use OCA\Vapor\Tools\Helper;

class DownloadNormalizer
{
    /**
     * Normalize Aria2 download data to unified format
     */
    public static function normalizeAria2(array $aria2Data): array
    {
        $status = self::mapAria2Status($aria2Data['status'] ?? 'unknown');
        $totalLength = (int) ($aria2Data['totalLength'] ?? 0);
        $completedLength = (int) ($aria2Data['completedLength'] ?? 0);
        
        // Calculate progress percentage
        $progress = ($totalLength > 0) ? round(($completedLength / $totalLength) * 100) : 0;
        
        // Get filename from files array if available
        $filename = 'unknown';
        if (!empty($aria2Data['files']) && is_array($aria2Data['files'])) {
            $firstFile = reset($aria2Data['files']);
            if (isset($firstFile['path'])) {
                $filename = basename($firstFile['path']);
            }
        }
        
        return [
            'gid' => $aria2Data['gid'] ?? '',
            'filename' => $filename,
            'tool' => 'aria2',
            'status' => $status,
            'progress' => $progress,
            'totalSize' => $totalLength,
            'completedSize' => $completedLength,
            'speed' => (int) ($aria2Data['downloadSpeed'] ?? 0),
            'connections' => (int) ($aria2Data['connections'] ?? 0),
            'errorMessage' => $aria2Data['errorMessage'] ?? null,
            'timestamp' => time(),
        ];
    }

    /**
     * Normalize Ytdl download data to unified format
     */
    public static function normalizeYtdl(array $ytdlData): array
    {
        $status = $ytdlData['status'] ?? 'unknown';
        
        return [
            'gid' => $ytdlData['gid'] ?? '',
            'filename' => $ytdlData['filename'] ?? 'unknown',
            'tool' => 'ytdl',
            'status' => $status,
            'progress' => ($status === 'complete') ? 100 : 0,
            'totalSize' => null,
            'completedSize' => null,
            'speed' => null,
            'connections' => null,
            'errorMessage' => $ytdlData['errorMessage'] ?? null,
            'timestamp' => $ytdlData['timestamp'] ?? time(),
        ];
    }

    /**
     * Map Aria2 status to unified status enum
     */
    private static function mapAria2Status(string $aria2Status): string
    {
        $statusMap = [
            'active' => 'active',
            'waiting' => 'waiting',
            'paused' => 'paused',
            'error' => 'failed',
            'complete' => 'complete',
            'removed' => 'removed',
        ];
        
        return $statusMap[$aria2Status] ?? 'unknown';
    }

    /**
     * Map Ytdl status to unified status enum
     */
    public static function mapYtdlStatus(string $ytdlStatus): string
    {
        $statusMap = [
            'active' => 'active',
            'waiting' => 'waiting',
            'complete' => 'complete',
            'error' => 'failed',
            'paused' => 'paused',
        ];
        
        return $statusMap[$ytdlStatus] ?? 'unknown';
    }

    /**
     * Filter downloads by status
     */
    public static function filterByStatus(array $downloads, string $status): array
    {
        return array_filter($downloads, function($download) use ($status) {
            return $download['status'] === $status;
        });
    }
}
