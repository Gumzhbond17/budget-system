<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ຮູບໂປຣໄຟລ໌ — ເກັບຢູ່ disk `private` ຄືກັບໂລໂກ້ທະນາຄານ (ບໍ່ມີ disk public
 * ໃນລະບົບນີ້ເລີຍ) ແລະ ເປີດຜ່ານ signed URL ເທົ່ານັ້ນ.
 *
 * `avatar_updated_at` ໃຊ້ທັງເປັນ "ປ່ຽນເມື່ອໃດ" ແລະ ເປັນ cache-buster ຢູ່ຝັ່ງ
 * SPA — path ເປັນ UUID ໃໝ່ທຸກຄັ້ງຢູ່ແລ້ວ ແຕ່ signed URL ຂອງ route ດຽວກັນ
 * (`users/{id}/avatar`) ຈະຄືເກົ່າຖ້າ expiry ຕົກວິນາທີດຽວກັນ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('email');
            $table->timestamp('avatar_updated_at')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'avatar_updated_at']);
        });
    }
};
