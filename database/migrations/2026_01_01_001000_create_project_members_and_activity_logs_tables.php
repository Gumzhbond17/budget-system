<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ຄວບຄຸມການເຂົ້າເຖິງລະດັບໂຄງການ — ຜູ້ໃຊ້ເຫັນສະເພາະໂຄງການທີ່ຕົນເປັນສະມາຊິກ
        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'approver', 'accountant', 'viewer'])->default('viewer');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        // Audit trail — ບັງຄັບສຳລັບລະບົບການເງິນ
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');                    // expenses / attachments / ...
            $table->foreignId('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);                  // created, updated, approved, deleted
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('project_members');
    }
};
