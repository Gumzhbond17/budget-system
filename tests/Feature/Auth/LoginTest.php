<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Cookie;

beforeEach(function () {
    RateLimiter::clear('');

    $this->user = User::factory()->create([
        'email' => 'phanh@example.com',
        'password' => Hash::make('correct-horse'),
    ]);
});

describe('POST /login', function () {
    it('ເຂົ້າສູ່ລະບົບໄດ້ ແລະ ຄືນຂໍ້ມູນຜູ້ໃຊ້', function () {
        $this->postJson('/login', [
            'email' => 'phanh@example.com',
            'password' => 'correct-horse',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'phanh@example.com');

        $this->assertAuthenticatedAs($this->user);
    });

    it('ບໍ່ຮົ່ວລະຫັດຜ່ານກັບອອກ response', function () {
        $response = $this->postJson('/login', [
            'email' => 'phanh@example.com',
            'password' => 'correct-horse',
        ])->assertOk();

        expect($response->getContent())->not->toContain('correct-horse')
            ->and($response->json('user'))->not->toHaveKey('password');
    });

    it('ໃຊ້ຂໍ້ຄວາມດຽວກັນບໍ່ວ່າອີເມວຜິດ ຫຼື ລະຫັດຜິດ', function () {
        // ຖ້າຂໍ້ຄວາມຕ່າງກັນ ຜູ້ໂຈມຕີຈະນັບໄດ້ວ່າອີເມວໃດມີໃນລະບົບ (user enumeration)
        $wrongPassword = $this->postJson('/login', [
            'email' => 'phanh@example.com',
            'password' => 'ຜິດ',
        ])->assertStatus(422);

        $unknownEmail = $this->postJson('/login', [
            'email' => 'ບໍ່ມີ@example.com',
            'password' => 'ຜິດ',
        ])->assertStatus(422);

        expect($wrongPassword->json('errors.email'))
            ->toBe($unknownEmail->json('errors.email'))
            ->toBe(['ອີເມວ ຫຼື ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ']);

        $this->assertGuest();
    });

    it('ກວດ input ທີ່ຂາດ', function (array $payload, string $field) {
        $this->postJson('/login', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    })->with([
        'ບໍ່ມີອີເມວ' => [['password' => 'x'], 'email'],
        'ບໍ່ມີລະຫັດຜ່ານ' => [['email' => 'phanh@example.com'], 'password'],
        'ອີເມວຜິດຮູບແບບ' => [['email' => 'ບໍ່ແມ່ນອີເມວ', 'password' => 'x'], 'email'],
    ]);

    it('ປ່ຽນ session id ຫຼັງ login — ກັນ session fixation', function () {
        $this->get('/');
        $before = session()->getId();

        $this->postJson('/login', [
            'email' => 'phanh@example.com',
            'password' => 'correct-horse',
        ])->assertOk();

        expect(session()->getId())->not->toBe($before);
    });

    it('ຈຳກັດ 5 ຄັ້ງຕໍ່ນາທີ (ພາກ 9)', function () {
        foreach (range(1, 5) as $ignored) {
            $this->postJson('/login', ['email' => 'phanh@example.com', 'password' => 'ຜິດ'])
                ->assertStatus(422);
        }

        $this->postJson('/login', ['email' => 'phanh@example.com', 'password' => 'correct-horse'])
            ->assertStatus(429);
    });

    it('ຜູ້ທີ່ login ຢູ່ແລ້ວເອີ້ນ login ຊ້ຳບໍ່ໄດ້', function () {
        $this->actingAs($this->user)
            ->postJson('/login', ['email' => 'phanh@example.com', 'password' => 'correct-horse'])
            ->assertStatus(302);   // middleware `guest` ເຕະກັບ
    });
});

describe('ຈື່ຂ້ອຍໄວ້', function () {
    /** cookie ຊື່ໃດໜຶ່ງໃນ response — ຄືນ null ຖ້າບໍ່ໄດ້ຕັ້ງ */
    function cookieFrom(TestResponse $response, string $prefix): ?Cookie
    {
        return collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie) => str_starts_with($cookie->getName(), $prefix));
    }

    function login(array $overrides = []): TestResponse
    {
        return test()->postJson('/login', [
            'email' => 'phanh@example.com',
            'password' => 'correct-horse',
            ...$overrides,
        ]);
    }

    it('ຕິ໊ກແລ້ວອອກ recaller cookie ອາຍຸຍາວ', function () {
        $response = login(['remember' => true])->assertOk();

        $recaller = cookieFrom($response, 'remember_web');

        expect($recaller)->not->toBeNull()
            ->and($recaller->getExpiresTime())->toBeGreaterThan(now()->addYear()->getTimestamp())
            ->and($this->user->fresh()->remember_token)->not->toBeNull();
    });

    it('ຕິ໊ກແລ້ວຍັງເຂົ້າ API ໄດ້ຫຼັງ session ຫາຍໄປ', function () {
        // ນີ້ຄືເຫດຜົນທັງໝົດຂອງ checkbox — ຖ້າອັນນີ້ຕົກ ຜູ້ໃຊ້ຕ້ອງ login ໃໝ່
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        $recaller = cookieFrom(login(['remember' => true])->assertOk(), 'remember_web');

        // ຈຳລອງ session ໝົດອາຍຸ: ເຫຼືອແຕ່ recaller cookie ຢູ່ໃນ browser
        $this->app['session']->driver()->flush();
        $this->flushSession();
        Auth::forgetGuards();

        $this->withCredentials()
            ->withUnencryptedCookie($recaller->getName(), $recaller->getValue())
            ->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'phanh@example.com');
    });

    it('ບໍ່ຕິ໊ກແລ້ວ session cookie ຕາຍຕອນປິດ browser', function () {
        $response = login()->assertOk();

        // expires = 0 ແປວ່າ "session cookie" — browser ລຶບຖິ້ມຕອນປິດ
        expect(cookieFrom($response, config('session.cookie'))->getExpiresTime())->toBe(0);
    });

    it('ບໍ່ຕິ໊ກແລ້ວລຶບ recaller ຂອງຄັ້ງກ່ອນ', function () {
        // ຖ້າບໍ່ລຶບ cookie ອາຍຸ 5 ປີ ຈາກຄັ້ງທີ່ເຄີຍຕິ໊ກຈະດຶງກັບເຂົ້າມາຢູ່ດີ
        $recaller = cookieFrom(login(['remember' => true])->assertOk(), 'remember_web');

        Auth::guard('web')->logout();
        $this->flushSession();

        $forgotten = cookieFrom(login(['remember' => false])->assertOk(), 'remember_web');

        expect($forgotten)->not->toBeNull()
            ->and($forgotten->getName())->toBe($recaller->getName())
            ->and($forgotten->getExpiresTime())->toBeLessThan(now()->getTimestamp());
    });
});

