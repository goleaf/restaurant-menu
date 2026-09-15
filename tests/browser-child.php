<?php

declare(strict_types=1);

if (posix_setsid() < 0 || count($argv) < 2) {
    fwrite(STDERR, "Cannot create an isolated browser test process.\n");
    exit(1);
}
pcntl_exec($argv[1], array_slice($argv, 2));
fwrite(STDERR, "Cannot execute browser test process.\n");
exit(1);
