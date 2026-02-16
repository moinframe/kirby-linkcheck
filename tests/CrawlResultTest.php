<?php

declare(strict_types=1);

use Moinframe\Linkcheck\CrawlResult;

test('properties are accessible', function () {
    $result = new CrawlResult('https://a.com', 'https://b.com', 404);

    expect($result->sourceUrl)->toBe('https://a.com')
        ->and($result->linkedUrl)->toBe('https://b.com')
        ->and($result->statusCode)->toBe(404);
});
