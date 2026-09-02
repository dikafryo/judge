<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>{{ $event->name }} 최종집계표</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* 화면 미리보기 = 실제 A4 용지와 동일한 폭·여백 */
        html { background: #e2e8f0; }
        body {
            font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', sans-serif;
            color: #111; font-size: 12px;
            width: 210mm; min-height: 297mm; margin: 16px auto;
            padding: 24mm 18mm 12mm;
            background: #fff; box-shadow: 0 2px 14px rgba(15, 23, 42, 0.25);
        }
        h1 { text-align: center; font-size: 22px; margin-bottom: 22px; }
        table.result { width: 100%; border-collapse: collapse; }
        table.result th, table.result td { border: 1px solid #333; padding: 6px 5px; text-align: center; }
        table.result th { background: #f0f0f0; font-size: 11px; }
        table.result td.name { text-align: left; padding-left: 8px; }
        .pass-row { background: #e9f7ef; }
        .pass-cell { color: #067647; font-weight: bold; }
        .tie-cell { color: #b45309; font-weight: bold; }
        .rank1 { background: #fff7e0; font-weight: bold; }
        .sum { font-weight: bold; background: #fafafa; }
        .avg { font-weight: bold; }
        .rank { font-weight: bold; background: #f5f5ff; }
        .excluded { color: #cc0000; text-decoration: line-through; }
        td span.excluded { margin-left: 3px; }
        .note { margin-top: 8px; color: #666; font-size: 10px; }

        /* 심사위원 전원 서명란 */
        .sign-section { margin-top: 32px; page-break-inside: avoid; }
        .sign-title { text-align: center; font-size: 13px; margin-bottom: 4px; }
        .sign-date { text-align: center; font-size: 12px; color: #444; margin-bottom: 16px; }
        .sign-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 32px; }
        .sign-item { display: flex; align-items: flex-end; gap: 8px; font-size: 13px; white-space: nowrap; }
        .sign-item .who { color: #666; font-size: 11px; }
        .sign-item .name { font-weight: bold; font-size: 14px; }
        .sign-line {
            position: relative; display: inline-block;
            flex: none; width: 160px; height: 50px;
            border-bottom: 1px solid #555; text-align: center;
        }
        .sign-line .sig-label {
            position: absolute; left: 0; right: 0; bottom: 3px;
            color: #ccc; font-size: 10px;
        }
        /* 자필 서명은 (서명) 라벨 위에 겹쳐 놓는다 */
        .sign-line img {
            position: absolute; left: 50%; bottom: 1px; transform: translateX(-50%);
            z-index: 1; max-height: 48px; max-width: 96%;
        }

        /* 하단 결재란 — 기록자/검토자/확인자, 우측 정렬 + 열 세로 정렬 */
        .approve-section { margin-top: 30px; page-break-inside: avoid; text-align: right; }
        .approve-table { display: inline-table; border-spacing: 0 9px; font-size: 13px; }
        .approve-table td { padding-left: 12px; text-align: center; vertical-align: bottom; white-space: nowrap; }
        .approve-table .who { color: #666; font-size: 11px; }
        .approve-table .name { font-weight: bold; font-size: 14px; }

        .toolbar { text-align: center; margin-bottom: 24px; }
        .toolbar button {
            background: #4f46e5; color: #fff; border: 0; border-radius: 8px;
            padding: 10px 28px; font-size: 14px; cursor: pointer;
        }
        @media print {
            .toolbar { display: none; }
            html { background: none; }
            body { width: auto; min-height: 0; margin: 0; padding: 0 6mm; box-shadow: none; } /* 좌우 여백 보강 */
            @page { size: A4 portrait; margin: 24mm 18mm 12mm; } /* 상단 여백 2배 */
            /* 선정 행 배경 등 강조색이 인쇄에서 빠지지 않도록 */
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">🖨️ 인쇄하기</button></div>

    <h1>{{ $event->name }} 최종집계표</h1>

    <table class="result">
        <thead>
            <tr>
                <th style="width:56px">심사번호</th>
                <th>평가 대상</th>
                @foreach ($data['judges'] as $judge)
                    <th>{{ $judge['name'] }}</th>
                @endforeach
                <th style="width:52px">총점</th>
                <th style="width:52px">평균</th>
                <th style="width:44px">순위</th>
                @if ($event->pass_count)
                    <th style="width:48px">판정</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($data['rows'] as $row)
                <tr @class([
                    'pass-row' => ($row['pass'] ?? null) === 'pass',
                    'rank1'    => ($row['rank'] ?? null) === 1,
                ])>
                    {{-- 등록순 정렬이므로 iteration = 심사번호 (심사위원 화면·개별심사표와 동일) --}}
                    <td style="font-weight:bold">{{ sprintf('%02d', $loop->iteration) }}</td>
                    <td class="name">
                        {{ $row['name'] }}
                        @if ($row['affiliation'])<span style="color:#777"> ({{ $row['affiliation'] }})</span>@endif
                    </td>
                    @foreach ($data['judges'] as $judge)
                        @php
                            $jid      = $judge['judge_id'];
                            $total    = $row['by_judge'][$jid] ?? null;
                            $excluded = $row['by_judge_excluded'][$jid] ?? 0;
                            $kept     = $total !== null ? round($total - $excluded, 1) : null;
                        @endphp
                        <td>
                            @if ($total === null)
                                -
                            @elseif ($excluded > 0)
                                {{-- 반영분(있으면) + 제외분(붉은 취소선) --}}
                                @if ($kept > 0){{ $kept + 0 }}@endif<span class="excluded">{{ $excluded + 0 }}</span>
                            @else
                                {{ $total + 0 }}
                            @endif
                        </td>
                    @endforeach
                    <td class="sum">{{ $row['sum'] }}</td>
                    <td class="avg">{{ $row['avg'] !== null ? number_format($row['avg'], 2) : '-' }}</td>
                    <td class="rank">{{ $row['rank'] ?? '-' }}</td>
                    @if ($event->pass_count)
                        <td>
                            @if (($row['pass'] ?? null) === 'pass')
                                <span class="pass-cell">선정</span>
                            @elseif (($row['pass'] ?? null) === 'tie')
                                <span class="tie-cell">동점</span>
                            @else
                                -
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 동점 미해소 경고만 유지 (※ 설명 문구는 출력물에서 제거) --}}
    @if ($event->pass_count && ! empty($data['pass_tie']))
        <p class="note">
            <span class="tie-cell">⚠️ {{ $data['pass_tie']['rank'] }}위 동점 {{ $data['pass_tie']['tied'] }}곳, 남은 선정 자리 {{ $data['pass_tie']['slots'] }}곳 — 동점 해소 후 다시 출력하세요.</span>
        </p>
    @endif

    {{-- 심사위원 전원 서명란 (기본설정에서 생략 가능 — 생략 시 결재란 필수) --}}
    @if ($event->show_judge_signs)
    <div class="sign-section">
        <p class="sign-title">위와 같이 심사 결과를 확인합니다.</p>
        <p class="sign-date">{{ now()->format('Y년 m월 d일') }}</p>
        <div class="sign-grid">
            @foreach ($event->judges as $judge)
                <div class="sign-item">
                    <span class="who">심사위원</span>
                    <span class="name">{{ $judge->name }}</span>
                    <span class="sign-line">
                        <span class="sig-label">(서명)</span>
                        @if ($judge->signature)
                            <img src="{{ $judge->signature }}" alt="서명">
                        @endif
                    </span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- 하단 결재란 — 기본설정에서 입력한 기록자/검토자/확인자만 표시 (이름 없는 역할은 생략) --}}
    @if (! empty($event->report_signers))
        <div class="approve-section">
            <table class="approve-table">
                @foreach ($event->report_signers as $signer)
                    <tr>
                        <td class="who">{{ $signer['role'] }}</td>
                        <td>{{ $signer['dept'] ?? '' }}</td>
                        <td>{{ $signer['position'] ?? '' }}</td>
                        <td class="name">{{ $signer['name'] }}</td>
                        <td><span class="sign-line"><span class="sig-label">(서명)</span></span></td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</body>
</html>
