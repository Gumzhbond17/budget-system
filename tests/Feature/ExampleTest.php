<?php

declare(strict_types=1);

test('returns a successful response', function () {
    // route 'home' ຂອງ starter kit ຖືກແທນດ້ວຍ catch-all 'spa' ຕອນຍ້າຍໄປເປັນ SPA
    $this->get(route('spa'))->assertOk();
});
