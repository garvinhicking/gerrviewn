<?php

declare(strict_types=1);

namespace GarvinHicking\Gerrviewn;

// TODO: When I grow up, I want to be a PSR logger
final class LogService
{
    private readonly string $storage;
    /** @var array <int,string> */
    private array $entries;

    public function __construct()
    {
        $this->storage = __DIR__ . '/../json/changes.log';
    }

    public function error(string $message): void
    {
        $fp = fopen($this->storage, 'a');
        if (is_resource($fp)) {
            fwrite($fp, date('Y-m-d H:i:s') . ' [ERROR] ' . $message . "\n");
            fclose($fp);
        }
        $this->entries[] = '[ERROR] ' . $message;
    }

    public function info(string $message): void
    {
        $fp = fopen($this->storage, 'a');
        if (is_resource($fp)) {
            fwrite($fp, date('Y-m-d H:i:s') . ' [INFO] ' . $message . "\n");
            fclose($fp);
        }
        $this->entries[] = '[INFO] ' . $message;
    }

    /**
     * @return array <int, string>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function emit(): void
    {
        echo "OUTPUT\n";
        echo "======\n";
        foreach ($this->entries as $message) {
            echo $message . "\n";
        }
        echo "\n";
    }
}
