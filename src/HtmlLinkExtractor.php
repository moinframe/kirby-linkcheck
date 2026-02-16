<?php

declare(strict_types=1);

namespace Moinframe\Linkcheck;

class HtmlLinkExtractor
{
    private const SKIP_SCHEMES = ['mailto:', 'javascript:', 'tel:', 'data:'];

    /**
     * Extract and normalize all link hrefs from HTML.
     *
     * @return string[]
     */
    public function extract(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);

        $urls = [];

        foreach ($dom->getElementsByTagName('a') as $node) {
            $href = trim($node->getAttribute('href'));

            if ($href === '' || $href === '#') {
                continue;
            }

            $skip = false;
            foreach (self::SKIP_SCHEMES as $scheme) {
                if (str_starts_with(strtolower($href), $scheme)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $resolved = $this->resolveUrl($href, $baseUrl);

            if ($resolved !== null) {
                $urls[] = $resolved;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Resolve a potentially relative URL against a base URL and strip fragments.
     */
    private function resolveUrl(string $url, string $baseUrl): ?string
    {
        // Strip fragment
        $url = preg_replace('/#.*$/', '', $url) ?? '';

        if ($url === '') {
            return null;
        }

        // Already absolute
        if (preg_match('#^https?://#i', $url) === 1) {
            return $this->normalizeUrl($url);
        }

        // Protocol-relative
        if (str_starts_with($url, '//')) {
            $baseParts = parse_url($baseUrl);
            $scheme = is_array($baseParts) ? ($baseParts['scheme'] ?? 'https') : 'https';
            return $this->normalizeUrl($scheme . ':' . $url);
        }

        $baseParts = parse_url($baseUrl);
        if (!is_array($baseParts) || !isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        $scheme = $baseParts['scheme'];
        $host = $baseParts['host'];
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';

        // Absolute path
        if (str_starts_with($url, '/')) {
            return $this->normalizeUrl("{$scheme}://{$host}{$port}{$url}");
        }

        // Relative path -- resolve against base directory
        $basePath = $baseParts['path'] ?? '/';
        $baseDir = substr($basePath, 0, (int) strrpos($basePath, '/') + 1);

        return $this->normalizeUrl("{$scheme}://{$host}{$port}{$baseDir}{$url}");
    }

    private function normalizeUrl(string $url): string
    {
        // Resolve . and .. in path
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['host'])) {
            return $url;
        }

        $path = $parts['path'] ?? '/';
        $path = $this->resolveDots($path);

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }

    private function resolveDots(string $path): string
    {
        $segments = explode('/', $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($resolved);
            } elseif ($segment !== '.') {
                $resolved[] = $segment;
            }
        }

        $result = implode('/', $resolved);
        if (!str_starts_with($result, '/')) {
            $result = '/' . $result;
        }

        return $result;
    }
}
