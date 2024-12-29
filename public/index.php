<?php
function render_table(string $query): void {
    $database = new SQLite3('../db/db.sqlite');
    $results = $database->query($query);

    echo "<table border='1'>";

    $has_head = false;
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        if (!$has_head) {
            echo '<thead><tr>';
            foreach($row as $key => $_) {
                echo '<th>' . $key . '</th>';
            }
            echo '</tr></thead>';
            echo '<tbody>' . "\n";
        }
        $has_head = true;
        echo "<tr>";

        foreach($row as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>" . "\n";
        }

        echo "</tr>";
    }

    echo '</tbody>';
    echo "</table>";
    $database->close();
}
?>
<html>

<body>
<?php

echo "<h1>Gerrit patches ON MAIN</h1>";
render_table('SELECT * FROM changes WHERE branch = "main" AND is_active = 1');

echo "<h1>Gerrit patches cherry-picks</h1>";
render_table('SELECT * FROM changes WHERE branch != "main" AND is_active = 1');

echo "</body></html>";

/**
 * TODO:
- Wie zusammengehörige Patches für unterschiedliche Branches mergen?
- Core-Owner andere Farbe
- Regulars (Leute wie Torben) andere Farbe

Title | WIP? | Owner | Involved People | Branch | Created / Last Modified / Age | Votes
*/
