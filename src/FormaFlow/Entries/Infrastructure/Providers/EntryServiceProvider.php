<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Providers;

use FormaFlow\Entries\Application\Create\CreateEntryCommandHandler;
use FormaFlow\Entries\Application\Import\ImportEntriesCommandHandler;
use FormaFlow\Entries\Application\Stats\GetEntriesStatsQueryHandler;
use FormaFlow\Entries\Application\Update\UpdateEntryCommandHandler;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Entries\Infrastructure\Persistence\EloquentEntryRepository;
use Illuminate\Support\ServiceProvider;

final class EntryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EntryRepository::class, EloquentEntryRepository::class);

        $this->app->singleton(CreateEntryCommandHandler::class);
        $this->app->singleton(UpdateEntryCommandHandler::class);
        $this->app->singleton(GetEntriesStatsQueryHandler::class);
        $this->app->singleton(ImportEntriesCommandHandler::class);
    }
}
