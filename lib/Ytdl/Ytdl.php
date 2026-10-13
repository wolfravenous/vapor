<?php

namespace OCA\Vapor\Ytdl;

use OCA\Vapor\Tools\Helper;
use OCA\Vapor\Ytdl\Helper as YtdHelper;
use Symfony\Component\Process\Process;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use OCP\IL10N;

class Ytdl
{
    public $audioOnly = 0;
    public $audioFormat = 'm4a', $videoFormat = null;
    //path in nextcloud fs
    public $dbDlPath = null;
    private $format = 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best';
    private $options = [];
    private $downloadDir;
    private $timeout = 60 * 60 * 10; //10 hours
    private $outTpl = "%(title).32s.%(ext)s";
    private $defaultDir = "/tmp/downloads";
    private $env = [];
    private $bin;
    private $cmd;
    public $helper;
    private $l10n;

    public function __construct(array $options, IL10N $l10n)
    {
        $this->l10n = $l10n;
        $options += ['downloadDir' => '/tmp/downloads', 'settings' => []];
        $this->init($options);
    }
    public function init(array $options)
    {
        extract($options);
        if (!empty($binary)) {
            $this->bin = $binary;
        } else {
            $this->bin = __DIR__ . "/../../bin/yt-dlp"; //Helper::findBinaryPath('ytdl', __DIR__ . "/../../bin/yt-dlp");
        }
        if ($this->isInstalled() && !$this->isExecutable()) {
            chmod($this->bin, 0744);
        }
        $this->setDownloadDir($downloadDir);
        if (!empty($settings)) {
            foreach ($settings as $key => $value) {
                if (empty($value)) {
                    $this->addOption($key, true);
                } else {
                    $this->setOption($key, $value, true);
                }
            }
        }
        if (empty($lang = getenv('LANG')) || strpos(strtolower($lang), 'c.utf-8') === false) {
            $lang = 'C.UTF-8';
        }
        $this->setEnv('LANG', $lang);
        $this->addOption("--no-mtime");
	$this->addOption('--ignore-errors');

        if (($index = $this->hasOption('--output')) !== false) {
            $this->outTpl = $this->options[$index + 1];
            unset($this->options[$index]);
            unset($this->options[$index + 1]);
        }
    }

    public function setEnv($key, $val)
    {
        $this->env[$key] = $val;
    }

    public function audioMode()
    {
        if (Helper::ffmpegInstalled()) {
            // $this->addOption('--prefer-ffmpeg'); // (Deprecated)
            // $this->addOption('--add-metadata');
            // $this->setOption('--metadata-from-title', "%(artist)s-%(title).64s");
            $this->addOption('--extract-audio');
            $this->addOption('-x');
        } else {
            $this->audioFormat = "m4a";
        }
        /*$pos = strrpos($this->outTpl, '.');
        $this->outTpl = substr($this->outTpl, 0, $pos) . "." . $this->audioFormat;
        $this->outTpl = "/%(id)s-%(title)s.m4a";*/
        $this->setAudioFormat($this->audioFormat);
        return $this;
    }

    public function setAudioQuality($value = 0)
    {
        $this->setOption('--audio-quality', $value);
    }

    public function setAudioFormat($format)
    {
        $this->setOption('--audio-format', $format);
    }

    public function setVideoFormat($format)
    {
        //$this->videoFormat = $format;
        $this->setOption('--recode-video', $format);
    }

    public function GetUrlOnly()
    {
        $this->addOption('--get-filename');
        $this->addOption('--get-url');
        return $this;
    }

    public static function create($options)
    {
        return new self($options);
    }

    public function setDownloadDir($dir)
    {
        $this->downloadDir = rtrim($dir, '/');
    }

    public function getDownloadDir()
    {
        return $this->downloadDir;
    }

    public function prependOption(string $option)
    {
        array_unshift($this->options, $option);
    }



    public function download($url, string $gid = '')
    {
        if ($this->audioOnly) {
            $this->audioMode();
        } else {
            if (Helper::ffmpegInstalled() && $this->videoFormat) {
                $this->setOption('--format', 'bestvideo+bestaudio/best');
                $this->setVideoFormat($this->videoFormat);
            } else {
                $this->setOption('--format', $this->format);
            }
        }
        $this->helper = YtdHelper::create();

        $this->setOption("--output", $this->downloadDir . "/" . $this->outTpl);
        $this->setUrl($url);
        $this->addOption('--no-playlist');
        $this->prependOption($this->bin);

        // Async spawn: yt-dlp runs detached, writing stdout+stderr to a
        // per-gid log file. The request returns immediately; a watcher
        // (added in a later commit) reads the log to determine outcome.
        $logDir = \OC::$server->get(\OCP\IConfig::class)->getSystemValue('datadirectory', '/var/www/nextcloud/data')
            . '/vapor-ytdl-logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0770, true);
        }
        $logFile = $logDir . '/' . ($gid !== '' ? $gid : uniqid('ytdl-', true)) . '.log';

        // Symfony's Process doesn't redirect to files natively, so wrap
        // the invocation in `/bin/sh -c "... > log 2>&1"`.
        $shellCmd = escapeshellarg($this->bin);
        foreach (array_slice($this->options, 1) as $opt) {
            $shellCmd .= ' ' . escapeshellarg($opt);
        }
        $shellCmd .= ' > ' . escapeshellarg($logFile) . ' 2>&1';

