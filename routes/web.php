<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth (Sanctum SPA mode — session cookie, ບໍ່ແມ່ນ token)
|--------------------------------------------------------------------------
|
| ຢູ່ໃນ web.php ບໍ່ແມ່ນ api.php ໂດຍເຈດຕະນາ: SPA mode ຂອງ Sanctum ໃຊ້ session
| cookie ຂອງ Laravel ເອງ ຈຶ່ງຕ້ອງຜ່ານ middleware `web` (session + CSRF).
| ຖ້າຢູ່ໃນ api.php ຈະບໍ່ມີ session ໃຫ້ login ໃສ່.
|
| ຫ້າມເກັບ token ໃນ localStorage — XSS ດຶງໄດ້ (docs/architecture.md ພາກ 9).
|
*/

Route::post('login', [AuthController::class, 'login'])
    ->middleware(['guest', 'throttle:5,1'])   // ພາກ 9: throttle:5,1 ສຳລັບ login
    ->name('login');

Route::post('logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| SPA shell
|--------------------------------------------------------------------------
|
| ທຸກ URL ທີ່ບໍ່ແມ່ນ api/ ຫຼື sanctum/ ສົ່ງຄືນ shell ອັນດຽວກັນ ແລ້ວປ່ອຍໃຫ້
| vue-router ຈັດການ. ຕ້ອງປະກາດເປັນ route ສຸດທ້າຍ.
|
*/

Route::view('/{any?}', 'app')
    ->where('any', '^(?!api|sanctum|storage|up).*$')
    ->name('spa');
