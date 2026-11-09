<?php

declare(strict_types=1);

namespace App\Domain\Money;

use InvalidArgumentException;

/**
 * ຕົວກອງ input ຂອງ bcmath.
 *
 * bcmath ຮັບແຕ່ຮູບແບບ "-?ຕົວເລກ[.ຕົວເລກ]" ເທົ່ານັ້ນ — "1e5", " 12" ຫຼື "12 "
 * ຈະ throw ValueError ອອກມາເປັນ 500 ແທນທີ່ຈະເປັນ validation error ທີ່ອ່ານເຂົ້າໃຈໄດ້.
 * (is_numeric() ຢ່າງດຽວບໍ່ພຽງພໍ — ມັນຮັບທັງສາມແບບນັ້ນ.)
 */
final class DecimalString
{
    private const PATTERN = '/^-?\d+(\.\d+)?$/';

    /**
     * @param  string  $context  ຊື່ field ສຳລັບຂໍ້ຄວາມ error
     * @return numeric-string
     *
     * @throws InvalidArgumentException
     */
    public static function assert(string $value, string $context): string
    {
        // ເງື່ອນໄຂທີສອງແມ່ນສຳລັບ static analysis — regex ຄືກົດຈິງ
        if (preg_match(self::PATTERN, $value) !== 1 || ! is_numeric($value)) {
            throw new InvalidArgumentException(
                "{$context} must be a plain decimal number, \"{$value}\" given"
            );
        }

        return $value;
    }
}
