{{--
    ໂຄງຮ່າງຮ່ວມຂອງລາຍງານ PDF ທຸກສະບັບ (ລາຍຮັບ / ລາຍຈ່າຍ / ບັນຊີແຍກປະເພດ).

    ⚠️ mPDF ຮອງຮັບ CSS ຊຸດຈຳກັດ — ບໍ່ມີ flexbox/grid. ຈັດໜ້າດ້ວຍ table ເທົ່ານັ້ນ.
    ຟອນ 'phetsarath' ຖືກລົງທະບຽນຢູ່ App\Services\Reports\ReportPdfRenderer
    ພ້ອມ useOTL = 0xFF ເຊິ່ງເປັນສິ່ງດຽວທີ່ເຮັດໃຫ້ວັນນະຍຸດລາວວາງຖືກຕຳແໜ່ງ.

    ຄ່າຕົວເລກທັງໝົດຜ່ານ App\Support\ReportFormat — ຫ້າມໃຊ້ number_format()
    ກັບຈຳນວນເງິນ ເພາະມັນຮັບແຕ່ float (docs/architecture.md ພາກ 1.4).

    section ທີ່ລູກຕ້ອງໃຫ້:
      title    — ຊື່ລາຍງານ (ຂໍ້ຄວາມລ້ວນ, ໃຊ້ຢູ່ຫົວໜ້າທຸກໜ້າ ແລະ ຫົວເລື່ອງ)
      criteria — ແຖວ <td> ຂອງຕົວກັ່ນຕອງ
      body     — ເນື້ອໃນລາຍງານ
--}}
@use('App\Support\ReportFormat', 'F')
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            header: html_report-header;
            footer: html_report-footer;
        }

        body {
            font-family: phetsarath, sans-serif;
            font-size: 8pt;
            color: #1e293b;
        }

        h1 { font-size: 13pt; margin: 0 0 2mm; }
        h2 { font-size: 9.5pt; margin: 5mm 0 1.5mm; color: #334155; }

        .muted { color: #64748b; }
        .tiny  { font-size: 7pt; }

        /* ຕົວເລກຊິດຂວາສະເໝີ ແລະ ຫ້າມຕັດແຖວ — ຫຼັກຕົວເລກຕ້ອງຊ້ອນກັນຕົງ */
        .num { text-align: right; white-space: nowrap; }

        table { width: 100%; border-collapse: collapse; }

        th {
            background: #f1f5f9;
            border-bottom: 0.6pt solid #94a3b8;
            padding: 1.6mm 1.4mm;
            text-align: left;
            font-size: 7.5pt;
            font-weight: normal;
            color: #334155;
        }

        td {
            border-bottom: 0.2pt solid #e2e8f0;
            padding: 1.4mm;
            vertical-align: top;
        }

        tfoot td {
            border-top: 0.6pt solid #94a3b8;
            border-bottom: none;
            font-weight: bold;
            background: #f8fafc;
        }

        /* ຫົວຕາຕະລາງຊ້ຳທຸກໜ້າ ແລະ ຫ້າມແຖວດຽວແຍກຄາໜ້າ */
        thead { display: table-header-group; }
        tr    { page-break-inside: avoid; }

        /* ---------- ກ່ອງຍອດລວມ ---------- */
        .cards td {
            border: 0.4pt solid #e2e8f0;
            padding: 2.5mm;
        }
        .card-label { font-size: 7pt; color: #64748b; }
        .card-value { font-size: 12pt; font-weight: bold; }

        /* ລາຍຮັບຂຽວ ລາຍຈ່າຍແດງ — ຄືກັນກັບໜ້າຈໍ ເພື່ອບໍ່ໃຫ້ອ່ານສະຫຼັບກັນ */
        .in   { color: #15803d; }
        .out  { color: #b91c1c; }
        .warn { color: #b91c1c; }
    </style>
</head>
<body>

{{-- ຫົວ/ທ້າຍໜ້າ ຊ້ຳທຸກໜ້າ — ຜູ້ກວດສອບຕ້ອງຮູ້ວ່າແຜ່ນທີ່ຫຼຸດອອກມາເປັນຂອງລາຍງານໃດ --}}
<htmlpageheader name="report-header">
    <table style="border-bottom: 0.4pt solid #cbd5e1;">
        <tr>
            <td style="border: none; padding: 0 0 1.5mm;">
                <span style="font-size: 9pt; font-weight: bold;">@yield('title') — {{ $project->name }}</span>
                <span class="tiny muted"> ({{ $project->code }})</span>
            </td>
            <td class="num tiny muted" style="border: none; padding: 0 0 1.5mm;">
                ພິມເມື່ອ {{ $generatedAt->format('d/m/Y H:i') }} · {{ $generatedBy }}
            </td>
        </tr>
    </table>
</htmlpageheader>

<htmlpagefooter name="report-footer">
    <table style="border-top: 0.4pt solid #cbd5e1;">
        <tr>
            <td class="tiny muted" style="border: none; padding: 1.5mm 0 0;">
                ຍອດ USD/LAK ແປງດ້ວຍອັດຕາທີ່ບັນທຶກໄວ້ໃນແຕ່ລະລາຍການ (snapshot) ບໍ່ແມ່ນອັດຕາປັດຈຸບັນ.
            </td>
            <td class="num tiny muted" style="border: none; padding: 1.5mm 0 0;">
                ໜ້າ {PAGENO} / {nbpg}
            </td>
        </tr>
    </table>
</htmlpagefooter>

<h1>@yield('title')</h1>

<table class="tiny muted" style="margin-bottom: 4mm;">
    <tr>
        <td style="border: none; padding: 0.4mm 0;">
            <b>ຊ່ວງວັນທີ:</b> {{ $filters->dateFrom ?? 'ເລີ່ມຕົ້ນ' }} ຫາ {{ $filters->dateTo ?? 'ປັດຈຸບັນ' }}
        </td>
        <td style="border: none; padding: 0.4mm 0;">
            <b>ຄຳຄົ້ນຫາ:</b> {{ $filters->search ?? '—' }}
        </td>
        @yield('criteria')
        <td style="border: none; padding: 0.4mm 0;">
            <b>ສະຖານະທີ່ນັບ:</b> {{ F::statusList($filters->statuses) }}
        </td>
    </tr>
</table>

@yield('body')

</body>
</html>
