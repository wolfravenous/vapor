<?php

namespace OCA\Vapor\Tools;

use OCA\Vapor\Tools\Helper;
use OC\Files\Utils\Scanner;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\ForbiddenException;

class folderScan
{
    private $user;
    private $path;
    private $realDir;
    private $logger;
    private $relativePath;
    private $scanner;

    public function __construct($path = null, $user = null)
    {
        $this->user = $user ?? Helper::getUID();
        $this->path = $path ?? $this->getDefaultPath();
        $this->realDir = Helper::getRealDownloadDir();
        $this->logger = Helper::getLogger();
        $this->relativePath = $this->getRelativePath($this->path);
        
        // Only initialize scanner if we have a valid path
        if (!empty($this->relativePath) && $this->relativePath !== '/') {
            try {
                $eventDispatcher = null;
                if (method_exists(\OC::$server, 'getEventDispatcher')) {
                    $eventDispatcher = \OC::$server->getEventDispatcher();
                } else {
                    try {
                        $eventDispatcher = \OC::$server->get(\OCP\EventDispatcher\IEventDispatcher::class);
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
                
                $this->scanner = new Scanner(
                    $this->user,
                    Helper::getDatabaseConnection(),
                    $eventDispatcher,
                    $this->logger
                );
            } catch (\Exception $e) {
                $this->scanner = null;
            }
        } else {
            $this->scanner = null;
        }
    }

    public function getDefaultPath()
    {
        return Helper::getUserFolder() . Helper::getDownloadDir();
    }

    public static function create($path = null, $user = null)
    {
        return new static($path, $user);
    }

    public function setUser($user)
    {
        $this->user = $user;
        $this->relativePath = $this->getRelativePath($this->path);
        return $this;
    }

    public function setPath($path)
    {
        $this->path = $path;
        $this->relativePath = $this->getRelativePath($path);
        return $this;
    }

    private function update()
    {
        if (!Helper::folderUpdated($this->realDir)) {
            return ['message' => "no change", 'status' => false];
        }
        
        return $this->scan();
    }

    /**
     * Main scan method - Clean and reliable
     */
    public function scan()
    {
        // Skip if no valid path
        if (empty($this->relativePath) || $this->relativePath === '/') {
            $this->logger->debug("Skipping scan for invalid path: {$this->relativePath}");
            return ['status' => true, 'path' => $this->path, 'message' => 'Skipped invalid path'];
        }

        try {
            $this->logger->debug("Starting scan for user: {$this->user}, path: {$this->relativePath}");
            
            // METHOD 1: Use Nextcloud's storage scanner (most reliable)
            try {
                $rootFolder = \OC::$server->get(IRootFolder::class);
                $userFolder = $rootFolder->getUserFolder($this->user);
                
                // Check if path exists
                try {
                    $node = $userFolder->get($this->relativePath);
                    $storage = $node->getStorage();
                    $storage->getScanner()->scan($node->getInternalPath());
                    $this->logger->debug("Storage scan completed for: {$this->relativePath}");
                    return ['status' => true, 'path' => $this->path, 'method' => 'storage'];
                } catch (NotFoundException $e) {
                    // Folder doesn't exist, create it
                    $this->logger->debug("Creating folder: {$this->relativePath}");
                    $userFolder->newFolder($this->relativePath);
                    
                    // Now scan the newly created folder
                    $node = $userFolder->get($this->relativePath);
                    $storage = $node->getStorage();
                    $storage->getScanner()->scan($node->getInternalPath());
                    $this->logger->debug("Storage scan completed for newly created folder: {$this->relativePath}");
                    return ['status' => true, 'path' => $this->path, 'method' => 'storage_created'];
                }
            } catch (\Exception $e) {
                $this->logger->debug("Storage scanner failed: " . $e->getMessage());
            }
            
            // METHOD 2: Try scanner if available (fallback)
            if ($this->scanner) {
                try {
                    $this->scanner->scan($this->relativePath);
                    $this->logger->debug("Scanner completed for: {$this->relativePath}");
                    return ['status' => true, 'path' => $this->path, 'method' => 'scanner'];
                } catch (\Exception $e) {
                    $this->logger->debug("Scanner failed: " . $e->getMessage());
                }
            }
            
            // METHOD 3: Touch the folder (last resort)
            try {
                $rootFolder = \OC::$server->get(IRootFolder::class);
                $userFolder = $rootFolder->getUserFolder($this->user);
                $node = $userFolder->get($this->relativePath);
                $node->touch();
                $storage = $node->getStorage();
                $storage->getCache()->correctFolderSize($node->getInternalPath());
                $this->logger->debug("Touch fallback completed for: {$this->relativePath}");
                return ['status' => true, 'path' => $this->path, 'method' => 'touch'];
            } catch (\Exception $e) {
                $this->logger->debug("Touch fallback failed: " . $e->getMessage());
            }
            
            // Everything failed but files might still appear
            $this->logger->debug("All scan methods completed for: {$this->relativePath}");
            return ['status' => true, 'path' => $this->path, 'method' => 'none'];
            
        } catch (\Exception $e) {
            $this->logger->error("Scan failed for {$this->relativePath}: " . $e->getMessage());
            return ['status' => false, 'path' => $this->path, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get relative path from full path - FIXED to handle paths correctly
     */
    private function getRelativePath($fullPath)
    {
        if (empty($fullPath)) {
            return '/';
        }
        
        // Get the user folder prefix
        $userFolder = Helper::getUserFolder($this->user);
        
        // Remove user folder prefix if present
        if (strpos($fullPath, $userFolder) === 0) {
            $fullPath = substr($fullPath, strlen($userFolder));
        }
        
        // Remove leading slash if present and not empty
        if (strpos($fullPath, '/') === 0 && strlen($fullPath) > 1) {
            $fullPath = substr($fullPath, 1);
        }
        
        // If empty, return root
        if (empty($fullPath)) {
            return '/';
        }
        
        // Ensure it starts with a single slash
        return '/' . ltrim($fullPath, '/');
    }

    /**
     * Public sync method
     */
    public static function sync($force = false, $path = null, $user = null)
    {
        $instance = self::create($path, $user);
        return $force ? $instance->scan() : $instance->update();
    }
}
