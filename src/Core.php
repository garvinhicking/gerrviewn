<?php
declare(strict_types=1);

namespace GarvinHicking\Gerrviewn;

use RuntimeException;
use SQLite3;

final readonly class Core
{
    private SQLite3 $db;
    private string $path;
    private string $webroot;
    private LogService $logService;
    private ?bool $isInitialized;

    public function __construct()
    {
        $this->path = __DIR__ . '/../db/';
        $this->webroot = __DIR__ . '/../public/';
        $this->logService = new LogService();

        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0777, true)) {
                throw new RuntimeException('Cannot create directory ' . $this->path);
            }
        }

        if (!is_writable($this->path)) {
            throw new RuntimeException('Cannot write to ' . $this->path);
        }

        $sqlite = $this->path . '/instance.sqlite';
        $fresh = false;
        if (!file_exists($sqlite)) {
            $fresh = true;
        }
        $this->db = new SQLite3($sqlite);

        if ($fresh) {
            $this->initDatabaseTables();
            $remoteService = new RemoteService($this->logService, $this->db);
            $remoteService->run();
            $remoteService->hydrate();
            $this->isInitialized = true;
        } else {
            $this->isInitialized = false;
        }
    }

    public function cliFetch(): void {
        if ($this->isInitialized) {
            return;
        }
        $remoteService = new RemoteService($this->logService, $this->db);
        $remoteService->run();
        $this->logService->emit();
    }

    public function cliParse(): void {
        if ($this->isInitialized) {
            return;
        }
        $remoteService = new RemoteService($this->logService, $this->db);
        $remoteService->hydrate();
        $this->logService->emit();
    }

    public function initDatabaseTables(): void
    {
        $sqlFiles = glob($this->path . 'structure-*.sql');
        foreach ($sqlFiles as $sqlFile) {
            // SQLite only supports one statement per file.
            if (!$this->db->exec(file_get_contents($sqlFile))) {
                throw new RuntimeException('Cannot execute SQL in file ' . $sqlFile);
            }
        }
    }

    public function timestampedUri(string $path): string
    {
        $f = filemtime($path);
        return preg_replace('@^' . preg_quote($this->webroot) . '@i', '', $path) . '?' . $f;
    }

    public function run(): bool
    {
        if (!isset($this->db)) {
            die('Boostrap failed.');
        }

        echo $this->htmlHead();
        echo 'MAIN APP RUNNING.';
        echo $this->htmlFooter();

        return true;
    }

    public function includeViteAssets(): string {
        // We could fetch this from manifest.json, but why not Zoidberg this?
        $js = glob($this->webroot . '/frontend/assets/vite.entry-*.js');
        $css = glob($this->webroot . '/frontend/assets/vite-*.css');

        $out = '';
        foreach($js as $file) {
            $out .= '    <script type="module" src="' . $this->timestampedUri($file) . '"></script>' . "\n";
        }

        foreach($css as $file) {
            $out .= '    <link media="screen" href="' . $this->timestampedUri($file) . '" rel="stylesheet">' . "\n";
        }

        return $out;
    }

    public function htmlHead(): string
    {
        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Gerrviewn</title>
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="generator" content="Garvin">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
' . $this->includeViteAssets() . '
</head>
<body>';
    }

    public function htmlFooter(): string
    {
        return '</body></html>';
    }
}
