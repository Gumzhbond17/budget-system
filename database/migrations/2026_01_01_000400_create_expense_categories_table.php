<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();

            // self-referencing: sheet ເດີມມີຫົວຂໍ້ໃຫຍ່ "I. ການເຮັດເອກະສານຂໍອະນຸມັດໂຄງການ"
            // ເປັນໝວດແມ່ ແລະ "EDL", "ອາຫານ" ເປັນໝວດລູກ
            $table->foreignId('parent_id')->nullable()
                ->constrained('expense_categories')->nullOnDelete();

            $table->string('code', 40)->unique();   // EQUIPMENT, LABOR, FUEL
            $table->string('name_lo', 120);         // ອຸປະກອນຕ່າງໆ
            $table->string('name_en', 120)->nullable();
            $table->string('color', 7)->default('#6366f1');  // ສີໃນ chart — ກຳນົດຈາກ DB ບໍ່ແມ່ນ hardcode ໃນ Vue
            $table->string('icon', 40)->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
