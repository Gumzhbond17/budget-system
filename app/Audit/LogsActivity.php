<?php

declare(strict_types=1);

namespace App\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ບັນທຶກ ສ້າງ / ແກ້ / ລຶບ / ກູ້ຄືນ ຂອງ model ລົງ `activity_logs` ໂດຍອັດຕະໂນມັດ.
 *
 * ⚠️ ໄດ້ສະເພາະການປ່ຽນຜ່ານ model (`$model->save()`, `->delete()`). ການ update
 * ຜ່ານ query builder (`Model::query()->update(...)`, `DB::table()`) ບໍ່ຍິງ event
 * ຈຶ່ງບໍ່ຖືກບັນທຶກ — ຢ່າໃຊ້ທາງນັ້ນກັບຂໍ້ມູນທີ່ຜູ້ໃຊ້ປ່ຽນ.
 *
 * Model ທີ່ຢາກໃຫ້ label ຕ່າງຈາກຄ່າຕັ້ງຕົ້ນ (doc_no / code / name …) ໃຫ້ນິຍາມ
 * `activityLabel(): string` ເອງ.
 *
 * @mixin Model
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => ActivityLogger::modelEvent($model, 'created'));
        static::updated(fn (Model $model) => ActivityLogger::modelEvent($model, 'updated'));
        static::deleted(fn (Model $model) => ActivityLogger::modelEvent($model, 'deleted'));

        // `restored()` ມີສະເພາະ model ທີ່ໃຊ້ SoftDeletes — ລົງທະບຽນຜ່ານຊື່ event
        // ກົງໆ ຈຶ່ງໃຊ້ trait ດຽວກັນໄດ້ທັງສອງແບບ
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('restored', fn (Model $model) => ActivityLogger::modelEvent($model, 'restored'));
        }
    }
}
