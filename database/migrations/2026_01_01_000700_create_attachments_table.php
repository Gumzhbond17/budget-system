<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Polymorphic: ໃຊ້ໄດ້ກັບ expenses, funding_receipts, contracts ໃນອະນາຄົດ
            $table->morphs('attachable');   // ສ້າງ attachable_type + attachable_id + index

            $table->enum('kind', [
                'transfer_slip',   // ໃບໂອນເງິນ
                'receipt',         // ໃບບິນ/ໃບຮັບເງິນ
                'invoice',         // ໃບເກັບເງິນ
                'quotation',       // ໃບສະເໜີລາຄາ
                'contract',        // ສັນຍາ
                'photo',           // ຮູບໜ້າງານ
                'other',
            ])->default('transfer_slip');

            // ---------- ຂໍ້ມູນໄຟລ໌ ----------
            $table->string('disk', 30)->default('private');      // ຫ້າມໃຊ້ 'public'
            $table->string('path', 500);                         // expenses/2026/08/{uuid}.webp
            $table->string('original_name', 255);                // ຊື່ເດີມ (ສະແດງໃຫ້ຜູ້ໃຊ້ເທົ່ານັ້ນ ຫ້າມໃຊ້ສ້າງ path)
            $table->string('mime_type', 100);                    // ກວດຈາກ content ບໍ່ແມ່ນ extension
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('thumbnail_path', 500)->nullable();

            // SHA-256 ຂອງໄຟລ໌ — ໃຊ້ 2 ຢ່າງ:
            //   1. ກວດຫາໄຟລ໌ຊ້ຳ (ໃນ Excel ເດີມມີຮູບຊ້ຳຫຼາຍ)
            //   2. ພິສູດຄວາມຄົບຖ້ວນ (tamper detection) ຕອນກວດສອບ
            $table->char('checksum_sha256', 64);

            // ---------- OCR (optional, ໄລຍະ 2) ----------
            $table->text('ocr_text')->nullable();                // ຂໍ້ຄວາມທີ່ສະກັດຈາກໃບໂອນ
            $table->bigInteger('ocr_amount_minor')->nullable();  // ຈຳນວນເງິນທີ່ OCR ອ່ານໄດ້
            $table->char('ocr_currency_code', 3)->nullable();
            $table->timestamp('ocr_processed_at')->nullable();

            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();   // ຫຼັກຖານການເງິນ — ຫ້າມລຶບຖາວອນ

            $table->index('checksum_sha256');   // ຫາໄຟລ໌ຊ້ຳ
            $table->index(['attachable_type', 'attachable_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
