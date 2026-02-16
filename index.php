<?php

declare(strict_types=1);

use Moinframe\Linkcheck\CrawlResult;
use Moinframe\Linkcheck\LinkChecker;
use Kirby\Cms\App;
use Kirby\Filesystem\F;

F::loadClasses([
    'Moinframe\\Linkcheck\\CrawlResult' => 'src/CrawlResult.php',
    'Moinframe\\Linkcheck\\LinkChecker' => 'src/LinkChecker.php',
    'Moinframe\\Linkcheck\\HtmlLinkExtractor' => 'src/HtmlLinkExtractor.php',
], __DIR__);

App::plugin('moinframe/kirby-linkcheck', [
    'options' => [
        'userAgent' => 'MoinframeLinkcheck/1.0',
        'timeout' => 10,
    ],
    'sections' => [
        'linkcheck' => require __DIR__ . '/sections/linkcheck.php',
    ],

    'commands' => [
        'linkcheck' => require __DIR__ . '/commands/linkcheck.php',
    ],

    'api' => [
        'routes' => [
            [
                'pattern' => 'moinframe-linkcheck/check',
                'method'  => 'POST',
                'action'  => function () {
                    $url = get('url');
                    $sitemap = get('sitemap');

                    if ($url === null || $url === '') {
                        $url = kirby()->site()->url();
                    }

                    if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                        throw new \Kirby\Exception\InvalidArgumentException('A valid URL is required');
                    }

                    $checker = new LinkChecker(
                        timeout: option('moinframe.linkcheck.timeout', 10),
                        userAgent: option('moinframe.linkcheck.userAgent', 'MoinframeLinkcheck/1.0'),
                    );

                    $sitemapUrl = is_string($sitemap) && $sitemap !== '' ? $sitemap : null;

                    try {
                        $results = $checker->check($url, $sitemapUrl);
                    } catch (\Throwable) {
                        throw new \Kirby\Exception\Exception(
                            t('moinframe.linkcheck.error.timeout') ?? 'The crawl was interrupted.' // @phpstan-ignore argument.type
                        );
                    }

                    $broken = array_filter(
                        $results,
                        fn(CrawlResult $r) => $r->statusCode >= 400 || $r->statusCode === 0
                    );

                    return [
                        'status'      => 'complete',
                        'totalLinks'  => count($results),
                        'brokenCount' => count($broken),
                        'results'     => array_map(fn(CrawlResult $r) => [
                            'sourceUrl'  => $r->sourceUrl,
                            'linkedUrl'  => $r->linkedUrl,
                            'statusCode' => $r->statusCode,
                        ], array_values($results)),
                    ];
                },
            ],
        ],
    ],

    'translations' => [
        'en' => [
            'moinframe.linkcheck.check'         => 'Check Links',
            'moinframe.linkcheck.checking'      => 'Crawling site, this may take a while...',
            'moinframe.linkcheck.summary'       => '{total} links checked, {broken} broken',
            'moinframe.linkcheck.col.status'    => 'Status',
            'moinframe.linkcheck.col.source'    => 'Source Page',
            'moinframe.linkcheck.col.link'      => 'Linked URL',
            'moinframe.linkcheck.empty'         => 'No results yet. Click "Check Links" to start.',
            'moinframe.linkcheck.noIssues'      => 'No broken links found.',
            'moinframe.linkcheck.url'           => 'URL',
            'moinframe.linkcheck.sitemap'       => 'Sitemap URL (optional)',
            'moinframe.linkcheck.error.url'     => 'Please enter a valid URL',
            'moinframe.linkcheck.error.timeout' => 'The crawl was interrupted. Try increasing max_execution_time in your PHP configuration.',
            'moinframe.linkcheck.success'       => 'Crawl complete',
        ],
        'de' => [
            'moinframe.linkcheck.check'         => 'Links prüfen',
            'moinframe.linkcheck.checking'      => 'Seite wird gecrawlt, das kann einen Moment dauern...',
            'moinframe.linkcheck.summary'       => '{total} Links geprüft, {broken} fehlerhaft',
            'moinframe.linkcheck.col.status'    => 'Status',
            'moinframe.linkcheck.col.source'    => 'Quellseite',
            'moinframe.linkcheck.col.link'      => 'Verlinkte URL',
            'moinframe.linkcheck.empty'         => 'Noch keine Ergebnisse. Klicke auf "Links prüfen" um zu starten.',
            'moinframe.linkcheck.noIssues'      => 'Keine fehlerhaften Links gefunden.',
            'moinframe.linkcheck.url'           => 'URL',
            'moinframe.linkcheck.sitemap'       => 'Sitemap-URL (optional)',
            'moinframe.linkcheck.error.url'     => 'Bitte eine gültige URL eingeben',
            'moinframe.linkcheck.error.timeout' => 'Der Crawl wurde unterbrochen. Versuche max_execution_time in der PHP-Konfiguration zu erhöhen.',
            'moinframe.linkcheck.success'       => 'Crawl abgeschlossen',
        ],
    ],
]);
