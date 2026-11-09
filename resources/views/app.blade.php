<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ຊື່ຈາກ APP_NAME — ບໍ່ hardcode --}}
    <title>{{ config('app.name') }}</title>
    {{-- SVG favicon: ~1 KB, ຄົມທຸກຂະໜາດ, ອອກແບບກົງກັບ AppLogo.vue --}}
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    {{--
        ຕັ້ງ theme ກ່ອນ Vue mount ເພື່ອກັນຈໍກະພິບຂາວ (FOUC) ຕອນຜູ້ໃຊ້ເລືອກ dark mode.
        ThemeProvider.vue ຈະອ່ານຄ່າດຽວກັນນີ້ຕໍ່.
    --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var dark = stored === 'dark' || (!stored && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {
                /* private mode — ໃຊ້ light ໄປ */
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
