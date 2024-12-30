<?php
declare(strict_types=1);

namespace GarvinHicking\Gerrviewn;

final readonly class RemoteService
{
    private string $url;
    private string $storage;
    private string $csvMergers;
    private string $csvRegulars;
    private string $csvSpecial;

    public function __construct(private LogService $logService, private \SQLite3 $database) {
        $this->url = 'https://review.typo3.org/changes/?'
            . 'q=status:open+-is:wip'
            . '&o=LABELS'
            . '&o=DETAILED_LABELS'
            . '&o=SUBMIT_REQUIREMENTS'
            . '&o=CURRENT_REVISION'
            . '&o=CURRENT_COMMIT'
            . '&o=DETAILED_ACCOUNTS'
            . '&o=MESSAGES'
            . '&o=SUBMITTABLE'
            . '&o=WEB_LINKS'
            . '&n=1000';
        $this->storage = __DIR__ . '/../json/changes.json';
        // TODO: Refactor me into a CSV|User Service
        $this->csvMergers = __DIR__ . '/../db/mergers.csv';
        $this->csvRegulars = __DIR__ . '/../db/regulars.csv';
        $this->csvSpecial = __DIR__ . '/../db/special.csv';
    }

    public function run(): int {
        $contents = file_get_contents($this->url);
        $contents = preg_replace('@^\)\]\}\'\n@imsU', '', $contents);
        $fp = fopen($this->storage, 'wb');
        $bytes = fwrite($fp, $contents);
        fclose($fp);

        $this->logService->info('Fetched ' . $bytes . ' bytes from remote.');

        return $bytes;
    }

    public function hydrate(): void {
        if (!file_exists($this->storage)) {
            $this->run();
        }

        $contents = file_get_contents($this->storage);
        $json = json_decode($contents, TRUE);

        $this->logService->info('Thawed ' . count($json) . ' issues.');

        $this->database->query('UPDATE changes
                                   SET is_active = 0
                                 WHERE is_active = 1');

        // @TODO: Move these files to constructor
        $coreTeam = explode("\n", file_get_contents($this->csvMergers));
        $collectedOwners = [];
        foreach ($json as $index => $change) {
            $uid = $this->createOrFetchChange($change['virtual_id_number']);

            if (!isset($collectedOwners[$change['owner']['name']])) {
                $collectedOwners[$change['owner']['name']] = [
                    'count'     => 0,
                    'usernames' => [],
                ];
            }

            $collectedOwners[$change['owner']['name']]['count']++;
            if (!in_array($change['owner']['username'], $collectedOwners[$change['owner']['name']]['usernames'])) {
                $collectedOwners[$change['owner']['name']]['usernames'][] = $change['owner']['username'];
            }

            $updateQuery = $this->database->prepare(
                'UPDATE changes
                  SET is_active = 1,
                      title = :title,
                      owner = :owner,
                      is_wip = :is_wip,
                      url = :url,
                      patch_size = :patch_size,
                      last_modified = :last_modified,
                      created = :created,
                      comments = :comments,
                      commit_message = :commit_message,
                      branch = :branch
                WHERE uid = ' . $uid
            );

            // TODO: [BUGFIX] / [TASK] / [DOCS] / [FEATURE] | category
            // TODO: [!!!] | is_breaking
            // TODO: unresolved_comment_count
            // TODO: "I've seen this"
            // TODO: "I want this"
            // TODO: "Please move forward"
            $updateQuery->bindValue(':title', $change['subject'], SQLITE3_TEXT);
            $updateQuery->bindValue(':owner', $change['owner']['name'], SQLITE3_TEXT);
            $updateQuery->bindValue(':is_wip', (int)str_contains($change['subject'], '[WIP]'), SQLITE3_INTEGER);
            $updateQuery->bindValue(':url', 'https://review.typo3.org/c/Packages/TYPO3.CMS/+/' . $change['virtual_id_number'], SQLITE3_TEXT);
            $updateQuery->bindValue(':patch_size', abs($change['insertions']) + abs($change['deletions']), SQLITE3_INTEGER);
            $updateQuery->bindValue(':last_modified', strtotime($change['updated']), SQLITE3_INTEGER);
            $updateQuery->bindValue(':created', strtotime($change['created']), SQLITE3_TEXT);
            $updateQuery->bindValue(':comments', $change['total_comment_count'], SQLITE3_TEXT);
            $updateQuery->bindValue(':commit_message', $change['revisions'][$change['current_revision']]['commit']['message'], SQLITE3_TEXT);
            $updateQuery->bindValue(':branch', $change['branch'], SQLITE3_TEXT);

            $result = $updateQuery->execute();
            $updateQuery->close();

            if ($result === FALSE) {
                $this->logService->error('DB UPDATE failed for UID #' . $uid);
                continue;
            }

            $this->logService->info('+ #' . $change['virtual_id_number'] . ' - ' . $change['subject']);
        }

        $dupes = [];
        foreach ($collectedOwners as $name => $userdata) {
            if (count($userdata['usernames']) === 0) {
                $this->logService->error(
                    sprintf(
                        'INCONSISTENCY: No username matched to "%s"',
                        $name,
                    )
                );
            } else if (count($userdata['usernames']) > 1) {
                $this->logService->error(
                    sprintf(
                        'INCONSISTENCY: More than one username matched to "%s": %s',
                        $name,
                        implode(', ', $userdata['usernames']),
                    )
                );
            } else {
                if (isset($dupes[$userdata['usernames'][0]])) {
                    $this->logService->error(
                        sprintf(
                            'INCONSISTENCY: More than one name matched to username "%s": %s',
                            $userdata['usernames'][0],
                            implode(', ', $dupes[$userdata['usernames'][0]]),
                        )
                    );
                } else {
                    $dupes[$userdata['usernames'][0]] = [];
                }

                $dupes[$userdata['usernames'][0]][] = $name;
            }
        }

        $this->logService->info(count($collectedOwners) . ' Owners evaluated.');

        $leaderboard = [];
        $coreTeamPatches = 0;
        foreach ($collectedOwners as $name => $userdata) {
            if (in_array($userdata['usernames'][0], $coreTeam)) {
                $coreTeamPatches += $userdata['count'];
            }
            $leaderboard[$name] = $userdata['count'];
        }

        arsort($leaderboard);
        $this->logService->info('Total Core team patches: ' . $coreTeamPatches);
        $this->logService->info('Individual patches:' . "\n" . print_r($leaderboard, true));

        $this->database->query('DELETE FROM changes
                                 WHERE is_active = 0');

        /*
        SOLVED:
        ============================================================
        branch = [main, ...]
        subject = [BUGFIX] ...
        created = 2024-12-28 09:43:41.000000000
        updated = 2024-12-28 09:43:41.000000000
        total_comment_count
        virtual_id_number
        owner[name]
        owner[username]
        insertions = code zeilen mit änderungen
        deletions = code zeilen mit löschungen

        UNSOLVED:
        ============================================================
        mergeable = 1
        submittable =
        status = immer NEW?
        attention_set
        submit_type = immer CHERRY_PICK ?
        unresolved_comment_count

        labels[Verified][recommended]
        labels[Code-Review][recommended]
        */

    }

    private function pickRow(string $query, bool $pickSingle = true): array
    {
        $results = $this->database->query($query);
        $return = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $return[] = $row;
        }

        if ($return === []) {
            return [];
        }

        if ($pickSingle) {
            return $return[0];
        }

        return $return;
    }

    private function createOrFetchChange($gerrit_uid): int
    {
        $existing_row = $this->pickRow('SELECT uid FROM changes WHERE gerrit_uid = ' . (int)$gerrit_uid);
        if ($existing_row === []) {
            $insertQuery = $this->database->prepare(
                'INSERT INTO changes (gerrit_uid) VALUES (:gerrit_uid)'
            );
            $insertQuery->bindValue(':gerrit_uid', $gerrit_uid, SQLITE3_INTEGER);

            // Execute the INSERT statement
            $result = $insertQuery->execute();
            if ($result === FALSE) {
                $this->logService->error('DB INSERT failed for UID #' . $gerrit_uid);

                return 0;
            }

            return $this->database->lastInsertRowID();
        }

        return $existing_row['uid'];
    }

}
