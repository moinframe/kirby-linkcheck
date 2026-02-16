<?php

declare(strict_types=1);

namespace Moinframe\Linkcheck;

use Kirby\Http\Remote;

class LinkChecker
{
    /** @var CrawlResult[] */
    private array $results = [];

    /** @var array<string, true> */
    private array $seen = [];

    /** @var string[] */
    private array $crawlQueue = [];

    /** @var array{source: string, url: string}[] */
    private array $externalLinks = [];

    /** @var array<string, true> */
    private array $seenExternal = [];

    private string $baseDomain;
    private HtmlLinkExtractor $extractor;

    /**
     * @var \Closure(string $method, string $url, array<string, mixed> $options): object|null
     *     Custom HTTP client callable. Must return an object with code(), content(), headers().
     *     Defaults to Kirby\Http\Remote when null.
     */
    private ?\Closure $httpClient;

    public function __construct(
        private readonly int $timeout = 10,
        private readonly string $userAgent = 'MoinframeLinkcheck/1.0',
        private readonly bool $verbose = false,
        ?\Closure $httpClient = null,
    ) {
        $this->extractor = new HtmlLinkExtractor();
        $this->httpClient = $httpClient;
    }

    /**
     * Run the link checker starting from the given URL.
     *
     * @return CrawlResult[]
     */
    public function check(string $startUrl, ?string $sitemapUrl = null): array
    {
        $parsed = parse_url($startUrl);
        $this->baseDomain = strtolower(is_array($parsed) ? ($parsed['host'] ?? '') : '');

        $this->addToCrawlQueue($startUrl);

        if ($sitemapUrl !== null) {
            $this->seedFromSitemap($sitemapUrl);
        }

        $this->crawlInternalPages();
        $this->checkExternalLinks();

        return $this->results;
    }

    /**
     * Perform an HTTP request using either the injected client or Kirby Remote.
     *
     * @return object&\Kirby\Http\Remote
     */
    private function request(string $method, string $url): object
    {
        if ($this->httpClient !== null) {
            /** @var object&\Kirby\Http\Remote */
            return ($this->httpClient)($method, $url, [
                'timeout' => $this->timeout,
                'headers' => ['User-Agent' => $this->userAgent],
            ]);
        }

        $options = [
            'timeout' => $this->timeout,
            'headers' => ['User-Agent' => $this->userAgent],
        ];

        return match (strtoupper($method)) {
            'HEAD' => new Remote($url, array_merge($options, ['method' => 'HEAD'])),
            default => Remote::get($url, $options),
        };
    }

    private function crawlInternalPages(): void
    {
        while ($this->crawlQueue !== []) {
            $url = array_shift($this->crawlQueue);

            $response = null;
            try {
                $response = $this->request('GET', $url);
                $statusCode = $response->code() ?? 0;
            } catch (\Throwable) {
                $statusCode = 0;
            }

            if ($statusCode === 0) {
                $this->log("  [0] {$url} (connection failed)");
                continue;
            }

            $this->log("  [{$statusCode}] {$url}");

            /** @var object&\Kirby\Http\Remote $response */
            $headers = $response->headers();
            $contentType = $headers['content-type'] ?? $headers['Content-Type'] ?? '';
            if ($statusCode >= 200 && $statusCode < 300 && str_contains($contentType, 'text/html')) {
                $html = $response->content() ?? '';
                $links = $this->extractor->extract($html, $url);

                foreach ($links as $linkedUrl) {
                    if ($this->isSameDomain($linkedUrl)) {
                        $this->results[] = new CrawlResult($url, $linkedUrl, $statusCode);
                        $this->addToCrawlQueue($linkedUrl);
                    } else {
                        $this->addToExternalQueue($url, $linkedUrl);
                    }
                }
            }
        }
    }

    private function checkExternalLinks(): void
    {
        if ($this->externalLinks === []) {
            return;
        }

        $this->log("\nChecking " . count($this->externalLinks) . " external links...");

        /** @var array{source: string, url: string}[] $retryWithGet */
        $retryWithGet = [];

        foreach ($this->externalLinks as $link) {
            try {
                $response = $this->request('HEAD', $link['url']);
                $statusCode = $response->code() ?? 0;
            } catch (\Throwable) {
                $statusCode = 0;
            }

            if ($statusCode === 0) {
                $this->log("  [0] {$link['url']} (connection failed)");
                $this->results[] = new CrawlResult($link['source'], $link['url'], 0);
                continue;
            }

            // HEAD is unreliable on many servers -- retry any 4xx with GET
            if ($statusCode >= 400 && $statusCode < 500) {
                $retryWithGet[] = $link;
                continue;
            }

            $this->log("  [{$statusCode}] {$link['url']}");
            $this->results[] = new CrawlResult($link['source'], $link['url'], $statusCode);
        }

        // Retry 4xx with GET
        if ($retryWithGet !== []) {
            $this->log("\nRetrying " . count($retryWithGet) . " links with GET (HEAD returned 4xx)...");

            foreach ($retryWithGet as $link) {
                try {
                    $response = $this->request('GET', $link['url']);
                    $statusCode = $response->code() ?? 0;
                } catch (\Throwable) {
                    $statusCode = 0;
                }

                if ($statusCode === 0) {
                    $this->log("  [0] {$link['url']} (connection failed)");
                } else {
                    $this->log("  [{$statusCode}] {$link['url']}");
                }

                $this->results[] = new CrawlResult($link['source'], $link['url'], $statusCode);
            }
        }
    }

    private function seedFromSitemap(string $sitemapUrl): void
    {
        $this->log("Fetching sitemap: {$sitemapUrl}");

        try {
            $response = $this->request('GET', $sitemapUrl);
            $xml = @simplexml_load_string($response->content() ?? '');

            if ($xml === false) {
                $this->log("  Failed to parse sitemap XML");
                return;
            }

            // Handle sitemapindex (recursive)
            if (isset($xml->sitemap)) {
                foreach ($xml->sitemap as $sitemap) {
                    $loc = (string) ($sitemap->loc ?? '');
                    if ($loc !== '') {
                        $this->seedFromSitemap($loc);
                    }
                }
            }

            // Handle urlset
            if (isset($xml->url)) {
                foreach ($xml->url as $urlEntry) {
                    $loc = (string) ($urlEntry->loc ?? '');
                    if ($loc !== '' && $this->isSameDomain($loc)) {
                        $this->addToCrawlQueue($loc);
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->log("  Failed to fetch sitemap: {$e->getMessage()}");
        }
    }

    private function isSameDomain(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return strtolower(is_string($host) ? $host : '') === $this->baseDomain;
    }

    private function addToCrawlQueue(string $url): void
    {
        if (isset($this->seen[$url])) {
            return;
        }
        $this->seen[$url] = true;
        $this->crawlQueue[] = $url;
        $this->log("Queued: {$url}");
    }

    private function addToExternalQueue(string $source, string $url): void
    {
        if (isset($this->seenExternal[$url])) {
            return;
        }
        $this->seenExternal[$url] = true;
        $this->externalLinks[] = ['source' => $source, 'url' => $url];
    }

    private function log(string $message): void
    {
        if ($this->verbose) {
            fwrite(STDERR, $message . "\n");
        }
    }
}
