<?php

declare(strict_types=1);

namespace Moinframe\Linkcheck;

readonly class CrawlResult
{
    public function __construct(
        public string $sourceUrl,
        public string $linkedUrl,
        public int $statusCode,
    ) {}
}
