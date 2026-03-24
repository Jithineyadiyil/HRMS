<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DomPDF Configuration
    |--------------------------------------------------------------------------
    */
    'show_warnings'   => false,
    'orientation'     => 'portrait',
    'defines'         => [
        // Allow loading local file:// images (logo, etc.)
        'enable_remote'           => true,
        'enable_html5_parser'     => true,
        'enable_css_float'        => true,
        'enable_font_subsetting'  => true,
        'pdf_backend'             => 'CPDF',
        'default_media_type'      => 'screen',
        'default_paper_size'      => 'a4',
        'default_paper_orientation' => 'portrait',
        'default_font'            => 'DejaVu Sans',
        'dpi'                     => 150,
        'font_height_ratio'       => 1.1,
        'is_php_enabled'          => false,
        'is_strong_mode'          => false,
        'is_javascript_enabled'   => false,
        'debugPng'                => false,
        'debugKeepTemp'           => false,
        'debugCss'                => false,
        'debugLayout'             => false,
        'debugLayoutLines'        => true,
        'debugLayoutBlocks'       => true,
        'debugLayoutInline'       => true,
        'debugLayoutPaddingBox'   => true,
        'pdf_unicode'             => true,
        'temp_dir'                => sys_get_temp_dir(),
        'chroot'                  => realpath(base_path()),
        'log_output_file'         => null,
        'allowed_remote_hosts'    => null,
        'allowed_protocols'       => [
            'file://' => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ],
    ],
];
