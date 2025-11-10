<?php

use Carbon\Laravel\ServiceProvider;
use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Auth\Console\ClearResetsCommand;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\BroadcastServiceProvider;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\BusServiceProvider;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Cache\Console\CacheTableCommand;
use Illuminate\Cache\Console\ForgetCommand;
use Illuminate\Cache\Console\PruneStaleTagsCommand;
use Illuminate\Cache\RateLimiter;
use Illuminate\Concurrency\ConcurrencyManager;
use Illuminate\Concurrency\ConcurrencyServiceProvider;
use Illuminate\Concurrency\Console\InvokeSerializedClosureCommand;
use Illuminate\Console\Scheduling\ScheduleClearCacheCommand;
use Illuminate\Console\Scheduling\ScheduleFinishCommand;
use Illuminate\Console\Scheduling\ScheduleInterruptCommand;
use Illuminate\Console\Scheduling\ScheduleListCommand;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Console\Scheduling\ScheduleTestCommand;
use Illuminate\Console\Scheduling\ScheduleWorkCommand;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Contracts\Pipeline\Hub;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\Console\DbCommand;
use Illuminate\Database\Console\DumpCommand;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\InstallCommand;
use Illuminate\Database\Console\Migrations\MigrateCommand;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\Migrations\StatusCommand;
use Illuminate\Database\Console\PruneCommand;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Database\Console\Seeds\SeederMakeCommand;
use Illuminate\Database\Console\ShowCommand;
use Illuminate\Database\Console\ShowModelCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Foundation\Console\ApiInstallCommand;
use Illuminate\Foundation\Console\BroadcastingInstallCommand;
use Illuminate\Foundation\Console\CastMakeCommand;
use Illuminate\Foundation\Console\ChannelListCommand;
use Illuminate\Foundation\Console\ChannelMakeCommand;
use Illuminate\Foundation\Console\ClassMakeCommand;
use Illuminate\Foundation\Console\ClearCompiledCommand;
use Illuminate\Foundation\Console\ComponentMakeCommand;
use Illuminate\Foundation\Console\ConfigCacheCommand;
use Illuminate\Foundation\Console\ConfigClearCommand;
use Illuminate\Foundation\Console\ConfigPublishCommand;
use Illuminate\Foundation\Console\ConfigShowCommand;
use Illuminate\Foundation\Console\ConsoleMakeCommand;
use Illuminate\Foundation\Console\DocsCommand;
use Illuminate\Foundation\Console\DownCommand;
use Illuminate\Foundation\Console\EnumMakeCommand;
use Illuminate\Foundation\Console\EnvironmentCommand;
use Illuminate\Foundation\Console\EnvironmentDecryptCommand;
use Illuminate\Foundation\Console\EnvironmentEncryptCommand;
use Illuminate\Foundation\Console\EventCacheCommand;
use Illuminate\Foundation\Console\EventClearCommand;
use Illuminate\Foundation\Console\EventGenerateCommand;
use Illuminate\Foundation\Console\EventListCommand;
use Illuminate\Foundation\Console\EventMakeCommand;
use Illuminate\Foundation\Console\ExceptionMakeCommand;
use Illuminate\Foundation\Console\InterfaceMakeCommand;
use Illuminate\Foundation\Console\JobMakeCommand;
use Illuminate\Foundation\Console\JobMiddlewareMakeCommand;
use Illuminate\Foundation\Console\KeyGenerateCommand;
use Illuminate\Foundation\Console\LangPublishCommand;
use Illuminate\Foundation\Console\ListenerMakeCommand;
use Illuminate\Foundation\Console\MailMakeCommand;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Illuminate\Foundation\Console\NotificationMakeCommand;
use Illuminate\Foundation\Console\ObserverMakeCommand;
use Illuminate\Foundation\Console\OptimizeClearCommand;
use Illuminate\Foundation\Console\OptimizeCommand;
use Illuminate\Foundation\Console\PackageDiscoverCommand;
use Illuminate\Foundation\Console\PolicyMakeCommand;
use Illuminate\Foundation\Console\ProviderMakeCommand;
use Illuminate\Foundation\Console\RequestMakeCommand;
use Illuminate\Foundation\Console\ResourceMakeCommand;
use Illuminate\Foundation\Console\RouteCacheCommand;
use Illuminate\Foundation\Console\RouteClearCommand;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Foundation\Console\RuleMakeCommand;
use Illuminate\Foundation\Console\ScopeMakeCommand;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Foundation\Console\StorageLinkCommand;
use Illuminate\Foundation\Console\StorageUnlinkCommand;
use Illuminate\Foundation\Console\StubPublishCommand;
use Illuminate\Foundation\Console\TestMakeCommand;
use Illuminate\Foundation\Console\TraitMakeCommand;
use Illuminate\Foundation\Console\UpCommand;
use Illuminate\Foundation\Console\VendorPublishCommand;
use Illuminate\Foundation\Console\ViewCacheCommand;
use Illuminate\Foundation\Console\ViewClearCommand;
use Illuminate\Foundation\Console\ViewMakeCommand;
use Illuminate\Foundation\Providers\ConsoleSupportServiceProvider;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Mail\MailServiceProvider;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Console\NotificationTableCommand;
use Illuminate\Notifications\NotificationServiceProvider;
use Illuminate\Pagination\PaginationServiceProvider;
use Illuminate\Pipeline\PipelineServiceProvider;
use Illuminate\Queue\Console\BatchesTableCommand;
use Illuminate\Queue\Console\ClearCommand;
use Illuminate\Queue\Console\FailedTableCommand;
use Illuminate\Queue\Console\FlushFailedCommand;
use Illuminate\Queue\Console\ForgetFailedCommand;
use Illuminate\Queue\Console\ListenCommand;
use Illuminate\Queue\Console\ListFailedCommand;
use Illuminate\Queue\Console\MonitorCommand;
use Illuminate\Queue\Console\PruneBatchesCommand;
use Illuminate\Queue\Console\PruneFailedJobsCommand;
use Illuminate\Queue\Console\RestartCommand;
use Illuminate\Queue\Console\RetryBatchCommand;
use Illuminate\Queue\Console\RetryCommand;
use Illuminate\Queue\Console\TableCommand;
use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Redis\RedisServiceProvider;
use Illuminate\Routing\Console\ControllerMakeCommand;
use Illuminate\Routing\Console\MiddlewareMakeCommand;
use Illuminate\Session\Console\SessionTableCommand;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Termwind\Laravel\TermwindServiceProvider;

