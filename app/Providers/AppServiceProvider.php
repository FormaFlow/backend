<?php

declare(strict_types=1);

namespace App\Providers;

use App\Policies\FormPolicy;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings moved to module-specific ServiceProviders
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
