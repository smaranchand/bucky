<?php

namespace Bucky;

class Logger
{
    private string $logFile;

    public function __construct(?string $path = null)
    {
        $this->logFile = $path ?? dirname(__DIR__) . '/logs/bucky.log';
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
