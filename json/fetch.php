<?php

$url = 'https://review.typo3.org/changes/?'
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

$contents = file_get_contents($url);
$contents = preg_replace('@^\)\]\}\'\n@imsU', '', $contents);
$fp = fopen('changes.json', 'wb');
$bytes = fwrite($fp, $contents);
fclose($fp);

echo $bytes . " Bytes written.\n";
