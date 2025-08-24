<?php

return [
    'managers' => [
        'default' => [
            'dev'        => env('APP_DEBUG', false),
            'meta'       => env('DOCTRINE_METADATA', 'annotations'),
            'connection' => env('DB_CONNECTION', 'mysql'),
            'namespaces' => [
                'App\Entities'
            ],
            'paths' => [
                base_path('app/Entities')
            ],
            'repository' => Doctrine\ORM\EntityRepository::class,
            'proxies' => [
                'namespace'     => 'App\Proxies',
                'path'          => storage_path('proxies'),
                'auto_generate' => env('DOCTRINE_PROXY_AUTOGENERATE', false)
            ],
            'events' => [
                'listeners' => [],
                'subscribers' => []
            ],
            'filters' => [],
            'mapping_types' => []
        ]
    ],
    'extensions' => [
        LaravelDoctrine\Extensions\Timestamps\TimestampableExtension::class,
        LaravelDoctrine\Extensions\SoftDeletes\SoftDeleteableExtension::class,
        LaravelDoctrine\Extensions\Loggable\LoggableExtension::class,
        LaravelDoctrine\Extensions\Blameable\BlameableExtension::class,
        LaravelDoctrine\Extensions\Sluggable\SluggableExtension::class,
        LaravelDoctrine\Extensions\Translatable\TranslatableExtension::class,
        LaravelDoctrine\Extensions\Tree\TreeExtension::class,
    ],
    'custom_types' => [],
    'custom_datetime_functions' => [],
    'custom_numeric_functions' => [],
    'custom_string_functions' => [],
    'logger' => env('DOCTRINE_LOGGER', false),
    'cache' => [
        'second_level' => false,
        'default' => env('DOCTRINE_CACHE', 'array'),
        'namespace' => null,
        'type' => 'array'
    ],
    'gedmo' => [
        'all_mappings' => false
    ],
    'doctrine_presence_verifier' => true,
    'notifications' => [
        'channel' => 'database'
    ]
];