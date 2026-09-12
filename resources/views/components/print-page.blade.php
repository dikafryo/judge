{{-- A4 인쇄물 공통 껍데기.
     최종집계표와 개별심사표가 같은 용지 설정을 쓰는데 각자 복사해 갖고 있었다.
     본문 서식은 문서마다 다르므로 여기서는 종이 크기·여백·미리보기만 맡고,
     각 문서의 CSS 는 styles 슬롯으로 받는다.

     · margin: 본문 패딩 = @page 여백 (미리보기와 실제 인쇄를 일치시킨다)
     · colorExact: 배경색을 인쇄에도 그대로 (선정 행 강조가 빠지면 안 되는 문서용) --}}
@props(['title', 'margin' => '15mm 18mm', 'colorExact' => false])

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* 화면 미리보기 = 실제 A4 용지와 동일한 폭·여백 */
        html { background: #e2e8f0; }
        body {
            font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', sans-serif;
            color: #111; font-size: 12px;
            width: 210mm; min-height: 297mm; margin: 16px auto;
            padding: {{ $margin }};
            background: #fff; box-shadow: 0 2px 14px rgba(15, 23, 42, 0.25);
        }
        h1 { text-align: center; font-size: 22px; }

        {{ $styles ?? '' }}

        @media print {
            .toolbar { display: none; }
            html { background: none; }
            body { width: auto; min-height: 0; margin: 0; padding: 0 6mm; box-shadow: none; } /* 좌우 여백 보강 */
            @page { size: A4 portrait; margin: {{ $margin }}; }
            @if ($colorExact)
                /* 선정 행 배경 등 강조색이 인쇄에서 빠지지 않도록 */
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @endif
        }
    </style>
</head>
<body>
{{ $slot }}
</body>
</html>
