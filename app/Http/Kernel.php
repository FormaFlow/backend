<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $middleware = [];
    protected $middlewareGroups = [
        'web' => [],
        'api' => [],
    ];
    protected $middlewareAliases = [];
}
