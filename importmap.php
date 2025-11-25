<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    'tom-select/dist/css/tom-select.default.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    'tom-select' => [
        'version' => '2.4.3',
    ],
    '@orchidjs/sifter' => [
        'version' => '1.1.0',
    ],
    '@orchidjs/unicode-variants' => [
        'version' => '1.1.2',
    ],
    'tom-select/dist/css/tom-select.default.min.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
    'tom-select/dist/css/tom-select.bootstrap4.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
    'tom-select/dist/css/tom-select.bootstrap5.css' => [
        'version' => '2.4.3',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '3.9.1',
    ],
    '@symfony/ux-live-component' => [
        'path' => './vendor/symfony/ux-live-component/assets/dist/live_controller.js',
    ],
    'lodash-es' => [
        'version' => '4.17.21',
    ],
    'parchment' => [
        'version' => '3.0.0',
    ],
    'eventemitter3' => [
        'version' => '5.0.1',
    ],
    'fast-diff' => [
        'version' => '1.3.0',
    ],
    'lodash.clonedeep' => [
        'version' => '4.5.0',
    ],
    'lodash.isequal' => [
        'version' => '4.5.0',
    ],
    'tw-elements' => [
        'version' => '2.0.0',
    ],
    'tw-elements/css/tw-elements.min.css' => [
        'version' => '2.0.0',
        'type' => 'css',
    ],
    'quill/dist/quill.snow.css' => [
        'version' => '2.0.3',
        'type' => 'css',
    ],
    'quill/dist/quill.bubble.css' => [
        'version' => '2.0.3',
        'type' => 'css',
    ],
    'quill' => [
        'version' => '2.0.0-dev.4',
    ],
    'quill-delta' => [
        'version' => '5.1.0',
    ],
    'quill-table-better' => [
        'version' => '1.2.3',
    ],
    'quill-table-better/dist/quill-table-better.css' => [
        'version' => '1.2.3',
        'type' => 'css',
    ],
    'axios' => [
        'version' => '1.13.2',
    ],
    'quill2-emoji' => [
        'version' => '0.1.2',
    ],
    'quill2-emoji/dist/style.css' => [
        'version' => '0.1.2',
        'type' => 'css',
    ],
    'quill-resize-image' => [
        'version' => '1.0.11',
    ],
    'quill-toggle-fullscreen-button' => [
        'version' => '0.1.4',
    ],
    'quill-html-edit-button' => [
        'version' => '3.0.0',
    ],
];
