<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>{{ $event->name }} 심사위원 접속 안내</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Malgun Gothic', 'Apple SD Gothic Neo', sans-serif;
            color: #111; padding: 32px; font-size: 13px;
        }
        h1 { text-align: center; font-size: 20px; margin-bottom: 4px; }
        .subtitle { text-align: center; color: #666; font-size: 12px; margin-bottom: 24px; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .card {
            border: 1px dashed #999; border-radius: 10px;
            padding: 16px; display: flex; gap: 16px; align-items: center;
            page-break-inside: avoid;
        }
        .card .qr { flex-shrink: 0; width: 108px; height: 108px; }
        .card .qr svg { width: 108px; height: 108px; }
        .card .info { min-width: 0; }
        .card .name { font-size: 17px; font-weight: bold; margin-bottom: 6px; }
        .card .name small { font-weight: normal; color: #888; font-size: 11px; }
        .card .row { margin-top: 3px; color: #444; font-size: 12px; }
        .card .row b { color: #111; }
        .card .code {
            display: inline-block; margin-top: 6px;
            font-size: 22px; font-weight: bold; letter-spacing: 4px;
            border: 1px solid #333; border-radius: 6px; padding: 3px 10px;
        }
        .hint { margin-top: 6px; color: #999; font-size: 10px; }

        .empty { text-align: center; color: #888; padding: 60px 0; }

        .toolbar { text-align: center; margin-bottom: 22px; }
        .toolbar button {
            background: #4f46e5; color: #fff; border: 0; border-radius: 8px;
            padding: 10px 28px; font-size: 14px; cursor: pointer;
        }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            @page { size: A4 portrait; margin: 12mm 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">🖨️ 인쇄하기</button></div>

    <h1>{{ $event->name }} — 심사위원 접속 안내</h1>
    <p class="subtitle">
        QR코드를 스캔하면 본인 심사 화면으로 바로 접속됩니다.
        QR 사용이 어려우면 접속 주소에서 코드를 입력하세요. (점선을 따라 잘라 개별 전달)
    </p>

    @if ($judges->isEmpty())
        <p class="empty">
            접속 코드가 있는 심사위원이 없습니다.
            @unless ($event->is_open) (심사 마감으로 코드가 회수된 상태입니다 — 재개하면 새 코드가 발급됩니다.) @endunless
        </p>
    @else
        <div class="grid">
            @foreach ($judges as $judge)
                <div class="card">
                    <div class="qr" data-url="{{ route('judge.show', $judge) }}"></div>
                    <div class="info">
                        <div class="name">{{ $judge->name }} <small>심사위원</small></div>
                        <div class="row">행사: <b>{{ $event->name }}</b></div>
                        <div class="row">접속 주소: <b>{{ rtrim(config('app.url'), '/') }}/</b></div>
                        <div class="code">{{ $judge->code }}</div>
                        <div class="hint">{{ route('judge.show', $judge) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <script>
        document.querySelectorAll('.qr').forEach(function (el) {
            var qr = qrcode(0, 'M');
            qr.addData(el.dataset.url);
            qr.make();
            el.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
        });
    </script>
</body>
</html>
