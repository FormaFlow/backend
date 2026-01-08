<?php

declare(strict_types=1);

namespace FormaFlow\Users\Infrastructure\Providers;

use FormaFlow\Users\Domain\UserRepository;
use FormaFlow\Users\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

final class UserServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UserRepository::class, EloquentUserRepository::class);
    }
}
