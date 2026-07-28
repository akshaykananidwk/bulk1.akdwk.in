<?php

/**
 * Paths the GitHub updater must NEVER overwrite or delete.
 * Merged with update.json "protect" entries at update time.
 */
return [
    'config/config.php',
    '.env',
    'installed.lock',
    'uploads/',
    'storage/',
    'assets/custom/',
];
