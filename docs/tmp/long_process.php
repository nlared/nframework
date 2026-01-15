<?php
// A dummy long running process
for ($i = 1; $i <= 20; $i++) {
    echo "Progress: Step $i of 20 - " . date('H:i:s') . "\n";
    sleep(1);
}
echo "Process Complete!\n";
