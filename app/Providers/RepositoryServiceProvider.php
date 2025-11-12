<?php

declare(strict_types=1);

namespace App\Providers;

use FormaFlow\Forms\Domain\FormRepository;
use FormaFlow\Forms\Infrastructure\Persistence\EloquentFormRepository;
use FormaFlow\Users\Domain\UserRepository;
use FormaFlow\Users\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\ServiceProvider;

final class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FormRepository::class, EloquentFormRepository::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
    }
}
