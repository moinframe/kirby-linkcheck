# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer install              # Install dependencies
./vendor/bin/pest             # Run all tests
./vendor/bin/pest --filter="test name"  # Run a single test by name
npm run build                 # Build panel plugin (kirbyup)
npm run dev                   # Watch mode for panel development
```

## Architecture

Kirby CMS plugin (`moinframe/kirby-linkcheck`) that crawls a website and reports broken links. Panel section with Vue UI and a CLI command. No external dependencies beyond Kirby CMS.

**Flow:** Panel section → `POST /api/linkcheck/check` → `LinkChecker::check()` → `HtmlLinkExtractor::extract()` → `CrawlResult` objects → JSON response (+ cached). Synchronous — the crawl runs within the API request.

**CLI flow:** `kirby linkcheck [url]` → `LinkChecker::check()` → console output.

**Crawl strategy (LinkChecker):** Sequentially processes a queue of same-domain URLs using `Kirby\Http\Remote`. Internal pages are GET-crawled and their links extracted. External links are collected separately, then checked via HEAD requests (with GET fallback on 4xx). Connection failures produce status code `0`. Sitemap XML seeding is supported (including recursive `<sitemapindex>`). Accepts an injectable `httpClient` closure for testing.

**URL handling (HtmlLinkExtractor):** Uses PHP's native `DOMDocument` to find `a[href]` elements. Resolves relative URLs against the page's base URL, strips fragments, skips non-HTTP schemes (mailto/javascript/tel/data), deduplicates.

**Testing:** Pest v3 with injectable mock HTTP client. Tests live flat in `tests/` (no subdirectories). The `phpunit.xml` points the test suite at `tests/` and source coverage at `src/`.

## Key Details

- Plugin ID: `moinframe/kirby-linkcheck`
- Namespace: `Moinframe\Linkcheck\` → `src/`, `Tests\` → `tests/`
- PHP 8.3+ required, Kirby CMS ^5.3
- Same-domain detection compares link host against start URL host (exact match, no subdomain logic)
- `CrawlResult` is a readonly value object with `sourceUrl`, `linkedUrl`, `statusCode`
- Config prefix: `moinframe.linkcheck.*`
- Translation prefix: `moinframe.linkcheck.*`
