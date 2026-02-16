<?php

declare(strict_types=1);

return [
    'props' => [
        'label' => function (string $label = 'Link Checker') {
            return $label;
        },
        'defaultUrl' => function (?string $defaultUrl = null) {
            return $defaultUrl;
        },
        'defaultSitemap' => function (?string $defaultSitemap = null) {
            return $defaultSitemap;
        },
        'editable' => function (bool $editable = true) {
            return $editable;
        },
    ],
    'computed' => [
        'siteUrl' => function () {
            return $this->model()->site()->url();
        },
    ],
];
