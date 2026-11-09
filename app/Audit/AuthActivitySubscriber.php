<?php

declare(strict_types=1);

namespace App\Audit;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * login / logout / login ລົ້ມເຫຼວ → activity_logs.
 *
 * ຟັງ event ຂອງ Laravel ແທນທີ່ຈະຂຽນຢູ່ AuthController: ການກັບເຂົ້າລະບົບດ້ວຍ
 * cookie "ຈື່ຂ້ອຍໄວ້" ກໍຍິງ `Login` ຄືກັນ ໂດຍບໍ່ຜ່ານ controller ເລີຍ.
 *
 * ຢູ່ນອກ app/Listeners ໂດຍເຈດຕະນາ — event discovery ຂອງ Laravel ຈະຈັບ method
 * `handle*` ໃນໂຟນເດີນັ້ນ ແລ້ວລົງທະບຽນຊ້ຳກັບ `subscribe()` (ບັນທຶກຊ້ອນສອງແຖວ).
 */
final class AuthActivitySubscriber
{
    public function handleLogin(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // ຄໍລຳນີ້ມີມາແຕ່ຕົ້ນ ແຕ່ບໍ່ມີໃຜຂຽນ — saveQuietly ເພື່ອບໍ່ໃຫ້ກາຍເປັນ
        // ແຖວ "ແກ້ໄຂຜູ້ໃຊ້" ທຸກຄັ້ງທີ່ login
        $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

        ActivityLogger::login($event->user, $event->remember);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            ActivityLogger::logout($event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? '';

        ActivityLogger::loginFailed(
            $event->user instanceof User ? $event->user : null,
            is_string($email) ? $email : '',
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}
