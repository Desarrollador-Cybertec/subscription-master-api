<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Action Definitions
    |--------------------------------------------------------------------------
    |
    | Maps product actions to their metrics and limits for authorization.
    | - requires_active: blocks the action when subscription is expired
    | - metric: the usage metric to check (null = status-only check)
    | - limit_key: the key in installation_limits to compare against
    | - periodic: whether the metric resets monthly
    |
    */

    'actions' => [

        'sintyc' => [
            'create_user' => [
                'requires_active' => true,
                'metric' => 'users_active',
                'limit_key' => 'max_users',
                'periodic' => false,
            ],
            'reactivate_user' => [
                'requires_active' => true,
                'metric' => 'users_active',
                'limit_key' => 'max_users',
                'periodic' => false,
            ],
            'create_area' => [
                'requires_active' => true,
                'metric' => null,
                'limit_key' => null,
                'periodic' => false,
            ],
        ],

        'chronology' => [
            'run_execution' => [
                'requires_active' => true,
                'metric' => 'executions',
                'limit_key' => 'executions_per_month',
                'periodic' => true,
            ],
            'run_import' => [
                'requires_active' => true,
                'metric' => 'executions',
                'limit_key' => 'executions_per_month',
                'periodic' => true,
            ],
            'run_report' => [
                'requires_active' => true,
                'metric' => 'executions',
                'limit_key' => 'executions_per_month',
                'periodic' => true,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Metric Aliases
    |--------------------------------------------------------------------------
    |
    | Normalize incoming metric names to canonical forms.
    |
    */

    'metric_aliases' => [
        'execution' => 'executions',
        'user_active' => 'users_active',
        'user' => 'users_active',
    ],

];
