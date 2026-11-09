{{--
    ລາຍງານລາຍຮັບ-ລາຍຈ່າຍ — ສ່ວນສະຫຼຸບ (ຕໍ່ເດືອນ) + ສ່ວນລະອຽດ (ຍອດຄົງເຫຼືອແລ່ນ).
    ເບິ່ງ reports/layout.blade.php ສຳລັບ style ແລະ ຫົວ/ທ້າຍໜ້າ.

    ຄໍລຳຈຳນວນເປັນ **ກີບ** ຄືກັນກັບໜ້າ /ledger ຢູ່ SPA — ນັກບັນຊີຄິດເປັນກີບ.
    USD ຢູ່ຄໍລຳທ້າຍເປັນຕົວທຽບ.
--}}
@use('App\Support\ReportFormat', 'F')
@extends('reports.layout')

@section('title', 'ລາຍງານລາຍຮັບ-ລາຍຈ່າຍ')

@section('criteria')
    <td style="border: none; padding: 0.4mm 0;">
        <b>ຜູ້ຈ່າຍ:</b> {{ $payerLabel }}
    </td>
@endsection

@section('body')
    {{-- ================= ສ່ວນສະຫຼຸບ ================= --}}
    <table class="cards">
        <tr>
            <td style="width: 25%;">
                <div class="card-label">ລວມລາຍຮັບ (LAK)</div>
                <div class="card-value in">{{ F::money($totals['revenue_lak']) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="card-label">ລວມລາຍຈ່າຍ (LAK)</div>
                <div class="card-value out">{{ F::money($totals['expense_lak']) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="card-label">ຍອດຄົງເຫຼືອ (LAK)</div>
                {{-- ຕິດລົບ = ຈ່າຍເກີນເງິນທີ່ຮັບມາ — ຕ້ອງແດງ ບໍ່ໃຫ້ເລື່ອນຜ່ານ --}}
                <div class="card-value {{ $totals['balance_lak']['minor'] < 0 ? 'out' : 'in' }}">
                    {{ F::money($totals['balance_lak']) }}
                </div>
            </td>
            <td style="width: 25%;">
                <div class="card-label">ງົບປະມານທີ່ຍັງເຫຼືອ (LAK)</div>
                <div class="card-value">
                    @if ($totals['budget_remaining_lak'] !== null)
                        {{ F::money($totals['budget_remaining_lak']) }}
                    @elseif ($totals['budget_currency'] !== null)
                        <span class="tiny muted">ງົບຕັ້ງເປັນ {{ $totals['budget_currency'] }}</span>
                    @else
                        <span class="tiny muted">ຍັງບໍ່ໄດ້ຕັ້ງງົບ</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <h2>ສະຫຼຸບຕໍ່ເດືອນ</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 12%;">ເດືອນ</th>
                <th class="num" style="width: 8%;">ລາຍການ</th>
                <th class="num" style="width: 16%;">ລາຍຮັບ (LAK)</th>
                <th class="num" style="width: 16%;">ລາຍຈ່າຍ (LAK)</th>
                <th class="num" style="width: 16%;">ສຸດທິ (LAK)</th>
                <th class="num" style="width: 16%;">ຍອດຄົງເຫຼືອ (LAK)</th>
                <th class="num" style="width: 16%;">ຍອດຄົງເຫຼືອ (USD)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($monthly as $month)
                <tr>
                    <td>{{ $month['month'] }}</td>
                    <td class="num">{{ F::count($month['count']) }}</td>
                    <td class="num in">{{ F::money($month['revenue_lak']) }}</td>
                    <td class="num out">{{ F::money($month['expense_lak']) }}</td>
                    <td class="num {{ $month['net_lak']['minor'] < 0 ? 'out' : 'in' }}">
                        {{ F::money($month['net_lak']) }}
                    </td>
                    <td class="num {{ $month['balance_lak']['minor'] < 0 ? 'out' : '' }}">
                        {{ F::money($month['balance_lak']) }}
                    </td>
                    <td class="num {{ $month['balance_usd']['minor'] < 0 ? 'out' : '' }}">
                        {{ F::money($month['balance_usd']) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted">ບໍ່ມີລາຍການທີ່ກົງກັບຕົວກັ່ນຕອງນີ້</td>
                </tr>
            @endforelse
        </tbody>

        @if ($monthly !== [])
            <tfoot>
                <tr>
                    <td>ລວມ</td>
                    <td class="num">{{ F::count($totals['entry_count']) }}</td>
                    <td class="num in">{{ F::money($totals['revenue_lak']) }}</td>
                    <td class="num out">{{ F::money($totals['expense_lak']) }}</td>
                    <td class="num {{ $totals['balance_lak']['minor'] < 0 ? 'out' : 'in' }}">
                        {{ F::money($totals['balance_lak']) }}
                    </td>
                    <td class="num">{{ F::money($totals['balance_lak']) }}</td>
                    <td class="num">{{ F::money($totals['balance_usd']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    {{-- ================= ສ່ວນລະອຽດ ================= --}}
    <h2>ລາຍການລະອຽດ ({{ F::count($totals['entry_count']) }} ລາຍການ)</h2>

    <table>
        <thead>
            <tr>
                <th class="num" style="width: 3%;">ລ/ດ</th>
                <th style="width: 8%;">ວັນທີ</th>
                <th style="width: 10%;">ເລກທີ່</th>
                <th style="width: 27%;">ລາຍລະອຽດ</th>
                <th style="width: 12%;">ຄູ່ຄ້າ</th>
                {{--
                    ສອງຄໍລຳແຍກ (ເດບິດ/ເຄຣດິດ) ບໍ່ແມ່ນຄໍລຳດຽວທີ່ມີເຄື່ອງໝາຍ —
                    ນີ້ຄືຮູບແບບບັນຊີແຍກປະເພດມາດຕະຖານ ແລະ ອ່ານງ່າຍກວ່າຫຼາຍ
                    ເມື່ອຕ້ອງກວາດຕາຫາລາຍການປະເພດດຽວ.
                --}}
                <th class="num" style="width: 13%;">ລາຍຮັບ (LAK)</th>
                <th class="num" style="width: 13%;">ລາຍຈ່າຍ (LAK)</th>
                <th class="num" style="width: 14%;">ຍອດຄົງເຫຼືອ (LAK)</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($entries as $index => $entry)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($entry['date'])->format('d/m/Y') }}</td>
                    <td class="tiny">{{ $entry['doc_no'] ?? '—' }}</td>

                    {{-- Blade escape ໃຫ້ອັດຕະໂນມັດ — title ມາຈາກຜູ້ໃຊ້ --}}
                    <td>
                        {{ $entry['title'] }}
                        <span class="tiny muted">
                            · {{ F::money($entry['amount']) }} {{ $entry['amount']['currency'] }}
                            @if ($entry['status'] !== 'approved')
                                · [{{ F::status($entry['status']) }}]
                            @endif
                        </span>
                    </td>

                    <td>{{ $entry['counterparty'] ?? '—' }}</td>

                    <td class="num in">
                        {{ $entry['type'] === 'revenue' ? '+'.F::money($entry['amount_lak']) : '—' }}
                    </td>
                    {{-- ເຄື່ອງໝາຍລົບຕ້ອງເປັນ ASCII '-' (U+002D) ບໍ່ແມ່ນ '−' (U+2212):
                         Phetsarath OT ບໍ່ມີ glyph ຂອງ U+2212 ແລະ PDF ຝັງຟອນດຽວ
                         ຈຶ່ງຈະອອກເປັນຊ່ອງຫວ່າງ ເຮັດໃຫ້ອ່ານຍອດຕິດລົບເປັນຍອດບວກ --}}
                    <td class="num out">
                        {{ $entry['type'] === 'expense' ? '-'.F::money($entry['amount_lak']) : '—' }}
                    </td>
                    <td class="num {{ $entry['balance_lak']['minor'] < 0 ? 'out' : '' }}">
                        {{ F::money($entry['balance_lak']) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">ບໍ່ມີລາຍການທີ່ກົງກັບຕົວກັ່ນຕອງນີ້</td>
                </tr>
            @endforelse
        </tbody>

        @if ($entries !== [])
            <tfoot>
                <tr>
                    <td colspan="5">ລວມທັງໝົດ</td>
                    <td class="num in">+{{ F::money($totals['revenue_lak']) }}</td>
                    <td class="num out">-{{ F::money($totals['expense_lak']) }}</td>
                    <td class="num {{ $totals['balance_lak']['minor'] < 0 ? 'out' : '' }}">
                        {{ F::money($totals['balance_lak']) }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