        $process = new Process(['/bin/sh', '-c', $shellCmd], null, $this->env);
        $process->setTimeout($this->timeout);
        $process->setOptions(['create_new_console' => true]);
        $process->disableOutput();
        $process->start();

        $pid = $process->getPid();



        // Persist PID, log path, and download dir in the DB row's data
        // column so the sweep can find them. Storing the path here means
        // the sweep scans the directory the user had configured at spawn
        // time, even if they later change the setting mid-download.
        if ($gid !== '') {
            (new \OCA\Vapor\Db\Helper())->mergeData($gid, [
                'pid' => $pid,
                'log' => $logFile,
                'path' => Helper::getDownloadDir(),
            ]);
        }



        \OC::$server->get(\Psr\Log\LoggerInterface::class)->warning(
            'VAPOR YTDL: spawned detached pid=' . $pid
            . ' gid=' . $gid
            . ' log=' . $logFile
        );

        return [
            'spawned' => true,
            'pid' => $pid,
            'log' => $logFile,
        ];
    }



    private function onError($buffer)
    {
        $this->helper->log($buffer);
    }

    public function onOutput($buffer, $extra)
    {
        $this->helper->run($buffer, $extra);
    }
    public function getDownloadUrl($url)
    {
        $this->setUrl($url);
        $this->GetUrlOnly();
        $this->buildCMD();
        exec($this->cmd, $output, $returnCode);
        if (count($output) === 1) {
            return ['url' => reset($output)];
        }
        list($url, $filename) = $output;
        $filename = Helper::cleanString($filename);
        return ['url' => $url, 'filename' => Helper::clipFilename($filename)];
    }

    public function setUrl($url)
    {
        $this->prependOption($url);
        //$index = array_search('-i', $this->options);
        //array_splice($this->options, $index + 1, 0, $url);
    }
    public function setOption($key, $value, $hyphens = false)
    {
        $this->addOption($key, $hyphens);
        $this->addOption($value, false);
        return $this;
    }
    public function addOption(String $option, $hyphens = false)
    {
        if ($hyphens && substr($option, 0, 2) !== '--') {
            $option = "--" . $option;
        }
        array_push($this->options, $option);
    }

    protected function hasOption($name)
    {
        return array_search($name, $this->options);
    }

    public function forceIPV4()
    {
        $this->addOption('force-ipv4', true);
        return $this;
    }

    private function buildCMD()
    {
        $this->cmd = $this->bin; //. " 2>&1";

        foreach ($this->options as $option) {
            $this->cmd .= " " . $option;
        }
    }
    public function isInstalled()
    {
        return @file_exists($this->bin);
    }
    public function isExecutable()
    {
        return @is_executable($this->bin);
    }

    public function isReadable()
    {
        return @is_readable($this->bin);
    }

    public function getBin()
    {
        return $this->bin;
    }
    public function install()
    {
        $url = $this->installUrl();
        $file = __DIR__ . "/../../bin/yt-dlp2";
        try {
            Helper::Download($url, $file);
            chmod($file, 0744);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return false;
    }

    public function installUrl()
    {
        return "https://github.com/shiningw/vapor-bin/raw/master/yt-dlp";
    }

    public function version()
    {
        $process = new Process([$this->bin, '--version']);
	$process->run();
        if ($process->isSuccessful()) {
            //remove any new line
            return trim($process->getOutput());
        }
        return false;
    }


    /**
     * Fetch the site-specific video id and title for a URL, without downloading.
     * Used to pre-compute the gid and display name so the row can be inserted
     * with the real filename before yt-dlp starts downloading.
     */
    public function getIdAndTitle(string $url): array
    {
        $process = new Process([
            $this->bin,
            '--no-playlist',
            '--print', 'id',
            '--print', 'title',
            $url,
        ]);
        $process->setTimeout(60);
        $process->run();
        if (!$process->isSuccessful()) {
            \OC::$server->get(\Psr\Log\LoggerInterface::class)->warning(
                'VAPOR YTDL getIdAndTitle: failed for ' . $url
                . ' stderr=' . substr($process->getErrorOutput(), 0, 300)
            );
            return ['id' => null, 'title' => null];
        }
        $lines = array_values(array_filter(explode("\n", trim($process->getOutput())), 'strlen'));
        if (count($lines) < 2) {
            return ['id' => null, 'title' => null];
        }
        return ['id' => $lines[0], 'title' => $lines[1]];
    }




    public function check()
    {
        if ($tagName = Helper::getLatestRelease('yt-dlp', 'yt-dlp')) {
            $tagName = Helper::removeLetters($tagName);
            $version = $this->version();
            if ($version && version_compare($version, $tagName, '<')) {
                return ['status' => true, 'message' => $tagName];
            }
        }
//        return ['status' => false, 'message' => 'No update available'];
        return ['status' => false, 'message' => $this->l10n->t("No update available")];
    }
    public function update()
    {
        $file = __DIR__ . "/../../bin/yt-dlp";
        try {
            Helper::downloadLatestRelease('yt-dlp', 'yt-dlp', $file);
            chmod($file, 0744);
        } catch (\Exception $e) {
            return ['status' => false,'message' => $e->getMessage()];
        }
        return ['status' => true, 'message' => 'Updated to latest version','data' => $this->version()];
    }
}
