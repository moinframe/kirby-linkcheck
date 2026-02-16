<?php

declare(strict_types=1);

use Moinframe\Linkcheck\CrawlResult;
use Moinframe\Linkcheck\LinkChecker;

/**
 * Create a mock HTTP response object with code(), content(), headers() methods.
 */
function mockResponse(?int $code, string $content = '', array $headers = []): object
{
    return new class ($code, $content, $headers) {
        public function __construct(
            private readonly ?int $code,
            private readonly string $content,
            private readonly array $headers,
        ) {}

        public function code(): ?int { return $this->code; }
        public function content(): string { return $this->content; }
        public function headers(): array { return $this->headers; }
    };
}

/**
 * Create a mock HTTP client closure from an array of sequential responses.
 */
function createMockClient(array $responses): \Closure
{
    $index = 0;

    return function (string $method, string $url, array $options) use ($responses, &$index) {
        $response = $responses[$index] ?? mockResponse(0);
        $index++;
        return $response;
    };
}

test('crawls same-domain links and checks external links', function () {
    $startPageHtml = '<html><body>
        <a href="https://example.com/about">About</a>
        <a href="https://external.com/page">External</a>
    </body></html>';

    $aboutPageHtml = '<html><body><p>About page with no links</p></body></html>';

    $client = createMockClient([
        // GET https://example.com (start page)
        mockResponse(200, $startPageHtml, ['Content-Type' => 'text/html']),
        // GET https://example.com/about (crawled same-domain)
        mockResponse(200, $aboutPageHtml, ['Content-Type' => 'text/html']),
        // HEAD https://external.com/page (external check)
        mockResponse(200),
    ]);

    $checker = new LinkChecker(httpClient: $client);
    $results = $checker->check('https://example.com');

    // Should have results for the links found on start page
    expect($results)->toHaveCount(2);

    $linkedUrls = array_map(fn(CrawlResult $r) => $r->linkedUrl, $results);
    expect($linkedUrls)->toContain('https://example.com/about')
        ->toContain('https://external.com/page');
});

test('connection errors produce status code 0', function () {
    $html = '<html><body>
        <a href="https://dead.example.com">Dead</a>
    </body></html>';

    $client = createMockClient([
        // GET start page
        mockResponse(200, $html, ['Content-Type' => 'text/html']),
        // HEAD external — connection failure (null code)
        mockResponse(null),
    ]);

    $checker = new LinkChecker(httpClient: $client);
    $results = $checker->check('https://example.com');

    $externalResults = array_values(array_filter($results, fn(CrawlResult $r) => str_contains($r->linkedUrl, 'dead.example.com')));

    expect($externalResults)->not->toBeEmpty();
    expect($externalResults[0]->statusCode)->toBe(0);
});

test('sitemap XML parsing seeds the queue', function () {
    $sitemapXml = '<?xml version="1.0" encoding="UTF-8"?>
        <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            <url><loc>https://example.com/page1</loc></url>
            <url><loc>https://example.com/page2</loc></url>
        </urlset>';

    $page1Html = '<html><body><p>Page 1</p></body></html>';
    $page2Html = '<html><body><p>Page 2</p></body></html>';

    $client = createMockClient([
        // GET sitemap
        mockResponse(200, $sitemapXml, ['Content-Type' => 'application/xml']),
        // GET start page (https://example.com)
        mockResponse(200, '<html><body></body></html>', ['Content-Type' => 'text/html']),
        // GET page1
        mockResponse(200, $page1Html, ['Content-Type' => 'text/html']),
        // GET page2
        mockResponse(200, $page2Html, ['Content-Type' => 'text/html']),
    ]);

    $checker = new LinkChecker(httpClient: $client);
    $results = $checker->check('https://example.com', 'https://example.com/sitemap.xml');

    // The sitemap should have seeded pages 1 and 2 for crawling
    // No external links, so results will be empty (internal pages don't create results for themselves)
    // This test mainly verifies no errors occur during sitemap parsing
    expect($results)->toBeArray();
});

test('non-HTML responses are not parsed for links', function () {
    $client = createMockClient([
        // GET start page — returns JSON, not HTML
        mockResponse(200, '{"key": "value"}', ['Content-Type' => 'application/json']),
    ]);

    $checker = new LinkChecker(httpClient: $client);
    $results = $checker->check('https://example.com');

    expect($results)->toBeEmpty();
});
