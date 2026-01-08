<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
    Laravel\Tinker\TinkerServiceProvider::class,
    Laravel\Sanctum\SanctumServiceProvider::class,
    FormaFlow\Forms\Infrastructure\Providers\FormServiceProvider::class,
    FormaFlow\Entries\Infrastructure\Providers\EntryServiceProvider::class,
    FormaFlow\Users\Infrastructure\Providers\UserServiceProvider::class,
];
