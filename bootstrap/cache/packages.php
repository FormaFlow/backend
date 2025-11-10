<?php

declare(strict_types=1);

use Carbon\Laravel\ServiceProvider;
use Termwind\Laravel\TermwindServiceProvider;

return [
    'nesbot/carbon' =>
        [
            'providers' =>
                [
                    0 => ServiceProvider::class,
                ],
        ],
    'nunomaduro/termwind' =>
        [
            'providers' =>
                [
                    0 => TermwindServiceProvider::class,
                ],
        ],
];
