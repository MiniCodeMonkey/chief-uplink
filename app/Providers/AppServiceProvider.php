<?php

namespace App\Providers;

use App\Events\ChiefMessageReceived;
use App\Events\DeviceDisconnected;
use App\Listeners\ScheduleOfflineEmailNotification;
use App\Listeners\ScheduleOfflinePushNotification;
use App\Listeners\SendEmailForChiefMessage;
use App\Listeners\SendPushForChiefMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerPushNotificationListeners();
        $this->registerEmailNotificationListeners();
    }

    protected function registerPushNotificationListeners(): void
    {
        Event::listen(ChiefMessageReceived::class, SendPushForChiefMessage::class);
        Event::listen(DeviceDisconnected::class, ScheduleOfflinePushNotification::class);
    }

    protected function registerEmailNotificationListeners(): void
    {
        Event::listen(ChiefMessageReceived::class, SendEmailForChiefMessage::class);
        Event::listen(DeviceDisconnected::class, ScheduleOfflineEmailNotification::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
