<?php

declare(strict_types=1);

use Moinframe\Linkcheck\HtmlLinkExtractor;

beforeEach(function () {
    $this->extractor = new HtmlLinkExtractor();
});

test('extracts absolute links from HTML', function () {
    $html = '<html><body>
        <a href="https://example.com/page1">Page 1</a>
        <a href="https://example.com/page2">Page 2</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe([
        'https://example.com/page1',
        'https://example.com/page2',
    ]);
});

test('resolves relative URLs against base', function () {
    $html = '<html><body>
        <a href="/about">About</a>
        <a href="contact">Contact</a>
        <a href="../other">Other</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com/pages/index.html');

    expect($links)->toContain('https://example.com/about')
        ->toContain('https://example.com/pages/contact')
        ->toContain('https://example.com/other');
});

test('skips mailto scheme', function () {
    $html = '<html><body>
        <a href="mailto:test@example.com">Email</a>
        <a href="https://example.com/real">Real</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe(['https://example.com/real']);
});

test('skips javascript scheme', function () {
    $html = '<html><body>
        <a href="javascript:void(0)">Click</a>
        <a href="https://example.com/real">Real</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe(['https://example.com/real']);
});

test('skips tel scheme', function () {
    $html = '<html><body>
        <a href="tel:+1234567890">Call</a>
        <a href="https://example.com/real">Real</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe(['https://example.com/real']);
});

test('skips data scheme', function () {
    $html = '<html><body>
        <a href="data:text/html,<h1>Hi</h1>">Data</a>
        <a href="https://example.com/real">Real</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe(['https://example.com/real']);
});

test('strips fragment identifiers', function () {
    $html = '<html><body>
        <a href="https://example.com/page#section">Page</a>
        <a href="/about#top">About</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe([
        'https://example.com/page',
        'https://example.com/about',
    ]);
});

test('returns unique URLs only', function () {
    $html = '<html><body>
        <a href="https://example.com/page">Page</a>
        <a href="https://example.com/page">Page Again</a>
        <a href="https://example.com/page#section">Page With Fragment</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe(['https://example.com/page']);
});

test('handles empty href and hash-only href', function () {
    $html = '<html><body>
        <a href="">Empty</a>
        <a href="#">Hash</a>
        <a href="https://example.com/real">Real</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe(['https://example.com/real']);
});

test('handles protocol-relative URLs', function () {
    $html = '<html><body>
        <a href="//cdn.example.com/resource">CDN</a>
    </body></html>';

    $links = $this->extractor->extract($html, 'https://example.com');

    expect($links)->toBe(['https://cdn.example.com/resource']);
});
