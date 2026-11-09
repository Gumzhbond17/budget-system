<?php

namespace App\Providers;

use App\Audit\AuthActivitySubscriber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ຜູກ driver ຢ່າງຈະແຈ້ງ — Intervention ບໍ່ມີ default. ໃຊ້ GD ເພາະ
        // Imagick ບໍ່ໄດ້ຕິດຕັ້ງໃນສະພາບແວດລ້ອມນີ້; GD ພຽງພໍສຳລັບ JPEG/PNG/WebP
        // ເຊິ່ງຄືທຸກປະເພດທີ່ AttachmentService ອະນຸຍາດ.
        $this->app->singleton(
            ImageManager::class,
            fn (): ImageManager => new ImageManager(new Driver),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // login / logout / login ລົ້ມເຫຼວ → activity_logs (ເບິ່ງ class ສຳລັບເຫດຜົນທີ່
        // ລົງທະບຽນຢູ່ນີ້ ບໍ່ແມ່ນໃຫ້ event discovery ຫາເອງ)
        Event::subscribe(AuthActivitySubscriber::class);
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
            : null,
        );
    }
}
