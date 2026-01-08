<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Providers;

use FormaFlow\Forms\Application\AddField\AddFieldCommandHandler;
use FormaFlow\Forms\Application\Create\CreateFormCommandHandler;
use FormaFlow\Forms\Application\Publish\PublishFormCommandHandler;
use FormaFlow\Forms\Application\Update\UpdateFormCommandHandler;
use FormaFlow\Forms\Domain\FormRepository;
use FormaFlow\Forms\Infrastructure\Persistence\EloquentFormRepository;
use Illuminate\Support\ServiceProvider;

final class FormServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FormRepository::class, EloquentFormRepository::class);

        $this->app->singleton(CreateFormCommandHandler::class);
        $this->app->singleton(PublishFormCommandHandler::class);
        $this->app->singleton(UpdateFormCommandHandler::class);
        $this->app->singleton(AddFieldCommandHandler::class);
    }
}
