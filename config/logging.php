<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channels that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => config('app.debug') ? 'stack-debug' : 'stack-standard',

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channels that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [

        //
        // Stacks
        //

        'stack-standard' => [ // standard application stack
            'driver' => 'stack',
            'channels' => ['daily-application', 'logtail'],
            'ignore_exceptions' => false,
        ],

        'stack-debug' => [ // for when debug mode is enabled
            'driver' => 'stack',
            'channels' => ['daily-application', 'debug'],
            'ignore_exceptions' => false,
        ],


        //
        // Channels
        //

        'single-application' => [
            'driver' => 'single',
            'path' => storage_path('logs/application.log'),
            'level' => 'debug',
        ],


        'daily-application' => [
            'driver' => 'daily',
            'path' => storage_path('logs/application.log'),
            'level' => 'warning',
            'days' => 30,
        ],
        'daily-database' => [ // channels utilised in AppServiceProvider.php -- not used in the default log channels
            'driver' => 'daily',
            'path' => storage_path('logs/database.log'),
            'level' => 'debug',
            'days' => 30,
        ],

        'debug' => [
            'driver' => 'daily',
            'path' => storage_path('logs/application-debug.log'),
            'level' => 'debug',
            'days' => 7,
        ],

        'runtime' => [
            'driver' => 'single',
            'path' => storage_path('logs/application-runtime.log'),
            'level' => 'info',
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Billboard I/O Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'error'),
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];
