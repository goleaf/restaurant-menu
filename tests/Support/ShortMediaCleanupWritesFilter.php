<?php

declare(strict_types=1);

namespace Tests\Support;

use php_user_filter;

final class ShortMediaCleanupWritesFilter extends php_user_filter
{
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = substr($bucket->data, 0, 1);
            $bucket->datalen = 1;
            $consumed++;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
