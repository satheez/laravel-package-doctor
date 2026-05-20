<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master Enable Switch
    |--------------------------------------------------------------------------
    */
    'enabled' => env('PACKAGE_DOCTOR_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Project Paths
    |--------------------------------------------------------------------------
    */
    'project' => [
        'base_path' => base_path(),
        'composer_json_path' => base_path('composer.json'),
        'composer_lock_path' => base_path('composer.lock'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dependency Scan Scope
    |--------------------------------------------------------------------------
    */
    'scan' => [
        'include_direct' => true,
        'include_dev' => env('PACKAGE_DOCTOR_INCLUDE_DEV', true),
        'include_transitive' => env('PACKAGE_DOCTOR_INCLUDE_TRANSITIVE', false),
        'exclude_packages' => [],
        'only_packages' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer Binary and Commands
    |--------------------------------------------------------------------------
    */
    'composer' => [
        'binary' => env('PACKAGE_DOCTOR_COMPOSER_BINARY', 'composer'),
        'timeout_seconds' => (int) env('PACKAGE_DOCTOR_COMPOSER_TIMEOUT', 120),
        'working_directory' => base_path(),
        'commands' => [
            'outdated' => [
                'enabled' => true,
                'arguments' => ['outdated', '--format=json', '--locked'],
            ],
            'audit' => [
                'enabled' => true,
                'arguments' => ['audit', '--format=json', '--locked'],
            ],
            'licenses' => [
                'enabled' => true,
                'arguments' => ['licenses', '--format=json'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External Metadata Collection
    |--------------------------------------------------------------------------
    */
    'metadata' => [
        'packagist' => [
            'enabled' => env('PACKAGE_DOCTOR_PACKAGIST_ENABLED', true),
            'base_url' => env('PACKAGE_DOCTOR_PACKAGIST_URL', 'https://packagist.org'),
            'repo_url' => env('PACKAGE_DOCTOR_PACKAGIST_REPO_URL', 'https://repo.packagist.org'),
            'timeout_seconds' => (int) env('PACKAGE_DOCTOR_PACKAGIST_TIMEOUT', 10),
            'user_agent' => env('PACKAGE_DOCTOR_USER_AGENT', 'LaravelPackageDoctor/1.0'),
        ],
        'github' => [
            'enabled' => env('PACKAGE_DOCTOR_GITHUB_ENABLED', true),
            'base_url' => env('PACKAGE_DOCTOR_GITHUB_API_URL', 'https://api.github.com'),
            'token' => env('PACKAGE_DOCTOR_GITHUB_TOKEN'),
            'timeout_seconds' => (int) env('PACKAGE_DOCTOR_GITHUB_TIMEOUT', 10),
            'fetch_latest_release' => true,
            'fetch_readme_presence' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('PACKAGE_DOCTOR_CACHE_ENABLED', true),
        'ttl_seconds' => (int) env('PACKAGE_DOCTOR_CACHE_TTL', 3600),
        'store' => env('PACKAGE_DOCTOR_CACHE_STORE'),
        'prefix' => 'package-doctor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring
    |--------------------------------------------------------------------------
    */
    'score' => [
        'minimum' => 0,
        'maximum' => 100,

        'deductions' => [
            'security_advisory' => -30,
            'abandoned' => -30,
            'repository_archived' => -25,
            'laravel_incompatible' => -20,
            'php_incompatible' => -20,
            'constraint_blocked' => -15,
            'no_release_18_months' => -15,
            'risky_license' => -15,
            'major_upgrade_available' => -10,
            'no_release_12_months' => -8,
            'low_downloads' => -5,
            'missing_documentation' => -5,
            'unknown_repository' => -3,
        ],

        'status_thresholds' => [
            'healthy' => 90,
            'watch' => 70,
            'risky' => 40,
            'critical' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Freshness Thresholds (months without a release)
    |--------------------------------------------------------------------------
    */
    'freshness' => [
        'watch_after_months_without_release' => 12,
        'risky_after_months_without_release' => 18,
        'critical_after_months_without_release' => 36,
    ],

    /*
    |--------------------------------------------------------------------------
    | Popularity Thresholds
    |--------------------------------------------------------------------------
    */
    'popularity' => [
        'low_downloads_threshold' => 1000,
        'very_low_downloads_threshold' => 100,
        'low_stars_threshold' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | License Classification
    |--------------------------------------------------------------------------
    */
    'licenses' => [
        'safe' => ['MIT', 'BSD-2-Clause', 'BSD-3-Clause', 'Apache-2.0', 'ISC'],
        'watch' => ['LGPL-2.1-only', 'LGPL-2.1-or-later', 'LGPL-3.0-only', 'LGPL-3.0-or-later'],
        'risky' => ['GPL-2.0-only', 'GPL-2.0-or-later', 'GPL-3.0-only', 'GPL-3.0-or-later', 'AGPL-3.0-only', 'AGPL-3.0-or-later'],
        'unknown_license_is_risky' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | CI Mode
    |--------------------------------------------------------------------------
    */
    'ci' => [
        'minimum_project_score' => (int) env('PACKAGE_DOCTOR_CI_MIN_SCORE', 60),
        'fail_on_statuses' => ['critical'],
        'fail_on_security_advisories' => true,
        'fail_on_abandoned_direct_dependencies' => true,
        'fail_on_laravel_incompatible_direct_dependencies' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    */
    'output' => [
        'default_format' => env('PACKAGE_DOCTOR_OUTPUT', 'table'),
        'show_summary' => true,
        'show_top_issues' => true,
        'show_recommendations' => true,
        'show_transitive_by_default' => true,
        'max_issues_per_package' => 5,
        'truncate_package_names' => false,
        'table_style' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore
    |--------------------------------------------------------------------------
    */
    'ignore' => [
        // 'vendor/package' => 'reason'
        'packages' => [],

        // 'vendor/package' => ['code' => 'reason']
        'issues' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Checks (reserved for v2)
    |--------------------------------------------------------------------------
    */
    'custom_checks' => [],

];
