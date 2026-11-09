<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('private');

    $this->user = User::factory()->create();
});

describe('POST /api/v1/profile/avatar', function () {
    it('ອັບຮູບແລ້ວເກັບເປັນ WebP ຢູ່ disk private ແລະ ຄືນ signed URL', function () {
        Sanctum::actingAs($this->user);

        $url = $this->post('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 1600, 1200),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonMissingPath('data.avatar_path')
            ->json('data.avatar_url');

        $path = $this->user->fresh()->avatar_path;

        expect($url)->toContain('signature=')
            ->and($path)->toStartWith('avatars/')->toEndWith('.webp');

        Storage::disk('private')->assertExists($path);

        // ຫຍໍ້ລົງບໍ່ເກີນ 1024 px — ບໍ່ crop (ສັດສ່ວນເດີມ 4:3 ຍັງຢູ່)
        [$width, $height] = getimagesizefromstring(Storage::disk('private')->get($path));
        expect([$width, $height])->toBe([1024, 768]);
    });

    it('ປ່ຽນຮູບແລ້ວລຶບໄຟລ໌ເກົ່າຖິ້ມ', function () {
        Sanctum::actingAs($this->user);

        $this->post('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json']);
        $first = $this->user->fresh()->avatar_path;

        $this->post('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json']);

        Storage::disk('private')->assertMissing($first);
        Storage::disk('private')->assertExists($this->user->fresh()->avatar_path);
    });

    it('ປະຕິເສດໄຟລ໌ທີ່ບໍ່ແມ່ນຮູບ', function (UploadedFile $file) {
        Sanctum::actingAs($this->user);

        $this->post('/api/v1/profile/avatar', ['avatar' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('avatar');

        expect($this->user->fresh()->avatar_path)->toBeNull();
    })->with([
        'svg' => fn () => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'),
        'pdf' => fn () => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ]);

    it('ຕ້ອງ login', function () {
        $this->post('/api/v1/profile/avatar', [], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    });
});

describe('DELETE /api/v1/profile/avatar', function () {
    it('ລຶບຮູບ ແລະ ໄຟລ໌ອອກ', function () {
        Sanctum::actingAs($this->user);

        $this->post('/api/v1/profile/avatar', ['avatar' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json']);
        $path = $this->user->fresh()->avatar_path;

        $this->deleteJson('/api/v1/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);

        Storage::disk('private')->assertMissing($path);
    });
});

describe('GET signed avatar', function () {
    it('ເປີດໄດ້ດ້ວຍ signed URL ໂດຍບໍ່ຕ້ອງ login (ສຳລັບ <img src>)', function () {
        Sanctum::actingAs($this->user);

        $url = $this->post('/api/v1/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('a.png'),
        ], ['Accept' => 'application/json'])->json('data.avatar_url');

        $this->app['auth']->forgetGuards();

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('URL ທີ່ບໍ່ມີລາຍເຊັນເປີດບໍ່ໄດ້', function () {
        $this->get("/api/v1/users/{$this->user->id}/avatar")->assertForbidden();
    });
});
