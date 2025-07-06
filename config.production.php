<?php

use Illuminate\Support\Str;

return [
    'baseUrl' => 'https://rbgvictoria.github.io/keybase-redevelopment',
    'production' => true,

    // DocSearch credentials
    'docsearchApiKey' => env('DOCSEARCH_KEY'),
    'docsearchIndexName' => env('DOCSEARCH_INDEX'),
    'url' => function ($page, $path) {
        return Str::startsWith($path, 'http') ? $path : $page->baseUrl . '/' . trimPath($path);
    },
];
