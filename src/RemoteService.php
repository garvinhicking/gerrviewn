<?php

declare(strict_types=1);

namespace GarvinHicking\Gerrviewn;

use SQLite3;

final readonly class RemoteService
{
    private string $url;
    private string $storage;
    private string $csvMergers;
    // private string $csvRegulars;
    // private string $csvSpecial;

    public function __construct(private LogService $logService, private SQLite3 $database)
    {
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
        // @TODO: Refactor me into a CSV|User Service
        $this->csvMergers = __DIR__ . '/../db/mergers.csv';
        // $this->csvRegulars = __DIR__ . '/../db/regulars.csv';
        // $this->csvSpecial = __DIR__ . '/../db/special.csv';
    }

    public function run(): int
    {
        $contents = file_get_contents($this->url);
        if (!is_string($contents)) {
            $this->logService->error('Unable to fetch: ' . $this->url);
            return 0;
        }

        $contents = (string) preg_replace('@^\)\]\}\'\n@imsU', '', $contents);
        $fp = fopen($this->storage, 'wb');
        if (!is_resource($fp)) {
            $this->logService->error('Unable to write to: ' . $this->storage);
            return 0;
        }

        $bytes = fwrite($fp, $contents);
        fclose($fp);

        $this->logService->info('Fetched ' . $bytes . ' bytes from remote.');

        return (int) $bytes;
    }

    public function hydrate(): void
    {
        if (!file_exists($this->storage)) {
            $this->run();
        }

        $contents = file_get_contents($this->storage);
        if (!is_string($contents)) {
            $this->logService->error('Unable to parse: ' . $this->storage);
            return;
        }

        $json = json_decode($contents, true);
        if (!is_array($json)) {
            $this->logService->error('Unable to JSON decode');
            return;
        }

        $this->logService->info('Thawed ' . count($json) . ' issues.');

        $this->database->query(
            'UPDATE changes
                                   SET is_active = 0
                                 WHERE is_active = 1'
        );

        // @TODO: Move these files to constructor
        $coreTeam = explode("\n", (string) file_get_contents($this->csvMergers));

        /** @var array<string, array{
         *      count: int,
         *      usernames: array<int, string>
         * }> $collectedOwners
         */
        $collectedOwners = [];

        /** @var array<int, array{
         *     virtual_id_number: int,
         *     owner: array{
         *         name: string,
         *         username: ?string,
         *         avatars: array<int, array{
         *             url: string,
         *             height: int,
         *         }>
         *     },
         *     subject: string,
         *     updated: string,
         *     created: string,
         *     total_comment_count: int,
         *     unresolved_comment_count: int,
         *     branch: string,
         *     insertions: int,
         *     deletions: int,
         *     revisions: array<string, array{
         *         commit: array{message: string}
         *     }>,
         *     labels: array{
         *         Verified: array{
         *             all: array<int, array{
         *                 username: ?string,
         *                 name: string,
         *                 permitted_voting_range: array{min: int, max: int},
         *                 value: int,
         *                 avatars: array<int, array{
         *                     url: string,
         *                     height: int,
         *                 }>
         *             }>
         *         },
         *         Code-Review: array{
         *             all: array<int, array{
         *                 username: ?string,
         *                 name: string,
         *                 permitted_voting_range: array{min: int, max: int},
         *                 value: int,
         *                 avatars: array<int, array{
         *                     url: string,
         *                     height: int,
         *                 }>
         *             }>
         *         }
         *     },
         *     current_revision: string
         * }> $json
         */
        foreach ($json as $index => $change) {
            $uid = $this->createOrFetchChange($change['virtual_id_number']);

            if (!isset($collectedOwners[$change['owner']['name']])) {
                $collectedOwners[$change['owner']['name']] = [
                    'count'     => 0,
                    'usernames' => [],
                ];
            }

            $involved = [];
            $score = [
                'Verified'   => 0,
                'Code-Review' => 0,
            ];

            $collectedOwners[$change['owner']['name']]['count']++;
            if (!in_array($change['owner']['username'], $collectedOwners[$change['owner']['name']]['usernames'])) {
                $collectedOwners[$change['owner']['name']]['usernames'][] = $change['owner']['username'];
                $involved[$change['owner']['username']] = [
                    'comments'      => 0,
                    'Verified'      => 0,
                    'Code-Review'   => 0,
                    'owner'         => true,
                    'ci'            => false,
                    'username'      => $change['owner']['name'],
                    'avatar'        => $this->findBiggestAvatar($change['owner']['avatars']),
                ];
            }

            $involved['core-ci'] = [
                'comments'      => 0,
                'Verified'      => 0,
                'Code-Review'   => 0,
                'owner'         => false,
                'ci'            => true,
                'username'      => 'TYPO3 CI',
                'avatar'        => '',
            ];

            $this->addToInvolved($change, 'Verified', $involved);
            $this->addToInvolved($change, 'Code-Review', $involved);

            $updateQuery = $this->database->prepare(
                'UPDATE changes
                  SET is_active = 1,
                      title = :title,
                      owner = :owner,
                      owner_avatar = :owner_avatar,
                      is_wip = :is_wip,
                      url = :url,
                      patch_size = :patch_size,
                      last_modified = :last_modified,
                      created = :created,
                      comments = :comments,
                      comments_unresolved = :comments_unresolved,
                      commit_message = :commit_message,
                      branch = :branch,
                      debug = :debug,
                      involved = :involved
                WHERE uid = ' . $uid
            );

            if ($updateQuery === false) {
                continue;
            }

            $updateQuery->bindValue(':title', $change['subject'], SQLITE3_TEXT);
            $updateQuery->bindValue(':owner', $change['owner']['name'], SQLITE3_TEXT);
            $updateQuery->bindValue(':owner_avatar', $this->findBiggestAvatar($change['owner']['avatars']), SQLITE3_TEXT);
            $updateQuery->bindValue(':is_wip', (int) str_contains($change['subject'], '[WIP]'), SQLITE3_INTEGER);
            $updateQuery->bindValue(':url', 'https://review.typo3.org/c/Packages/TYPO3.CMS/+/' . $change['virtual_id_number'], SQLITE3_TEXT);
            $updateQuery->bindValue(':patch_size', abs($change['insertions']) + abs($change['deletions']), SQLITE3_INTEGER);
            $updateQuery->bindValue(':last_modified', strtotime($change['updated']), SQLITE3_INTEGER);
            $updateQuery->bindValue(':created', strtotime($change['created']), SQLITE3_TEXT);
            $updateQuery->bindValue(':comments', $change['total_comment_count'], SQLITE3_INTEGER);
            $updateQuery->bindValue(':comments_unresolved', $change['unresolved_comment_count'], SQLITE3_INTEGER);
            $updateQuery->bindValue(':commit_message', $change['revisions'][$change['current_revision']]['commit']['message'], SQLITE3_TEXT);
            $updateQuery->bindValue(':branch', $change['branch'], SQLITE3_TEXT);
            $updateQuery->bindValue(':debug', print_r($change, true), SQLITE3_TEXT);

            $updateQuery->bindValue(':involved', $this->parseInvolved($involved, $coreTeam, ($change['unresolved_comment_count'] > 0)), SQLITE3_TEXT);

            /*
             * messages[<int>][author][username|avatars|name]
             * messages[<int>][date]
             * skip when: messages[<int>][tag] === autogenerated:gerrit:newPatchSet
             * skip when: messages[<int>][author][username] === core-ci
             */
            $result = $updateQuery->execute();
            $updateQuery->close();

            if ($result === false) {
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
            } elseif (count($userdata['usernames']) > 1) {
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

        $this->database->query(
            'DELETE FROM changes
                                 WHERE is_active = 0'
        );

        /*
        SOLVED:
        ============================================================
        branch = [main, ...]
        subject = [BUGFIX] ...
        created = 2024-12-28 09:43:41.000000000
        updated = 2024-12-28 09:43:41.000000000
        total_comment_count
        unresolved_comment_count
        virtual_id_number
        owner[name]
        owner[username]
        owner[avatars]
        insertions = code zeilen mit änderungen
        deletions = code zeilen mit löschungen

        UNSOLVED:
        ============================================================
        mergeable = 1
        submittable =
        status = immer NEW?
        attention_set
        submit_type = immer CHERRY_PICK ?

        labels[Verified][recommended]
        labels[Code-Review][recommended]
        */
    }

    /**
     * @return array<int|string, mixed>
     */
    private function pickRow(string $query, bool $pickSingle = true): array
    {
        $results = $this->database->query($query);
        $return = [];

        if ($results === false) {
            return $return;
        }

        do {
            $row = $results->fetchArray(SQLITE3_ASSOC);
            if ($row === false) {
                continue;
            }
            $return[] = $row;
        } while ($row !== false);

        if ($return === []) {
            return [];
        }

        if ($pickSingle) {
            return $return[0];
        }

        return $return;
    }

    private function createOrFetchChange(int $gerrit_uid): int
    {
        $existing_row = $this->pickRow('SELECT uid FROM changes WHERE gerrit_uid = ' . (int) $gerrit_uid);
        if ($existing_row === []) {
            $insertQuery = $this->database->prepare(
                'INSERT INTO changes (gerrit_uid) VALUES (:gerrit_uid)'
            );

            if ($insertQuery === false) {
                return 0;
            }
            $insertQuery->bindValue(':gerrit_uid', $gerrit_uid, SQLITE3_INTEGER);

            // Execute the INSERT statement
            $result = $insertQuery->execute();
            if ($result === false) {
                $this->logService->error('DB INSERT failed for UID #' . $gerrit_uid);

                return 0;
            }

            return $this->database->lastInsertRowID();
        }

        if (isset($existing_row['uid']) && is_int($existing_row['uid'])) {
            return $existing_row['uid'];
        }

        return 0;
    }

    /** @param array<int, array{
     * url: string,
     * height: int,
     * }> $avatars */
    private function findBiggestAvatar(array $avatars): string
    {
        $pickedAvatar = '';
        $maxHeight = 0;
        foreach ($avatars as $avatar) {
            if ($maxHeight < $avatar['height']) {
                $pickedAvatar = $avatar['url'];
                $maxHeight = $avatar['height'];
            }
        }

        return $pickedAvatar;
    }

    /**
     * @param array{
     *     virtual_id_number: int,
     *     owner: array{
     *         name: string,
     *         username: ?string,
     *         avatars: array<int, array{
     *             url: string,
     *             height: int,
     *         }>
     *     },
     *     subject: string,
     *     updated: string,
     *     created: string,
     *     total_comment_count: int,
     *     unresolved_comment_count: int,
     *     branch: string,
     *     insertions: int,
     *     deletions: int,
     *     revisions: array<string, array{
     *         commit: array{message: string}
     *     }>,
     *     labels: array{
     *         Verified: array{
     *             all: array<int, array{
     *                 username: ?string,
     *                 name: string,
     *                 permitted_voting_range: array{min: int, max: int},
     *                 value: int,
     *                 avatars: array<int, array{
     *                     url: string,
     *                     height: int,
     *                 }>
     *             }>
     *         },
     *         Code-Review: array{
     *             all: array<int, array{
     *                 username: ?string,
     *                 name: string,
     *                 permitted_voting_range: array{min: int, max: int},
     *                 value: int,
     *                 avatars: array<int, array{
     *                     url: string,
     *                     height: int,
     *                 }>
     *             }>
     *         }
     *     },
     *     current_revision: string
     * } $change
     * @param array<string, array<string,string|int|bool|null>> $involved
     */
    private function addToInvolved(array $change, string $type, array &$involved): void
    {
        foreach (($change['labels'][$type]['all'] ?? []) as $involvedPerson) {
            if (!isset($involvedPerson['username'])) {
                $involvedPerson['username'] = $involvedPerson['name'];
            }
            if (!isset($involved[$involvedPerson['username']])) {
                $involved[$involvedPerson['username']] = [
                    'comments'      => 0,
                    'Verified'      => 0,
                    'Code-Review'   => 0,
                    'owner'         => false,
                    'avatar'        => $this->findBiggestAvatar($involvedPerson['avatars']),
                    'username'      => $involvedPerson['name'],
                ];
            }

            if (empty($involved[$involvedPerson['username']]['avatar'])) {
                $involved[$involvedPerson['username']]['avatar'] = $this->findBiggestAvatar($involvedPerson['avatars']);
            }

            $involved[$involvedPerson['username']][$type] = $involvedPerson['value'];
        }
    }

    /**
     * @param array<string, array<?string,string|int|bool|null>> $involved
     * @param array<int,string> $coreTeam
     */
    private function parseInvolved(array $involved, array $coreTeam, bool $hasUnresolvedComments): string
    {
        $avatars = [];

        $coreVerified = 0;
        $otherVerified = 0;
        $coreCodeReview = 0;
        $otherCodeReview = 0;
        $ciVerified = 0;
        $blocked = false;

        foreach ($involved as $username => $involvedPerson) {
            $css = [];
            if (in_array($username, $coreTeam, true)) {
                $isCoreMember = true;
                $css[] = 'core-member';
            } else {
                $isCoreMember = false;
            }

            $points = 0;
            switch ((int) ($involvedPerson['Verified'] ?? 0)) {
                case -2:
                    $css[] = 'downvote2-verified';
                    $blocked = true;
                    break;
                case -1:
                    $css[] = 'downvote1-verified';
                    $blocked = true;
                    break;
                case 0:
                    $css[] = 'no-verified';
                    break;
                case 1:
                    $css[] = 'upvote1-verified';
                    $points = 1;
                    break;
                case 2:
                    $css[] = 'upvote2-verified';
                    $points = 1;
                    break;
            }

            if ($username === 'core-ci') {
                $css[] = 'ci-member';
                if ($points > 0) {
                    $ciVerified = 1;
                }
                $points = 0;
            }

            if ($involvedPerson['owner'] === 1) {
                $points = 0;
            }

            if ($points > 0) {
                if ($isCoreMember) {
                    $coreVerified++;
                } else {
                    $otherVerified++;
                }
            }

            $points = 0;
            switch ((int) ($involvedPerson['Code-Review'] ?? 0)) {
                case -2:
                    $css[] = 'downvote2-codereview';
                    $blocked = true;
                    break;
                case -1:
                    $css[] = 'downvote1-codereview';
                    $blocked = true;
                    break;
                case 0:
                    $css[] = 'no-codereview';
                    break;
                case 1:
                    $css[] = 'upvote1-codereview';
                    $points = 1;
                    break;
                case 2:
                    $css[] = 'upvote2-codereview';
                    $points = 1;
                    break;
            }

            if ($involvedPerson['owner'] === 1) {
                $points = 0;
            }

            if ($points > 0) {
                if ($isCoreMember) {
                    $coreCodeReview++;
                } else {
                    $otherCodeReview++;
                }
            }

            if ($isCoreMember) {
                $displayName = '(Core-Merger) ' . $involvedPerson['username'];
            } else {
                $displayName = '(Regular) ' . $involvedPerson['username'];
            }

            if ($involvedPerson['owner'] === 1) {
                $displayName .= ' [OWNER]';
            }

            $avatars[] = '<img class="votevatar ' . implode(' ', $css) . '"
                               src="' . $involvedPerson['avatar'] . '"
                               alt="' . htmlspecialchars($displayName) . '"
                               title="' . htmlspecialchars($displayName) . '">';
        }

        $avatarHtml = implode(' ', $avatars)
            . ' <!-- coreVerified: ' . $coreVerified . ' -->'
            . ' <!-- coreCodeReview: ' . $coreCodeReview . ' -->'
            . ' <!-- otherVerified: ' . $otherVerified . ' -->'
            . ' <!-- otherCodeReview: ' . $otherCodeReview . ' -->'
            . ' <!-- ciVerified: ' . $ciVerified . ' -->'
            . ' <!-- blocked: ' . $blocked . ' -->';

        if ($blocked) {
            return '<div class="merge-box merge-impossible">' . $avatarHtml . '</div>';
        }

        if (
            // At least one non-owner core member
            $coreVerified >= 1
            && $coreCodeReview >= 1

            // And at least 2 votes from core and non-core members
            && ($otherVerified + $coreVerified) >= 2
            && ($otherCodeReview + $coreCodeReview) >= 2

            // And Green CI
            && $ciVerified === 1
        ) {
            if ($hasUnresolvedComments) {
                return '<div class="merge-box merge-maybe-possible">' . $avatarHtml . '</div>';
            }

            return '<div class="merge-box merge-possible">' . $avatarHtml . '</div>';
        }

        return '<div class="merge-box merge-neutral">' . $avatarHtml . '</div>';
    }
}
