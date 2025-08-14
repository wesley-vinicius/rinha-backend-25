<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

use function Hyperf\Support\env;

return [
    'default' => [
        'host' => 'redis',
        'port' => 6379,
        'db' =>  0,
        'pool' => [
            'min_connections' => 400,
            'max_connections' => 600,
            'connect_timeout' => 10.0,
            'wait_timeout' => 4.0,
            'heartbeat' => -1,
            'max_idle_time' => 60,
        ],
    ],
];
