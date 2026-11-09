<?php

declare(strict_types=1);

/**
 * ໄຟລ໌ພາສາຂອງ SPA ຕ້ອງມີ key ຄົບຄືກັນທັງສອງພາສາ.
 *
 * ຖ້າ key ຂາດ vue-i18n ຈະ render ຊື່ key ດິບອອກໜ້າຈໍ (ເຊັ່ນ "auth.signIn")
 * ເຊິ່ງບໍ່ພັງ build ແລະ ບໍ່ພັງ type check — ຈັບໄດ້ຕອນເຫັນຢູ່ໜ້າຈໍເທົ່ານັ້ນ.
 */

/**
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function flattenKeys(array $data, string $prefix = ''): array
{
    $keys = [];

    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $keys = [...$keys, ...flattenKeys($value, $path)];

            continue;
        }

        $keys[] = $path;
    }

    return $keys;
}

/**
 * @return array<string, mixed>
 */
function loadLocale(string $locale): array
{
    $path = resource_path("js/i18n/locales/{$locale}.json");

    expect(file_exists($path))->toBeTrue("ບໍ່ພົບໄຟລ໌ພາສາ: {$path}");

    /** @var array<string, mixed> $data */
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    return $data;
}

it('ໄຟລ໌ພາສາເປັນ JSON ທີ່ຖືກຕ້ອງ', function (string $locale) {
    expect(loadLocale($locale))->toBeArray()->not->toBeEmpty();
})->with(['lo', 'en']);

it('ພາສາລາວ ແລະ ອັງກິດ ມີ key ຄົບຄືກັນ', function () {
    $lo = flattenKeys(loadLocale('lo'));
    $en = flattenKeys(loadLocale('en'));

    sort($lo);
    sort($en);

    expect(array_values(array_diff($en, $lo)))->toBe([], 'key ເຫຼົ່ານີ້ຂາດໃນ lo.json')
        ->and(array_values(array_diff($lo, $en)))->toBe([], 'key ເຫຼົ່ານີ້ຂາດໃນ en.json');
});

it('ບໍ່ມີຄຳແປທີ່ຫວ່າງເປົ່າ', function (string $locale) {
    $data = loadLocale($locale);
    $empty = [];

    array_walk_recursive($data, function (mixed $value, string $key) use (&$empty): void {
        if (is_string($value) && trim($value) === '') {
            $empty[] = $key;
        }
    });

    expect($empty)->toBe([]);
})->with(['lo', 'en']);

it('key ຂອງໜ້າ login ມີຄົບທັງສອງພາສາ', function (string $key) {
    foreach (['lo', 'en'] as $locale) {
        expect(flattenKeys(loadLocale($locale)))->toContain($key);
    }
})->with([
    'app.name',
    'app.tagline',
    'common.language',
    'auth.signInTitle',
    'auth.signInSubtitle',
    'auth.email',
    'auth.enterEmail',
    'auth.password',
    'auth.enterPassword',
    'auth.keepLoggedIn',
    'auth.signIn',
    'auth.signingIn',
    'auth.signOut',
    'auth.showPassword',
    'auth.hidePassword',
]);
