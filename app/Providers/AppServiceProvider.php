<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\FormPolicy;
use FormaFlow\Entries\Application\Create\CreateEntryCommandHandler;
use FormaFlow\Entries\Application\Update\UpdateEntryCommandHandler;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Entries\Infrastructure\Persistence\EloquentEntryRepository;
use FormaFlow\Forms\Application\AddField\AddFieldCommandHandler;
use FormaFlow\Forms\Application\Create\CreateFormCommandHandler;
use FormaFlow\Forms\Application\Publish\PublishFormCommandHandler;
use FormaFlow\Forms\Domain\FormRepository;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Forms\Infrastructure\Persistence\EloquentFormRepository;
use FormaFlow\Users\Domain\UserRepository;
use FormaFlow\Users\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FormRepository::class, EloquentFormRepository::class);
        $this->app->singleton(UserRepository::class, EloquentUserRepository::class);
        $this->app->singleton(EntryRepository::class, EloquentEntryRepository::class);

        $this->app->singleton(CreateFormCommandHandler::class);
        $this->app->singleton(PublishFormCommandHandler::class);
        $this->app->singleton(AddFieldCommandHandler::class);
        $this->app->singleton(CreateEntryCommandHandler::class);
        $this->app->singleton(UpdateEntryCommandHandler::class);
    }

    public function boot(): void
    {
        Gate::policy(
            FormModel::class,
            FormPolicy::class
        );

        Builder::macro('insertGetStringId', function (array $values) {
            $id = $values['id'] ?? null;

            $this->insert($values);

            return $id;
        });
    }
}
