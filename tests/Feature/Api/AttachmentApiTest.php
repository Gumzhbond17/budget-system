<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Project;
use App\Services\ExpenseService;
use Database\Seeders\CurrencySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CurrencySeeder::class);
    seedBaseRates();

    Storage::fake('private');

    $this->project = Project::factory()->create();
    $this->editor = memberOf($this->project, 'modify');
    $this->approver = memberOf($this->project, 'authorize');
    $this->reader = memberOf($this->project, 'read_only');

    $this->expense = createExpense($this->project, $this->editor);

    $this->upload = fn (array $files, string $kind = 'transfer_slip') => $this->postJson(
        "/api/v1/expenses/{$this->expense->uuid}/attachments",
        ['files' => $files, 'kind' => $kind],
    );
});

describe('ອັບໂຫຼດ', function () {
    it('ຮັບຮູບ ແລະ ເກັບລົງ private disk', function () {
        Sanctum::actingAs($this->editor);

        ($this->upload)([UploadedFile::fake()->image('slip.jpg', 1200, 800)])
            ->assertCreated()
            ->assertJsonPath('data.0.kind', 'transfer_slip')
            ->assertJsonPath('data.0.is_image', true);

        $attachment = Attachment::query()->sole();

        expect($attachment->disk)->toBe('private')
            ->and(Storage::disk('private')->exists($attachment->path))->toBeTrue();
    });

    it('ຮູບຖືກ re-encode ເປັນ WebP — ລ້າງ EXIF ພ້ອມ', function () {
        // ໃບໂອນຈາກມືຖືມີພິກັດ GPS ຝັງຢູ່. re-encode ລ້າງອອກ
        // ແລະ ຫຍໍ້ຂະໜາດລົງ (Excel ເດີມມີຮູບ 2.5 MB).
        Sanctum::actingAs($this->editor);

        ($this->upload)([UploadedFile::fake()->image('slip.jpg', 3000, 2000)])->assertCreated();

        $attachment = Attachment::query()->sole();

        expect($attachment->mime_type)->toBe('image/jpeg')     // MIME ຂອງຕົ້ນສະບັບ
            ->and($attachment->path)->toEndWith('.webp')       // ແຕ່ເກັບເປັນ webp
            ->and($attachment->width)->toBe(2000)              // ຫຍໍ້ຈາກ 4000
            ->and($attachment->thumbnail_path)->not->toBeNull();
    });

    it('ຊື່ໄຟລ໌ໃນ storage ເປັນ UUID ບໍ່ແມ່ນຊື່ເດີມ', function () {
        // ຖ້າໃຊ້ຊື່ເດີມສ້າງ path → path traversal ("../../.env")
        Sanctum::actingAs($this->editor);

        ($this->upload)([UploadedFile::fake()->image('../../evil name.jpg')])->assertCreated();

        $attachment = Attachment::query()->sole();

        expect($attachment->path)->not->toContain('..')
            ->and($attachment->path)->not->toContain('evil name');
    });

    it('ຮັບ PDF ໂດຍບໍ່ re-encode', function () {
        Sanctum::actingAs($this->editor);

        ($this->upload)([UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')])
            ->assertCreated()
            ->assertJsonPath('data.0.is_image', false);

        expect(Attachment::query()->sole()->path)->toEndWith('.pdf');
    });

    it('ຮູບຂະໜາດມືຖືທຳມະດາບໍ່ທະລຸໜ່ວຍຄວາມຈຳ', function () {
        // 12 ລ້ານ pixel ≈ ກ້ອງມືຖື 12 MP. GD ຄາຍເປັນ raw ~48 MB —
        // ຖ້າ decode ສອງເທື່ອ (ຮູບເຕັມ + thumbnail) ຈະທະລຸ 128 MB
        Sanctum::actingAs($this->editor);

        ($this->upload)([UploadedFile::fake()->image('big.jpg', 4000, 3000)])
            ->assertCreated();

        expect(Attachment::query()->sole()->width)->toBe(2000);
    });

    it('ປະຕິເສດໄຟລ໌ທີ່ບໍ່ອະນຸຍາດ', function () {
        Sanctum::actingAs($this->editor);

        ($this->upload)([UploadedFile::fake()->create('shell.php', 10)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('files.0');
    });

    it('ອັບໄຟລ໌ດຽວກັນຊ້ຳ ບໍ່ສ້າງ record ໃໝ່ ແລະ ບໍ່ນັບ counter ຊ້ຳ', function () {
        Sanctum::actingAs($this->editor);

        // ຜູ້ໃຊ້ກົດສອງເທື່ອຍ້ອນເນັດຊ້າ — ເກີດຂຶ້ນຈິງ
        $file = UploadedFile::fake()->image('slip.jpg');
        ($this->upload)([$file])->assertCreated();
        ($this->upload)([UploadedFile::fake()->image('slip.jpg')])->assertCreated();

        expect(Attachment::query()->count())->toBe(1)
            ->and($this->expense->fresh()->attachments_count)->toBe(1);
    });

    it('ອັບຫຼາຍໄຟລ໌ພ້ອມກັນໄດ້ ແລະ counter ຖືກຕ້ອງ', function () {
        Sanctum::actingAs($this->editor);

        // ຂະໜາດຕ້ອງຕ່າງກັນ — ຮູບປອມຂະໜາດດຽວກັນຈະມີ checksum ດຽວກັນ
        // ແລ້ວຖືກ dedup ລວມເປັນແຖວດຽວ (ພຶດຕິກຳທີ່ຖືກຕ້ອງ, ເບິ່ງ test ຂ້າງເທິງ)
        ($this->upload)([
            UploadedFile::fake()->image('a.jpg', 400, 300),
            UploadedFile::fake()->image('b.jpg', 500, 300),
            UploadedFile::fake()->image('c.jpg', 600, 300),
        ])->assertCreated()->assertJsonCount(3, 'data');

        expect($this->expense->fresh()->attachments_count)->toBe(3)
            ->and(Attachment::query()->count())->toBe(3);
    });

    it('ເກີນ 10 ໄຟລ໌ຕໍ່ຄັ້ງບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->editor);

        $files = array_map(fn (int $i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 11));

        ($this->upload)($files)->assertStatus(422)->assertJsonValidationErrors('files');
    });
});

describe('ສິດ', function () {
    it('read_only ອັບໂຫຼດບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->reader);

        ($this->upload)([UploadedFile::fake()->image('slip.jpg')])->assertForbidden();
    });

    it('ຄົນນອກໂຄງການອັບໂຫຼດບໍ່ໄດ້', function () {
        $outsider = memberOf(Project::factory()->create(), 'full_access');

        Sanctum::actingAs($outsider);

        ($this->upload)([UploadedFile::fake()->image('slip.jpg')])->assertForbidden();
    });

    it('ລາຍການທີ່ອະນຸມັດແລ້ວ ແນບເພີ່ມບໍ່ໄດ້', function () {
        $service = app(ExpenseService::class);
        $service->submit($this->expense);
        $service->approve($this->expense, $this->approver->id);

        Sanctum::actingAs($this->editor);

        ($this->upload)([UploadedFile::fake()->image('slip.jpg')])->assertForbidden();
    });

    it('ຫຼັກຖານຂອງລາຍການທີ່ອະນຸມັດແລ້ວ ລຶບບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->editor);
        ($this->upload)([UploadedFile::fake()->image('slip.jpg')])->assertCreated();

        $attachment = Attachment::query()->sole();

        $service = app(ExpenseService::class);
        $service->submit($this->expense);
        $service->approve($this->expense, $this->approver->id);

        $this->deleteJson("/api/v1/attachments/{$attachment->uuid}")->assertForbidden();
    });

    it('ລຶບໄດ້ຕອນຍັງບໍ່ອະນຸມັດ ແລະ counter ຫຼຸດລົງ', function () {
        Sanctum::actingAs($this->editor);
        ($this->upload)([UploadedFile::fake()->image('slip.jpg')])->assertCreated();

        $attachment = Attachment::query()->sole();

        $this->deleteJson("/api/v1/attachments/{$attachment->uuid}")->assertNoContent();

        expect($this->expense->fresh()->attachments_count)->toBe(0)
            ->and(Attachment::query()->find($attachment->id))->toBeNull()
            // ໄຟລ໌ຈິງຍັງຢູ່ — ຫຼັກຖານການເງິນຫ້າມລຶບຖາວອນ
            ->and(Storage::disk('private')->exists($attachment->path))->toBeTrue();
    });
});

describe('ການເຂົ້າເຖິງໄຟລ໌', function () {
    it('ບໍ່ຮົ່ວ path ຫຼື disk ອອກທາງ API', function () {
        Sanctum::actingAs($this->editor);

        $payload = ($this->upload)([UploadedFile::fake()->image('slip.jpg')])
            ->assertCreated()
            ->json('data.0');

        expect($payload)->not->toHaveKey('path')
            ->and($payload)->not->toHaveKey('disk')
            ->and($payload['url'])->toContain('signature=');
    });

    it('URL ທີ່ບໍ່ມີລາຍເຊັນເປີດບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->editor);
        ($this->upload)([UploadedFile::fake()->image('slip.jpg')])->assertCreated();

        $attachment = Attachment::query()->sole();

        $this->get("/api/v1/attachments/{$attachment->uuid}/download")->assertForbidden();
    });

    it('URL ທີ່ມີລາຍເຊັນເປີດໄດ້ ພ້ອມ header ກັນ XSS', function () {
        Sanctum::actingAs($this->editor);

        $url = ($this->upload)([UploadedFile::fake()->image('slip.jpg')])
            ->assertCreated()
            ->json('data.0.url');

        $this->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('ລາຍເຊັນທີ່ຖືກແກ້ໃຊ້ບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->editor);

        $url = ($this->upload)([UploadedFile::fake()->image('slip.jpg')])
            ->assertCreated()
            ->json('data.0.url');

        $this->get($url.'x')->assertForbidden();
    });
});

describe('ລາຍຮັບກໍແນບຫຼັກຖານໄດ້', function () {
    it('ອັບໂຫຼດໃສ່ລາຍຮັບໄດ້ ແລະ ນັບ counter', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/revenues/{$revenue->uuid}/attachments", [
            'files' => [UploadedFile::fake()->image('receipt.jpg')],
            'kind' => 'receipt',
        ])->assertCreated()->assertJsonPath('data.0.kind', 'receipt');

        expect($revenue->fresh()->attachments_count)->toBe(1);
    });

    it('ດຶງລາຍການຫຼັກຖານຂອງລາຍຮັບໄດ້', function () {
        $revenue = createRevenue($this->project, $this->editor);

        Sanctum::actingAs($this->editor);

        $this->postJson("/api/v1/revenues/{$revenue->uuid}/attachments", [
            'files' => [UploadedFile::fake()->image('a.jpg')],
            'kind' => 'receipt',
        ])->assertCreated();

        $this->getJson("/api/v1/revenues/{$revenue->uuid}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });
});
