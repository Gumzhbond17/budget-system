<?php

use App\Domain\Expense\InvalidStatusTransition;
use App\Http\Middleware\EnsureAccessIsActive;
use App\Http\Middleware\LogSearchActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Sanctum SPA mode: request ຈາກໂດເມນໃນ SANCTUM_STATEFUL_DOMAINS
        // ຖືກຍົກຂຶ້ນເປັນ stateful (session + CSRF) ກ່ອນຮອດ auth:sanctum
        $middleware->statefulApi();

        // ໃຊ້ເປັນ `->middleware('access.active')` ໃນ routes/api.php
        // (ຕ້ອງມາຫຼັງ auth:sanctum ຈຶ່ງຈະມີ $request->user())
        $middleware->alias([
            'access.active' => EnsureAccessIsActive::class,
            // ບັນທຶກ `?search=` ລົງ activity_logs (ໜ້າ "ບັນທຶກການໃຊ້ງານ")
            'log.search' => LogSearchActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // ການປ່ຽນສະຖານະທີ່ບໍ່ຖືກຕ້ອງ (ເຊັ່ນ approve ລາຍການທີ່ຍັງເປັນ draft) ຄື
        // ຄວາມຜິດຂອງ client ບໍ່ແມ່ນ server — ຕອບ 409 ບໍ່ແມ່ນ 500
        $exceptions->render(function (InvalidStatusTransition $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 409);
            }

            return null;
        });
    })->create();
