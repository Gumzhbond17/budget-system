{{-- ລາຍງານລາຍຮັບ — ເບິ່ງ reports/layout.blade.php ສຳລັບ style ແລະ ຫົວ/ທ້າຍໜ້າ --}}
@use('App\Support\ReportFormat', 'F')
@extends('reports.layout')

@section('title', 'ລາຍງານລາຍຮັບ')

@section('criteria')
    <td style="border: none; padding: 0.4mm 0;">
        <b>ແຫຼ່ງທຶນ:</b> {{ $payerLabel }}
    </td>
@endsection

@section('body')
    {{-- ---------- ຍອດລວມ ---------- --}}
    <table class="cards">
        <tr>
            <td style="width: 25%;">
                <div class="card-label">ຈຳນວນລາຍການ</div>
                <div class="card-value">{{ F::count($summary['item_count']) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="card-label">ລວມ (USD)</div>
                <div class="card-value in">{{ F::money($summary['total_usd']) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="card-label">ລວມ (LAK)</div>
                <div class="card-value in">{{ F::money($summary['total_lak']) }}</div>
            </td>
            <td style="width: 25%;">
                <div class="card-label">ຍັງບໍ່ມີຫຼັກຖານແນບ</div>
                <div class="card-value {{ $summary['missing_evidence_count'] > 0 ? 'warn' : '' }}">
                    {{ F::count($summary['missing_evidence_count']) }}
                </div>
            </td>
        </tr>
    </table>

    {{-- ---------- ສະຫຼຸບຕາມແຫຼ່ງທຶນ ---------- --}}
    <h2>ສະຫຼຸບຕາມແຫຼ່ງທຶນ</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 40%;">ແຫຼ່ງທຶນ</th>
                <th class="num" style="width: 12%;">ຈຳນວນລາຍການ</th>
                <th class="num" style="width: 18%;">USD</th>
                <th class="num" style="width: 20%;">LAK</th>
                <th class="num" style="width: 10%;">ສັດສ່ວນ</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byPayer as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="num">{{ F::count($row['count']) }}</td>
                    <td class="num">{{ F::money($row['usd']) }}</td>
                    <td class="num">{{ F::money($row['lak']) }}</td>
                    <td class="num">{{ $row['percentage'] }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">ບໍ່ມີລາຍການທີ່ກົງກັບຕົວກັ່ນຕອງນີ້</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- ---------- ລາຍການລະອຽດ ---------- --}}
    <h2>ລາຍການລະອຽດ ({{ F::count($revenues->count()) }} ລາຍການ)</h2>

    <table>
        <thead>
            <tr>
                <th class="num" style="width: 3%;">ລ/ດ</th>
                <th style="width: 8%;">ວັນທີ</th>
                <th style="width: 10%;">ເລກທີ່</th>
                <th style="width: 26%;">ລາຍລະອຽດ</th>
                <th style="width: 13%;">ແຫຼ່ງທຶນ</th>
                <th style="width: 10%;">ເລກອ້າງອີງ</th>
                <th class="num" style="width: 12%;">ຈຳນວນເງິນ</th>
                <th style="width: 4%;">ສະກຸນ</th>
                <th class="num" style="width: 7%;">ອັດຕາ</th>
                <th class="num" style="width: 9%;">USD</th>
                <th class="num" style="width: 7%;">ຫຼັກຖານ</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($revenues as $index => $revenue)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>{{ $revenue->received_date->format('d/m/Y') }}</td>
                    <td class="tiny">{{ $revenue->doc_no ?? '—' }}</td>

                    {{-- Blade escape ໃຫ້ອັດຕະໂນມັດ — title ມາຈາກຜູ້ໃຊ້ --}}
                    <td>
                        {{ $revenue->title }}
                        @if ($revenue->status !== 'approved')
                            <span class="tiny muted">[{{ F::status($revenue->status) }}]</span>
                        @endif
                    </td>

                    <td>{{ $revenue->payer?->name ?? '—' }}</td>
                    <td class="tiny">{{ $revenue->reference ?? '—' }}</td>
                    <td class="num">{{ $revenue->amount_minor->toFormatted() }}</td>
                    <td>{{ $revenue->currency_code }}</td>
                    <td class="num">{{ F::rate($revenue->fx_rate_per_usd) }}</td>
                    <td class="num">{{ F::usd($revenue->amount_usd_minor) }}</td>
                    <td class="num">
                        {{-- ຫ້າມໃຊ້ ✓ (U+2713): Phetsarath OT ບໍ່ມີ glyph ນັ້ນ ແລະ PDF ຝັງ
                             ຟອນດຽວ ຈຶ່ງຈະອອກເປັນຊ່ອງຫວ່າງ — ໃຊ້ຄຳລາວແທນ --}}
                        @if ($revenue->attachments_count > 0)
                            ມີ ({{ $revenue->attachments_count }})
                        @else
                            <span class="warn">ບໍ່ມີ</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="muted">ບໍ່ມີລາຍການທີ່ກົງກັບຕົວກັ່ນຕອງນີ້</td>
                </tr>
            @endforelse
        </tbody>

        @if ($revenues->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="9">ລວມທັງໝົດ</td>
                    <td class="num in">{{ F::money($summary['total_usd']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
@endsection
