<?php

declare(strict_types=1);

namespace Tests\Support;

use php_user_filter;

final class RejectMediaCleanupWritesFilter extends php_user_filter
{
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $consumed += $bucket->datalen;
        }

        return PSFS_ERR_FATAL;
    }
}
