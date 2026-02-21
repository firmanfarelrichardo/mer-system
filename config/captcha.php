<?php

return [
    /*
     | Set CAPTCHA_DISABLE=true in .env to bypass captcha entirely (e.g. local dev).
     | NEVER set to true in production.
     */
    'disable' => env('CAPTCHA_DISABLE', false),

    'characters' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O',
        'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd',
        'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's',
        't', 'u', 'v', 'w', 'x', 'y', 'z', 0, 1, 2, 3, 4, 5, 6, 7, 8, 9],

    /*
     | Resolved from the mews/captcha vendor package so no manual asset
     | copying is required. These paths are absolute and container-safe.
     */
    'fontsDirectory' => base_path('vendor/mews/captcha/assets/fonts'),
    'bgsDirectory'   => base_path('vendor/mews/captcha/assets/backgrounds'),
    'default' => [
        'length'     => 4,
        'width'      => 280,
        'height'     => 70,
        'quality'    => 100,
        'math'       => false,
        'expire'     => 60,
        'encrypt'    => false,
        'angle'      => 8,
        'lines'      => 2,
        'bgImage'    => false,
        'bgColor'    => '#eef2ff',
        'fontColors' => ['#1a237e', '#b71c1c', '#1b5e20', '#e65100', '#4a148c', '#006064'],
        'sharpen'    => 15,
        'blur'       => 0,
        'contrast'   => -5,
    ],
    'flat' => [
        'length'     => 6,
        'fontColors' => ['#2c3e50', '#c0392b', '#16a085', '#c0392b', '#8e44ad', '#303f9f', '#f57c00', '#795548'],
        'width'      => 345,
        'height'     => 65,
        'math'       => false,
        'quality'    => 100,
        'lines'      => 6,
        'bgImage'    => true,
        'bgColor'    => '#28faef',
        'contrast'   => 0,
    ],
    'mini' => [
        'length' => 3,
        'width'  => 60,
        'height' => 32,
    ],
    'inverse' => [
        'length'    => 5,
        'width'     => 120,
        'height'    => 36,
        'quality'   => 90,
        'sensitive' => true,
        'angle'     => 12,
        'sharpen'   => 10,
        'blur'      => 2,
        'invert'    => false,
        'contrast'  => -5,
    ],
    'math' => [
        'length'  => 9,
        'width'   => 120,
        'height'  => 36,
        'quality' => 90,
    ],
];
