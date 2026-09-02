<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>{{ $event->name }} 개별심사표 — {{ $judge->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* 화면 미리보기 = 실제 A4 용지와 동일한 폭·여백 */
        html { background: #e2e8f0; }
        body {
            font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', sans-serif;
            color: #111; font-size: 12px;
            width: 210mm; min-height: 297mm; margin: 16px auto;
            padding: 15mm 18mm;
            background: #fff; box-shadow: 0 2px 14px rgba(15, 23, 42, 0.25);
        }
        h1 { text-align: center; font-size: 22px; margin-bottom: 14px; }

        /* 제목 아래: 좌측 메타, 우측 심사위원 서명란 */
        .meta-row {
            display: flex; justify-content: space-between; align-items: flex-end;
            margin-bottom: 14px;
        }
        .meta { color: #777; font-size: 11px; }
        .judge-line { font-size: 14px; white-space: nowrap; }
        .judge-line strong { font-size: 15px; }
        .sig-line {
            position: relative;
            display: inline-block; width: 200px; height: 52px;
            border-bottom: 1px solid #555;
            vertical-align: bottom; text-align: center;
            margin-left: 10px;
        }
        /* (서명) 라벨을 밑줄 안쪽 가운데에 연하게 — 그 위에 자필 서명 */
        .sig-label {
            position: absolute; left: 0; right: 0; bottom: 4px;
            color: #ccc; font-size: 10px;
        }
        /* 자필 서명은 (서명) 라벨 위에 겹쳐 놓는다 */
        .sig-line img {
            position: absolute; left: 50%; bottom: 1px; transform: translateX(-50%);
            z-index: 1; max-height: 50px; max-width: 196px;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #333; padding: 7px 6px; text-align: center; }
        th { background: #f0f0f0; font-size: 11px; }
        td.name { text-align: left; padding-left: 10px; }
        .total { font-weight: bold; background: #fafafa; }

        .confirm { margin-top: 28px; text-align: center; font-size: 13px; }
        .confirm .date { margin-top: 10px; color: #444; }

        .toolbar { text-align: center; margin-bottom: 24px; }
        .toolbar button {
            background: #4f46e5; color: #fff; border: 0; border-radius: 8px;
            padding: 10px 28px; font-size: 14px; cursor: pointer;
        }
        @media print {
            .toolbar { display: none; }
            html { background: none; }
            body { width: auto; min-height: 0; margin: 0; padding: 0 6mm; box-shadow: none; } /* 좌우 여백 보강 */
            @page { size: A4 portrait; margin: 15mm 18mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">🖨️ 인쇄하기</button></div>

    <h1>{{ $event->name }} 개별심사표</h1>

    <div class="meta-row">
        <span class="meta">
            @if ($event->event_date) 행사일: {{ $event->event_date->format('Y년 m월 d일') }} · @endif
            출력일시: {{ now()->format('Y-m-d H:i') }}
        </span>
        <span class="judge-line">
            심사위원: <strong>{{ $judge->name }}</strong>
            <span class="sig-line">
                <span class="sig-label">(서명)</span>
                @if ($judge->signature)
                    <img src="{{ $judge->signature }}" alt="서명">
                @endif
            </span>
        </span>
    </div>

    @php
        // 2단계 평가항목: 대분류(서브항목 있으면 서브가 채점 대상, 없으면 대분류 자신)
        $tops     = $event->criteria->whereNull('parent_id')->values();
        $byParent = $event->criteria->groupBy('parent_id');
        $leaves   = collect();
        foreach ($tops as $top) {
            $kids = $byParent->get($top->id, collect());
            if ($kids->isEmpty()) { $leaves->push($top); } else { $leaves = $leaves->concat($kids); }
        }
        $hasSub = $tops->contains(fn ($t) => $byParent->get($t->id, collect())->isNotEmpty());
        $fmt = fn ($n) => rtrim(rtrim(number_format($n, 1), '0'), '.');
    @endphp

    <table>
        <thead>
            @if ($hasSub)
                <tr>
                    <th style="width:64px" rowspan="2">심사번호</th>
                    @unless ($event->is_blind)
                        <th rowspan="2">평가 대상</th>
                    @endunless
                    @foreach ($tops as $top)
                        @php $kids = $byParent->get($top->id, collect()); @endphp
                        @if ($kids->isEmpty())
                            <th rowspan="2">{{ $top->name }}<br><span style="font-weight:normal">({{ $top->max_score }}점)</span></th>
                        @else
                            <th colspan="{{ $kids->count() }}">{{ $top->name }} <span style="font-weight:normal">({{ $top->max_score }}점)</span></th>
                        @endif
                    @endforeach
                    <th style="width:60px" rowspan="2">합계<br>(100점)</th>
                </tr>
                <tr>
                    @foreach ($tops as $top)
                        @foreach ($byParent->get($top->id, collect()) as $child)
                            <th>{{ $child->name }}<br><span style="font-weight:normal">({{ $child->max_score }}점)</span></th>
                        @endforeach
                    @endforeach
                </tr>
            @else
                <tr>
                    <th style="width:64px">심사번호</th>
                    @unless ($event->is_blind)
                        <th>평가 대상</th>
                    @endunless
                    @foreach ($leaves as $criterion)
                        <th>{{ $criterion->name }}<br><span style="font-weight:normal">({{ $criterion->max_score }}점)</span></th>
                    @endforeach
                    <th style="width:60px">합계<br>(100점)</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @foreach ($event->candidates as $i => $candidate)
                @php
                    $scores = $myScores->get($candidate->id, collect());
                    $total = $scores->count() ? $scores->sum() : null;
                @endphp
                <tr>
                    {{-- 블라인드면 심사번호만 표기 (이름은 최종집계표에서만), 이름 공개면 대상명 열 추가 --}}
                    <td style="font-weight:bold">{{ sprintf('%02d', $i + 1) }}</td>
                    @unless ($event->is_blind)
                        <td class="name">{{ $candidate->name }}{{ $candidate->affiliation ? ' (' . $candidate->affiliation . ')' : '' }}</td>
                    @endunless
                    @foreach ($leaves as $criterion)
                        <td>{{ $scores->has($criterion->id) ? $fmt($scores->get($criterion->id)) : '' }}</td>
                    @endforeach
                    <td class="total">{{ $total !== null ? $fmt($total) : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="confirm">
        위와 같이 공정하게 심사하였음을 확인합니다.
        <div class="date">{{ now()->format('Y년 m월 d일') }}</div>
    </div>
</body>
</html>
