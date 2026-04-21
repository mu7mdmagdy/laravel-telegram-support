<?php

namespace MoMagdy\TelegramSupport;

use MoMagdy\TelegramSupport\Console\Commands\TelegramTest;
use MoMagdy\TelegramSupport\Services\TelegramService;
use MoMagdy\TelegramSupport\View\Components\TelegramConnect;
use MoMagdy\TelegramSupport\View\Components\TelegramSupport;
use MoMagdy\TelegramSupport\View\Components\TelegramWidget;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class TelegramSupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/telegram.php', 'telegram');

        $this->app->singleton(TelegramService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'telegram-support');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'telegram-support');

        Blade::component('telegram-support', TelegramSupport::class);
        Blade::component('telegram-widget',  TelegramWidget::class);
        Blade::component('telegram-connect', TelegramConnect::class);

        if ($this->app->runningInConsole()) {
            $this->commands([TelegramTest::class]);

            $this->publishes([
                __DIR__ . '/../config/telegram.php' => config_path('telegram.php'),
            ], 'telegram-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'telegram-migrations');

            $this->publishes([
                __DIR__ . '/../resources/lang' => lang_path('vendor/telegram-support'),
            ], 'telegram-lang');

            $this->publishes([
                __DIR__ . '/../dist' => public_path('vendor/telegram-support'),
            ], 'telegram-assets');
        }
    }
}
