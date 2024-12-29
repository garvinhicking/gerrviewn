<?php
function pick_row(string $query, bool $pickSingle = true): array {
    global $database;

    $results = $database->query($query);
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

function error($message): void {
    echo $message . "\n";
}

function create_or_fetch_change($gerrit_uid): int {
    global $database;

    $existing_row = pick_row('SELECT uid FROM changes WHERE gerrit_uid = ' . (int)$gerrit_uid);
    if ($existing_row === []) {
        $insertQuery = $database->prepare(
            'INSERT INTO changes (gerrit_uid) VALUES (:gerrit_uid)'
        );
        $insertQuery->bindValue(':gerrit_uid', $gerrit_uid, SQLITE3_INTEGER);

        // Execute the INSERT statement
        $result = $insertQuery->execute();
        if ($result === false) {
            error('DB INSERT failed for UID #' . $gerrit_uid);
            return 0;
        }

        return $database->lastInsertRowID();
    }

    return $existing_row['uid'];
}

$contents = file_get_contents('changes.json');
$json = json_decode($contents, true);

echo count($json) . " issues.\n";

$database = new SQLite3('../db/db.sqlite');
$results = $database->query('UPDATE changes 
                                      SET is_active = 0 
                                    WHERE is_active = 1');

$collectedOwners = [];
foreach ($json as $index => $change) {
    $uid = create_or_fetch_change($change['virtual_id_number']);

    if (!isset($collectedOwners[$change['owner']['name']])) {
        $collectedOwners[$change['owner']['name']] = [
            'count' => 0,
            'usernames' => [],
        ];
    }

    $collectedOwners[$change['owner']['name']]['count']++;
    if (!in_array($change['owner']['username'], $collectedOwners[$change['owner']['name']]['usernames'])) {
        $collectedOwners[$change['owner']['name']]['usernames'][] = $change['owner']['username'];
    }

    $updateQuery = $database->prepare(
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

    if ($result === false) {
        error('DB UPDATE failed for UID #' . $uid);
        continue;
    }

    echo "+ #" . $change['virtual_id_number'] . ' - ' . $change['subject'] . "\n";
}

$dupes = [];
foreach($collectedOwners as $name => $userdata) {
    if (count($userdata['usernames']) === 0) {
        error(
            sprintf(
                'INCONSISTENCY: No username matched to "%s"',
                $name,
            )
        );
    } elseif (count($userdata['usernames']) > 1) {
        error(
            sprintf(
                'INCONSISTENCY: More than one username matched to "%s": %s',
                $name,
                implode(', ', $userdata['usernames']),
            )
        );
    } else {
        if (isset($dupes[$userdata['usernames'][0]])) {
            error(
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

echo count($collectedOwners) . " Owners evaluated.\n";

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
