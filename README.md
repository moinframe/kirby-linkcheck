# Kirby Linkcheck

A Kirby CMS plugin that crawls a website, discovers all links, and checks them for broken HTTP responses. Results are displayed in a Panel section or via the CLI.

The crawler follows all same-domain links to discover pages, then verifies every link it finds: both internal and external. It supports seeding from `sitemap.xml` files.

## Requirements

- PHP 8.3+
- Kirby CMS 5.3+
- [Kirby CLI](https://github.com/getkirby/cli) (optional, for the `kirby linkcheck` command)

## Installation

```bash
composer require moinframe/kirby-linkcheck
```

## Usage

There are two ways to use this plugin:

1. **Panel section**: add a section to any blueprint and check links directly from the Kirby Panel
2. **CLI command**: run `kirby linkcheck` from the terminal

## Panel Section

Add the section to any blueprint:

```yaml
sections:
  links:
    label: Check for broken links
    type: linkcheck
    defaultUrl: https://example.com # optional, defaults to site URL
    defaultSitemap: https://example.com/sitemap.xml # optional
    editable: false # optional, disables URL fields (default: true)
```

### How It Works

1. Enter a URL and click **Check Links**
2. The crawler runs and returns results directly
3. A results table shows all non-200 links with status, source page, and linked URL

## CLI Command

Check your site for broken links from the terminal:

```bash
# Check a URL
kirby linkcheck https://example.com

# Check a URL with a sitemap
kirby linkcheck https://example.com https://example.com/sitemap.xml

# Provide only a sitemap (base URL is derived from the sitemap host)
kirby linkcheck https://example.com/sitemap.xml
```

The command crawls the site, then prints a summary and lists all broken links:

```
Checking links on: https://example.com

  [404] https://example.com/about → https://example.com/missing-page
  [0] https://example.com/contact → https://dead-link.example.com

Done. 42 links checked, 2 broken.
```

Status code `0` means the connection failed entirely (DNS error, timeout, etc.).

### URL Handling

- Relative URLs are resolved against the page they were found on
- Fragment identifiers (`#section`) are stripped
- `mailto:`, `javascript:`, `tel:`, and `data:` schemes are skipped
- `.` and `..` path segments are resolved
- Protocol-relative URLs (`//cdn.example.com`) inherit the base scheme
- Duplicate URLs are deduplicated per page

### Same-Domain Detection

A link is considered same-domain if its host matches the start URL's host exactly. Subdomains are treated as external (e.g., if the start URL is `example.com`, links to `www.example.com` are external).

## PHP Configuration for Large Sites

For sites with many pages you may need to adjust your PHP configuration:

```ini
; php.ini or pool-specific config
max_execution_time = 300   ; Allow up to 5 minutes
memory_limit = 512M        ; Increase if errors occur
```

The CLI command is not affected by `max_execution_time` (PHP CLI has no time limit by default).

## License

MIT
