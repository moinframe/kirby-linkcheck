<?php

declare(strict_types=1);

use Moinframe\Linkcheck\CrawlResult;
use Moinframe\Linkcheck\LinkChecker;
use Kirby\CLI\CLI;

return [
    'description' => 'Check a website for broken links',
    'args' => [
        'url' => [
            'description' => 'URL to check, or sitemap URL (ending in .xml)',
        ],
        'sitemap' => [
            'description' => 'Sitemap URL (when first arg is the site URL)',
            'defaultValue' => null,
        ],
    ],
    'command' => static function (CLI $cli): void {
        $first = $cli->arg('url');
        $second = $cli->arg('sitemap');

        if (!is_string($first) || $first === '') {
            $cli->error('Please provide a URL or sitemap URL.');
            return;
        }

        $sitemapUrl = is_string($second) && $second !== '' ? $second : null;
        $url = $first;

        // If only one arg given and it ends with .xml, treat it as a sitemap
        if ($sitemapUrl === null && str_ends_with(strtolower($url), '.xml')) {
            $sitemapUrl = $url;
            $parts = parse_url($sitemapUrl);
            if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $url = $parts['scheme'] . '://' . $parts['host'] . $port;
            }
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $cli->error("Invalid URL: {$url}");
            return;
        }

        $cli->out("Checking links on: {$url}");
        if ($sitemapUrl !== null) {
            $cli->out("Using sitemap: {$sitemapUrl}");
        }
        $cli->out('');

        $checker = new LinkChecker(
            timeout: 10,
            userAgent: 'MoinframeLinkcheck/1.0',
            verbose: true,
        );

        $results = $checker->check($url, $sitemapUrl);

        $broken = array_filter(
            $results,
            fn(CrawlResult $r) => $r->statusCode >= 400 || $r->statusCode === 0
        );

        $cli->out('');
        $cli->success(sprintf(
            'Done. %d links checked, %d broken.',
            count($results),
            count($broken)
        ));

        if (!empty($broken)) {
            $cli->out('');
            $cli->out('Broken links:');
            foreach ($broken as $result) {
                $cli->out(sprintf(
                    '  [%d] %s → %s',
                    $result->statusCode,
                    $result->sourceUrl,
                    $result->linkedUrl
                ));
            }
        }
    },
];