return [
    'providers' =>
        [
            AuthServiceProvider::class,
            BroadcastServiceProvider::class,
            BusServiceProvider::class,
            CacheServiceProvider::class,
            ConsoleSupportServiceProvider::class,
            ConcurrencyServiceProvider::class,
            CookieServiceProvider::class,
            DatabaseServiceProvider::class,
            EncryptionServiceProvider::class,
            FilesystemServiceProvider::class,
            FoundationServiceProvider::class,
            HashServiceProvider::class,
            MailServiceProvider::class,
            NotificationServiceProvider::class,
            PaginationServiceProvider::class,
            PasswordResetServiceProvider::class,
            PipelineServiceProvider::class,
            QueueServiceProvider::class,
            RedisServiceProvider::class,
            SessionServiceProvider::class,
            TranslationServiceProvider::class,
            ValidationServiceProvider::class,
            ViewServiceProvider::class,
            ServiceProvider::class,
            TermwindServiceProvider::class,
        ],
    'eager' =>
        [
            AuthServiceProvider::class,
            CookieServiceProvider::class,
            DatabaseServiceProvider::class,
            EncryptionServiceProvider::class,
            FilesystemServiceProvider::class,
            FoundationServiceProvider::class,
            NotificationServiceProvider::class,
            PaginationServiceProvider::class,
            SessionServiceProvider::class,
            ViewServiceProvider::class,
            ServiceProvider::class,
            TermwindServiceProvider::class,
        ],
    'deferred' =>
        [
            BroadcastManager::class => BroadcastServiceProvider::class,
            Factory::class => BroadcastServiceProvider::class,
            Broadcaster::class => BroadcastServiceProvider::class,
            \Illuminate\Bus\Dispatcher::class => BusServiceProvider::class,
            Dispatcher::class => BusServiceProvider::class,
            QueueingDispatcher::class => BusServiceProvider::class,
            BatchRepository::class => BusServiceProvider::class,
            DatabaseBatchRepository::class => BusServiceProvider::class,
            'cache' => CacheServiceProvider::class,
            'cache.store' => CacheServiceProvider::class,
            'cache.psr6' => CacheServiceProvider::class,
            'memcached.connector' => CacheServiceProvider::class,
            RateLimiter::class => CacheServiceProvider::class,
            AboutCommand::class => ConsoleSupportServiceProvider::class,
            \Illuminate\Cache\Console\ClearCommand::class => ConsoleSupportServiceProvider::class,
            ForgetCommand::class => ConsoleSupportServiceProvider::class,
            ClearCompiledCommand::class => ConsoleSupportServiceProvider::class,
            ClearResetsCommand::class => ConsoleSupportServiceProvider::class,
            ConfigCacheCommand::class => ConsoleSupportServiceProvider::class,
            ConfigClearCommand::class => ConsoleSupportServiceProvider::class,
            ConfigShowCommand::class => ConsoleSupportServiceProvider::class,
            DbCommand::class => ConsoleSupportServiceProvider::class,
            \Illuminate\Database\Console\MonitorCommand::class => ConsoleSupportServiceProvider::class,
            PruneCommand::class => ConsoleSupportServiceProvider::class,
            ShowCommand::class => ConsoleSupportServiceProvider::class,
            \Illuminate\Database\Console\TableCommand::class => ConsoleSupportServiceProvider::class,
            WipeCommand::class => ConsoleSupportServiceProvider::class,
            DownCommand::class => ConsoleSupportServiceProvider::class,
            EnvironmentCommand::class => ConsoleSupportServiceProvider::class,
            EnvironmentDecryptCommand::class => ConsoleSupportServiceProvider::class,
            EnvironmentEncryptCommand::class => ConsoleSupportServiceProvider::class,
            EventCacheCommand::class => ConsoleSupportServiceProvider::class,
            EventClearCommand::class => ConsoleSupportServiceProvider::class,
            EventListCommand::class => ConsoleSupportServiceProvider::class,
            InvokeSerializedClosureCommand::class => ConsoleSupportServiceProvider::class,
            KeyGenerateCommand::class => ConsoleSupportServiceProvider::class,
            OptimizeCommand::class => ConsoleSupportServiceProvider::class,
            OptimizeClearCommand::class => ConsoleSupportServiceProvider::class,
            PackageDiscoverCommand::class => ConsoleSupportServiceProvider::class,
            PruneStaleTagsCommand::class => ConsoleSupportServiceProvider::class,
            ClearCommand::class => ConsoleSupportServiceProvider::class,
            ListFailedCommand::class => ConsoleSupportServiceProvider::class,
            FlushFailedCommand::class => ConsoleSupportServiceProvider::class,
            ForgetFailedCommand::class => ConsoleSupportServiceProvider::class,
            ListenCommand::class => ConsoleSupportServiceProvider::class,
            MonitorCommand::class => ConsoleSupportServiceProvider::class,
            PruneBatchesCommand::class => ConsoleSupportServiceProvider::class,
            PruneFailedJobsCommand::class => ConsoleSupportServiceProvider::class,
            RestartCommand::class => ConsoleSupportServiceProvider::class,
            RetryCommand::class => ConsoleSupportServiceProvider::class,
            RetryBatchCommand::class => ConsoleSupportServiceProvider::class,
            WorkCommand::class => ConsoleSupportServiceProvider::class,
            RouteCacheCommand::class => ConsoleSupportServiceProvider::class,
            RouteClearCommand::class => ConsoleSupportServiceProvider::class,
            RouteListCommand::class => ConsoleSupportServiceProvider::class,
            DumpCommand::class => ConsoleSupportServiceProvider::class,
            SeedCommand::class => ConsoleSupportServiceProvider::class,
            ScheduleFinishCommand::class => ConsoleSupportServiceProvider::class,
            ScheduleListCommand::class => ConsoleSupportServiceProvider::class,
            ScheduleRunCommand::class => ConsoleSupportServiceProvider::class,
            ScheduleClearCacheCommand::class => ConsoleSupportServiceProvider::class,
            ScheduleTestCommand::class => ConsoleSupportServiceProvider::class,
            ScheduleWorkCommand::class => ConsoleSupportServiceProvider::class,
            ScheduleInterruptCommand::class => ConsoleSupportServiceProvider::class,
            ShowModelCommand::class => ConsoleSupportServiceProvider::class,
            StorageLinkCommand::class => ConsoleSupportServiceProvider::class,
            StorageUnlinkCommand::class => ConsoleSupportServiceProvider::class,
            UpCommand::class => ConsoleSupportServiceProvider::class,
            ViewCacheCommand::class => ConsoleSupportServiceProvider::class,
            ViewClearCommand::class => ConsoleSupportServiceProvider::class,
            ApiInstallCommand::class => ConsoleSupportServiceProvider::class,
            BroadcastingInstallCommand::class => ConsoleSupportServiceProvider::class,
            CacheTableCommand::class => ConsoleSupportServiceProvider::class,
            CastMakeCommand::class => ConsoleSupportServiceProvider::class,
            ChannelListCommand::class => ConsoleSupportServiceProvider::class,
            ChannelMakeCommand::class => ConsoleSupportServiceProvider::class,
            ClassMakeCommand::class => ConsoleSupportServiceProvider::class,
            ComponentMakeCommand::class => ConsoleSupportServiceProvider::class,
            ConfigPublishCommand::class => ConsoleSupportServiceProvider::class,
            ConsoleMakeCommand::class => ConsoleSupportServiceProvider::class,
            ControllerMakeCommand::class => ConsoleSupportServiceProvider::class,
            DocsCommand::class => ConsoleSupportServiceProvider::class,
            EnumMakeCommand::class => ConsoleSupportServiceProvider::class,
            EventGenerateCommand::class => ConsoleSupportServiceProvider::class,
            EventMakeCommand::class => ConsoleSupportServiceProvider::class,
            ExceptionMakeCommand::class => ConsoleSupportServiceProvider::class,
            FactoryMakeCommand::class => ConsoleSupportServiceProvider::class,
            InterfaceMakeCommand::class => ConsoleSupportServiceProvider::class,
            JobMakeCommand::class => ConsoleSupportServiceProvider::class,
            JobMiddlewareMakeCommand::class => ConsoleSupportServiceProvider::class,
            LangPublishCommand::class => ConsoleSupportServiceProvider::class,
            ListenerMakeCommand::class => ConsoleSupportServiceProvider::class,
            MailMakeCommand::class => ConsoleSupportServiceProvider::class,
            MiddlewareMakeCommand::class => ConsoleSupportServiceProvider::class,
            ModelMakeCommand::class => ConsoleSupportServiceProvider::class,
            NotificationMakeCommand::class => ConsoleSupportServiceProvider::class,
            NotificationTableCommand::class => ConsoleSupportServiceProvider::class,
            ObserverMakeCommand::class => ConsoleSupportServiceProvider::class,
            PolicyMakeCommand::class => ConsoleSupportServiceProvider::class,
            ProviderMakeCommand::class => ConsoleSupportServiceProvider::class,
            FailedTableCommand::class => ConsoleSupportServiceProvider::class,
            TableCommand::class => ConsoleSupportServiceProvider::class,
            BatchesTableCommand::class => ConsoleSupportServiceProvider::class,
            RequestMakeCommand::class => ConsoleSupportServiceProvider::class,
            ResourceMakeCommand::class => ConsoleSupportServiceProvider::class,
            RuleMakeCommand::class => ConsoleSupportServiceProvider::class,
            ScopeMakeCommand::class => ConsoleSupportServiceProvider::class,
            SeederMakeCommand::class => ConsoleSupportServiceProvider::class,
            SessionTableCommand::class => ConsoleSupportServiceProvider::class,
            ServeCommand::class => ConsoleSupportServiceProvider::class,
            StubPublishCommand::class => ConsoleSupportServiceProvider::class,
            TestMakeCommand::class => ConsoleSupportServiceProvider::class,
            TraitMakeCommand::class => ConsoleSupportServiceProvider::class,
            VendorPublishCommand::class => ConsoleSupportServiceProvider::class,
            ViewMakeCommand::class => ConsoleSupportServiceProvider::class,
            'migrator' => ConsoleSupportServiceProvider::class,
            'migration.repository' => ConsoleSupportServiceProvider::class,
            'migration.creator' => ConsoleSupportServiceProvider::class,
            MigrateCommand::class => ConsoleSupportServiceProvider::class,
            FreshCommand::class => ConsoleSupportServiceProvider::class,
            InstallCommand::class => ConsoleSupportServiceProvider::class,
            RefreshCommand::class => ConsoleSupportServiceProvider::class,
            ResetCommand::class => ConsoleSupportServiceProvider::class,
            RollbackCommand::class => ConsoleSupportServiceProvider::class,
            StatusCommand::class => ConsoleSupportServiceProvider::class,
            MigrateMakeCommand::class => ConsoleSupportServiceProvider::class,
            'composer' => ConsoleSupportServiceProvider::class,
            ConcurrencyManager::class => ConcurrencyServiceProvider::class,
            'hash' => HashServiceProvider::class,
            'hash.driver' => HashServiceProvider::class,
            'mail.manager' => MailServiceProvider::class,
            'mailer' => MailServiceProvider::class,
            Markdown::class => MailServiceProvider::class,
            'auth.password' => PasswordResetServiceProvider::class,
            'auth.password.broker' => PasswordResetServiceProvider::class,
            Hub::class => PipelineServiceProvider::class,
            'pipeline' => PipelineServiceProvider::class,
            'queue' => QueueServiceProvider::class,
            'queue.connection' => QueueServiceProvider::class,
            'queue.failer' => QueueServiceProvider::class,
            'queue.listener' => QueueServiceProvider::class,
            'queue.worker' => QueueServiceProvider::class,
            'redis' => RedisServiceProvider::class,
            'redis.connection' => RedisServiceProvider::class,
            'translator' => TranslationServiceProvider::class,
            'translation.loader' => TranslationServiceProvider::class,
            'validator' => ValidationServiceProvider::class,
            'validation.presence' => ValidationServiceProvider::class,
            UncompromisedVerifier::class => ValidationServiceProvider::class,
        ],
    'when' =>
        [
            BroadcastServiceProvider::class =>
                [
                ],
            BusServiceProvider::class =>
                [
                ],
            CacheServiceProvider::class =>
                [
                ],
            ConsoleSupportServiceProvider::class =>
                [
                ],
            ConcurrencyServiceProvider::class =>
                [
                ],
            HashServiceProvider::class =>
                [
                ],
            MailServiceProvider::class =>
                [
                ],
            PasswordResetServiceProvider::class =>
                [
                ],
            PipelineServiceProvider::class =>
                [
                ],
            QueueServiceProvider::class =>
                [
                ],
            RedisServiceProvider::class =>
                [
                ],
            TranslationServiceProvider::class =>
                [
                ],
            ValidationServiceProvider::class =>
                [
                ],
        ],
];
