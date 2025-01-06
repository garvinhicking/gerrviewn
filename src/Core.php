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
    private bool $isInitialized;

    public function __construct(bool $reset = false, bool $fetchJsonFirstRun = true)
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
        if ($reset) {
            unlink($sqlite);
            $fresh = true;
        }

        $this->db = new SQLite3($sqlite);

        if ($fresh) {
            $this->initDatabaseTables();
            $remoteService = new RemoteService($this->logService, $this->db);
            if ($fetchJsonFirstRun) {
                $remoteService->run();
            }
            $remoteService->hydrate();
            $this->isInitialized = true;
        } else {
            $this->isInitialized = false;
        }
    }

    public function cliFetch(): void
    {
        if ($this->isInitialized) {
            return;
        }
        $remoteService = new RemoteService($this->logService, $this->db);
        $remoteService->run();
        $this->logService->emit();
    }

    public function cliParse(): void
    {
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
        if (!is_array($sqlFiles)) {
            return;
        }

        foreach ($sqlFiles as $sqlFile) {
            // SQLite only supports one statement per file.
            if (!$this->db->exec((string) file_get_contents($sqlFile))) {
                throw new RuntimeException('Cannot execute SQL in file ' . $sqlFile);
            }
        }
    }

    public function timestampedUri(string $path): string
    {
        $f = filemtime($path);
        return preg_replace('@^' . preg_quote($this->webroot, '@') . '@i', '', $path) . '?' . $f;
    }

    // @TODO: Who needs templating when PHP is a template language on its own...
    public function run(): bool
    {
        if (!isset($this->db)) {
            die('Boostrap failed.');
        }

        echo $this->htmlHead();

        echo '<h1>Gerrit patches ON MAIN</h1>';
        echo $this->renderList('SELECT * FROM changes WHERE branch = "main" AND is_active = 1');

        echo '<h1>Gerrit patches cherry-picks</h1>';
        echo $this->renderList('SELECT * FROM changes WHERE branch != "main" AND is_active = 1');

        echo $this->htmlFooter();

        return true;
    }

    public function includeViteAssets(): string
    {
        // We could fetch this from manifest.json, but why not Zoidberg this?
        $js = glob($this->webroot . '/frontend/assets/vite.entry-*.js');
        $css = glob($this->webroot . '/frontend/assets/vite-*.css');

        $out = '';
        if (is_array($js)) {
            foreach ($js as $file) {
                $out .= '    <script type="module" src="' . $this->timestampedUri($file) . '"></script>' . "\n";
            }
        }

        if (is_array($css)) {
            foreach ($css as $file) {
                $out .= '    <link media="screen" href="' . $this->timestampedUri($file) . '" rel="stylesheet">' . "\n";
            }
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
<body>

<div class="involved">
 <img class="votevatar no-verified no-codereview" src="https://www.gravatar.com/avatar/a0751c5152f32a164e4a26a86aa27c04.jpg?d=identicon&r=pg&s=120">
 <img class="votevatar upvote1-verified upvote1-codereview" src="https://www.gravatar.com/avatar/a0751c5152f32a164e4a26a86aa27c04.jpg?d=identicon&r=pg&s=120">
 <img class="votevatar upvote2-verified upvote2-codereview" src="https://www.gravatar.com/avatar/a0751c5152f32a164e4a26a86aa27c04.jpg?d=identicon&r=pg&s=120">
 <img class="votevatar downvote1-verified downvote1-codereview" src="https://www.gravatar.com/avatar/a0751c5152f32a164e4a26a86aa27c04.jpg?d=identicon&r=pg&s=120">
 <img class="votevatar downvote2-verified downvote2-codereview" src="https://www.gravatar.com/avatar/a0751c5152f32a164e4a26a86aa27c04.jpg?d=identicon&r=pg&s=120">
</div>
';
    }

    public function htmlFooter(): string
    {
        return '
    <button id="theme-toggle">Toggle dark/light mode</button>
</body>
</html>';
    }

    public function renderList(string $query): string
    {
        $results = $this->db->query($query);

        if ($results === false) {
            return '';
        }

        $out = '<ol>';

        do {
            /** @var false|array<string, ?string> $row */
            $row = $results->fetchArray(SQLITE3_ASSOC);

            if (is_array($row)) {
                $out .= '<li class="issue">';

                $out .= '
                <details>
                    <summary>
                        ' . htmlspecialchars($row['title'] ?? 'N/A') . '
                        <div class="mainLine">
                            <div class="extra">
                            <span class="highlight-box">' . $this->weighPatchSize((int) $row['patch_size']) . '</span>
                            ' . $row['comments'] . ' comments'
                            . ($row['comments_unresolved'] > 0 ? ' (<strong class="danger">' . $row['comments_unresolved'] . ' open</strong>)' : '')
                            . ', ' . $row['patch_size'] . ' lines' . '
                            </div>
                            <div class="author">
                              <img class="avatar" src="' . htmlspecialchars($row['owner_avatar'] ?? '') . '" />' . htmlspecialchars($row['owner'] ?? 'N/A') . '
                            </div>
                        </div>
                        <div class="mainLine">
                            <div class="extra2">
                            ' . $this->timeWithCutoff((int) $row['last_modified']) . '
                            </div>
                            ' . ($row['branch'] !== 'main' ? '
                            <div class="branch">
                            ' . htmlspecialchars($row['branch'] ?? 'N/A') . '
                            </div>
                            ' : '') . '
                        </div>
                        <div class="mainLine">
                            <div class="extra3">
                            &nbsp;
                            </div>
                            <div class="age">
                                <span title="Last touched">' . $this->daysSince((int) $row['last_modified'], time()) . '</span>
                            </div>
                        </div>
                        <div class="mainLine">
                            <div class="extra4">
                            ' . $row['involved'] . '
                            </div>
                            <div class="age2">
                                <span title="Days since creation on ' . date('d.m.Y H:i', (int) $row['created']) . '">' . $this->daysSince((int) $row['created'], time()) . '</span>
                            </div>
                        </div>
                    </summary>
                    <article>
                        <div class="gerrit_link">
                            <a target="_blank" href="' . htmlspecialchars($row['url'] ?? '') . '">Gerrit</a>
                       </div>
                        <div class="forge_link">
                            <a target="_blank" href="#">Forge</a>
                        </div>

                        ' . nl2br(htmlspecialchars($row['commit_message'] ?? '')) . '
                    </article>
                </details>';

                $out .= '</li>' . "\n";
            }
        } while ($row !== false);

        $out .= '</ol>';

        return $out;
    }

    public function daysSince(int $created, int $modified): string
    {
        $seconds = $modified - $created;

        if ($seconds < 86400) {
            return '<span class="hours">' . ceil($seconds / 60 / 60) . ' hours</span>';
        }

        $days = ceil($seconds / 60 / 60 / 24);

        if ($days > 365) {
            return '<span class="years">' . round($days / 365, 2) . ' years</span>';
        }

        if ($days > 30) {
            return '<span class="months">' . round($days / 30, 2) . ' months</span>';
        }

        return '<span class="days">' . $days . ' days</span>';
    }

    /**
     * Calculation to put size thresholds into readable "XL", "L", "M", ... outputs
     */
    private function weighPatchSize(int $patch_size): string
    {
        if ($patch_size < 8) {
            return 'XS';
        }

        if ($patch_size < 50) {
            return 'S';
        }

        if ($patch_size < 250) {
            return 'M';
        }

        if ($patch_size < 700) {
            return 'L';
        }

        return 'XL';
    }

    private function timeWithCutoff(int $last_modified): string
    {
        if ($last_modified > (time() - (86400 * 7))) {
            return date('d.m.Y H:i', $last_modified);
        }

        return date('d.m.Y', $last_modified);
    }
}
