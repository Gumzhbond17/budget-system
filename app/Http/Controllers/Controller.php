<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // ພາກ 9: "ທຸກ endpoint ຕ້ອງມີ Policy" — $this->authorize() ຕ້ອງໃຊ້ໄດ້ທົ່ວ
    use AuthorizesRequests;
}
