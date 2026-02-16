<?php

declare(strict_types=1);

test('section definition returns expected structure', function () {
    $section = require __DIR__ . '/../sections/linkcheck.php';

    expect($section)->toBeArray()
        ->toHaveKey('props')
        ->toHaveKey('computed');

    expect($section['props'])->toHaveKey('label')
        ->toHaveKey('defaultUrl')
        ->toHaveKey('defaultSitemap');

    expect($section['computed'])->toHaveKey('siteUrl');
});

test('section props return default values', function () {
    $section = require __DIR__ . '/../sections/linkcheck.php';

    expect($section['props']['label']('My Label'))->toBe('My Label');
    expect($section['props']['label']())->toBe('Link Checker');
    expect($section['props']['defaultUrl'](null))->toBeNull();
    expect($section['props']['defaultUrl']('https://example.com'))->toBe('https://example.com');
    expect($section['props']['defaultSitemap'](null))->toBeNull();
});