describe('POST /logout', function () {
    it('ອອກຈາກລະບົບໄດ້', function () {
        $this->actingAs($this->user)->postJson('/logout')->assertOk();

        $this->assertGuest();
    });

    it('ຕ້ອງ login ກ່ອນ', function () {
        $this->postJson('/logout')->assertUnauthorized();
    });

    it('ຫຼັງ logout ເອີ້ນ API ບໍ່ໄດ້ອີກ', function () {
        // Sanctum ຕັດສິນວ່າ request ເປັນ stateful ຈາກ header Origin/Referer.
        // browser ສົ່ງໃຫ້ເອງທຸກ XHR ແຕ່ test client ບໍ່ສົ່ງ — ຖ້າບໍ່ຈຳລອງ
        // ອັນນີ້ test ຈະບໍ່ໄດ້ຍ່າງຜ່ານເສັ້ນທາງ cookie ຈິງເລີຍ.
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost');

        // login ຈິງ ບໍ່ແມ່ນ actingAs() — actingAs ຄ້າງ user ໄວ້ຂ້າມ request
        $this->postJson('/login', [
            'email' => 'phanh@example.com',
            'password' => 'correct-horse',
        ])->assertOk();

        // ຫໍ່ດ້ວຍ `data` ຄືກັບທຸກ endpoint ອື່ນ — UserResource ພ່ວງຂໍ້ມູນ
        // ອົງກອນ ແລະ ອາຍຸການໃຊ້ງານມານຳ ເພື່ອໃຫ້ SPA ຮູ້ຕັ້ງແຕ່ບູດ
        $this->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.email', 'phanh@example.com');

        $this->postJson('/logout')->assertOk();

        // ລະບຸ guard 'web' ຢ່າງຈະແຈ້ງ: middleware auth:sanctum ປ່ຽນ guard ຕັ້ງຕົ້ນ
        // ເປັນ 'sanctum' ແລະ Laravel cache ໄວ້ຂ້າມ request ພາຍໃນ test ດຽວກັນ
        // (browser ບໍ່ເປັນແບບນັ້ນ — ແຕ່ລະ request ຄື process ໃໝ່)
        $this->assertGuest('web');

        // ລ້າງ guard ທີ່ cache ໄວ້ ເພື່ອຈຳລອງ request ໃໝ່ (browser = process ໃໝ່ທຸກຄັ້ງ)
        Auth::forgetGuards();

        $this->getJson('/api/v1/user')->assertUnauthorized();
    });
});

describe('SPA shell', function () {
    it('ຄືນ shell ດຽວກັນໃຫ້ທຸກ route ຂອງ frontend', function (string $path) {
        $this->get($path)
            ->assertOk()
            ->assertSee('id="app"', false);
    })->with(['/', '/login', '/expenses', '/reports/by-category']);

    it('ບໍ່ກືນ route ຂອງ API', function () {
        // ຖ້າ catch-all ກືນ /api/* ໄປ endpoint ທັງໝົດຈະຕອບເປັນ HTML
        $this->getJson('/api/v1/currencies')->assertUnauthorized();
    });

    it('ບໍ່ກືນ health check ຂອງ Laravel', function () {
        $this->get('/up')->assertOk()->assertDontSee('id="app"', false);
    });
});
