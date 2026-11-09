<?php

declare(strict_types=1);

use App\Models\Bank;
use App\Models\Project;
use Database\Seeders\CurrencySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // projects.budget_currency_code ມີ FK ໄປຫາ currencies — ບໍ່ seed ແລ້ວ
    // ສ້າງໂຄງການບໍ່ໄດ້ເລີຍ (ຄືກັບ MasterDataApiTest)
    $this->seed(CurrencySeeder::class);

    // ການທົດສອບການລຶບສ້າງລາຍຈ່າຍຜ່ານ ExpenseService ເຊິ່ງແປງສະກຸນເງິນ —
    // ບໍ່ມີ rate ແລ້ວມັນຈະໂຍນ RuntimeException ແທນທີ່ຈະສ້າງແຖວໃຫ້
    seedBaseRates();

    $this->project = Project::factory()->create();
    $this->accountant = memberOf($this->project, 'modify');
    $this->viewer = memberOf($this->project, 'read_only');
});

describe('CRUD', function () {
    it('ທຸກຄົນທີ່ login ແລ້ວອ່ານລາຍການໄດ້ — Vue ຕ້ອງໃຊ້ເຕີມ dropdown', function () {
        Bank::factory()->create(['code' => 'BCEL', 'name_lo' => 'ທະນາຄານການຄ້າຕ່າງປະເທດລາວ']);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/banks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCEL')
            ->assertJsonPath('data.0.has_logo', false)
            ->assertJsonPath('data.0.logo_url', null);
    });

    it('ບໍ່ຄືນ logo_path ອອກໄປເລີຍ — ເຫັນໄຟລ໌ໄດ້ຜ່ານ signed URL ເທົ່ານັ້ນ', function () {
        Bank::factory()->create(['logo_path' => 'banks/secret.webp']);

        Sanctum::actingAs($this->viewer);

        $body = $this->getJson('/api/v1/banks')->assertOk()->json('data.0');

        expect($body)->not->toHaveKey('logo_path')
            ->and($body['has_logo'])->toBeTrue()
            ->and($body['logo_url'])->toContain('signature=');
    });

    it('ຜູ້ບໍ່ມີສິດຂຽນສ້າງບໍ່ໄດ້', function () {
        Sanctum::actingAs($this->viewer);

        $this->postJson('/api/v1/banks', ['code' => 'LDB', 'name_lo' => 'ທະນາຄານພັດທະນາລາວ'])
            ->assertForbidden();
    });

    it('ສ້າງໄດ້ ແລະ ລະຫັດຖືກປ່ຽນເປັນຕົວພິມໃຫຍ່ໃຫ້', function () {
        Sanctum::actingAs($this->accountant);

        // "bcel" ກັບ "BCEL" ຜ່ານ unique ທັງຄູ່ຖ້າບໍ່ normalise —
        // ນີ້ຄືບັນຫາດຽວກັບ "SSS" vs "SSS " ໃນ Excel ເດີມ
        $this->postJson('/api/v1/banks', [
            'code' => '  bcel ',
            'name_lo' => 'ທະນາຄານການຄ້າຕ່າງປະເທດລາວ',
            'name_en' => 'BCEL',
            'swift_code' => 'coeblala',
        ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'BCEL')
            ->assertJsonPath('data.swift_code', 'COEBLALA');
    });

    it('ລະຫັດຊ້ຳບໍ່ໄດ້ ເຖິງແມ່ນພິມຕົວນ້ອຍ', function () {
        Bank::factory()->create(['code' => 'BCEL']);

        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/banks', ['code' => 'bcel', 'name_lo' => 'ຊ້ຳ'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    });

    it('ປະຕິເສດ SWIFT ທີ່ຄວາມຍາວບໍ່ແມ່ນ 8 ຫຼື 11', function () {
        Sanctum::actingAs($this->accountant);

        $this->postJson('/api/v1/banks', [
            'code' => 'LDB',
            'name_lo' => 'ທະນາຄານພັດທະນາລາວ',
            'swift_code' => 'LDBKLA',   // 6 ຕົວ — ພິມຂາດ
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('swift_code');
    });

    it('ແກ້ໄຂໄດ້ ລວມທັງລະຫັດ — ຍັງບໍ່ມີ FK ຊີ້ມາຫາ', function () {
        $bank = Bank::factory()->create(['code' => 'OLD']);

        Sanctum::actingAs($this->accountant);

        $this->patchJson("/api/v1/banks/{$bank->id}", ['code' => 'NEW'])
            ->assertOk()
            ->assertJsonPath('data.code', 'NEW');
    });

    it('ສົ່ງລະຫັດເກົ່າຂອງຕົນເອງຄືນມາໄດ້ — ຟອມສົ່ງທັງກ້ອນ', function () {
        $bank = Bank::factory()->create(['code' => 'BCEL']);

        Sanctum::actingAs($this->accountant);

        $this->patchJson("/api/v1/banks/{$bank->id}", [
            'code' => 'BCEL',
            'name_lo' => 'ຊື່ໃໝ່',
        ])
            ->assertOk()
            ->assertJsonPath('data.name_lo', 'ຊື່ໃໝ່');
    });

    it('ຢຸດໃຊ້ແລ້ວຫາຍຈາກລາຍການ ນອກຈາກຂໍ include_inactive', function () {
        Bank::factory()->create(['is_active' => false]);

        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/banks')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/banks?include_inactive=1')->assertOk()->assertJsonCount(1, 'data');
    });

    it('ລຶບໄດ້ເມື່ອບໍ່ມີລາຍການໃດອ້າງເຖິງ', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        $this->deleteJson("/api/v1/banks/{$bank->id}")->assertNoContent();

        $this->assertDatabaseMissing('banks', ['id' => $bank->id]);
    });

    it('ລຶບບໍ່ໄດ້ເມື່ອມີລາຍຈ່າຍລະບຸວ່າໂອນຜ່ານທະນາຄານນີ້', function () {
        $bank = Bank::factory()->create();

        createExpense($this->project, $this->accountant, [
            'bank_id' => $bank->id,
            'payment_method' => 'bank_transfer',
        ]);

        Sanctum::actingAs($this->accountant);

        // 409 ບໍ່ແມ່ນ 403: FK ເປັນ nullOnDelete ຈຶ່ງລຶບໄດ້ທາງເຕັກນິກ ແຕ່ຈະ
        // ເຮັດໃຫ້ໃບໂອນທີ່ແນບໄວ້ກາຍເປັນ "ບໍ່ຮູ້ວ່າທະນາຄານໃດ" ແບບງຽບໆ
        $this->deleteJson("/api/v1/banks/{$bank->id}")->assertStatus(409);

        $this->assertDatabaseHas('banks', ['id' => $bank->id]);
    });

    it('ນັບການອ້າງອີງໃຫ້ເມື່ອຂໍ with_usage', function () {
        $bank = Bank::factory()->create();
        createExpense($this->project, $this->accountant, ['bank_id' => $bank->id]);

        Sanctum::actingAs($this->viewer);

        // ບໍ່ຂໍ = ບໍ່ນັບ (ຟອມທຸລະກຳດຶງ endpoint ນີ້ທຸກເທື່ອທີ່ເປີດ)
        $plain = $this->getJson('/api/v1/banks')->assertOk()->json('data.0');
        expect($plain)->not->toHaveKey('expenses_count');

        $this->getJson('/api/v1/banks?with_usage=1')
            ->assertOk()
            ->assertJsonPath('data.0.expenses_count', 1)
            ->assertJsonPath('data.0.revenues_count', 0);
    });
});

describe('ໂລໂກ້', function () {
    beforeEach(function () {
        Storage::fake('private');
    });

    /** ຮູບ PNG ຈິງ — UploadedFile::fake()->image() ສ້າງໄຟລ໌ທີ່ GD decode ໄດ້ */
    function logoFile(int $size = 400): UploadedFile
    {
        return UploadedFile::fake()->image('logo.png', $size, $size);
    }

    it('ອັບແລ້ວໄດ້ signed URL ທີ່ເປີດຮູບໄດ້ຈິງ ໂດຍບໍ່ຕ້ອງ login', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        $url = $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile()])
            ->assertOk()
            ->assertJsonPath('data.has_logo', true)
            ->json('data.logo_url');

        expect($bank->refresh()->logo_path)->toStartWith('banks/')->toEndWith('.webp');
        Storage::disk('private')->assertExists($bank->logo_path);

        // ນີ້ຄືຄຸນສົມບັດທີ່ສຳຄັນ: <img src> ຂອງ browser ບໍ່ສົ່ງ header auth
        // ໄປນຳ ດັ່ງນັ້ນ URL ຕ້ອງໃຊ້ໄດ້ໂດຍບໍ່ມີ session ເລີຍ
        app('auth')->forgetGuards();

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('ບໍ່ມີລາຍເຊັນ = ເປີດບໍ່ໄດ້', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);
        $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile()])->assertOk();

        $this->get("/api/v1/banks/{$bank->id}/logo")->assertForbidden();
    });

    it('ຮູບຖືກ re-encode ເປັນ WebP ແລະ ຫຍໍ້ລົງ 256 px', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile(1000)])->assertOk();

        // ຫຍໍ້ລົງ = ລ້າງ EXIF ໄປນຳ (strip: true) — ນັ້ນຄືເຫດຜົນທີ່ບໍ່ເກັບ
        // ໄຟລ໌ຕົ້ນສະບັບໄວ້ຄືເກົ່າ
        expect($bank->refresh()->logo_width)->toBe(256)
            ->and($bank->logo_height)->toBe(256)
            ->and($bank->logo_updated_at)->not->toBeNull();
    });

    it('ອັບທັບແລ້ວລຶບໄຟລ໌ເກົ່າຖິ້ມ — ໂລໂກ້ບໍ່ແມ່ນຫຼັກຖານ', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile()])->assertOk();
        $first = $bank->refresh()->logo_path;

        $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile(300)])->assertOk();
        $second = $bank->refresh()->logo_path;

        expect($second)->not->toBe($first);
        Storage::disk('private')->assertMissing($first);
        Storage::disk('private')->assertExists($second);
    });

    it('ລຶບໂລໂກ້ອອກໄດ້ ໂດຍທະນາຄານຍັງຢູ່', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);
        $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile()])->assertOk();
        $path = $bank->refresh()->logo_path;

        $this->deleteJson("/api/v1/banks/{$bank->id}/logo")
            ->assertOk()
            ->assertJsonPath('data.has_logo', false);

        Storage::disk('private')->assertMissing($path);
        $this->assertDatabaseHas('banks', ['id' => $bank->id, 'logo_path' => null]);
    });

    it('ລຶບທະນາຄານແລ້ວໄຟລ໌ໂລໂກ້ບໍ່ຄ້າງຢູ່ disk', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);
        $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile()])->assertOk();
        $path = $bank->refresh()->logo_path;

        $this->deleteJson("/api/v1/banks/{$bank->id}")->assertNoContent();

        Storage::disk('private')->assertMissing($path);
    });

    it('ຜູ້ບໍ່ມີສິດຂຽນອັບໂລໂກ້ບໍ່ໄດ້', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->viewer);

        $this->post("/api/v1/banks/{$bank->id}/logo", ['logo' => logoFile()])
            ->assertForbidden();
    });

    it('ປະຕິເສດ PDF ແລະ ໄຟລ໌ທີ່ບໍ່ແມ່ນຮູບ', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        $this->post("/api/v1/banks/{$bank->id}/logo", [
            'logo' => UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    });

    it('ປະຕິເສດ SVG — markup ຝັງ script ໄດ້', function () {
        $bank = Bank::factory()->create();

        Sanctum::actingAs($this->accountant);

        $this->post("/api/v1/banks/{$bank->id}/logo", [
            'logo' => UploadedFile::fake()->create('logo.svg', 2, 'image/svg+xml'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('logo');
    });
});
