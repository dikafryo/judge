<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 심사 결과 CSV 내보내기.
 *
 * 열 구성은 집계 화면과 같다 — 심사번호·순위·대상·소속 + 심사위원별 점수 + 합계·평균.
 * 엑셀이 한글을 깨뜨리지 않도록 UTF-8 BOM 을 앞에 붙인다.
 */
class ResultCsvExporter
{
    /** 윈도우 파일명에 못 쓰는 문자 */
    private const UNSAFE_FILENAME_CHARS = '/[\/\\\\:*?"<>|]/';

    /** @param array $data ScoreAggregator::aggregate() 결과 */
    public function stream(Event $event, array $data): StreamedResponse
    {
        return response()->streamDownload(
            fn () => $this->write($data),
            $this->filename($event),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function filename(Event $event): string
    {
        $safeName = preg_replace(self::UNSAFE_FILENAME_CHARS, '_', $event->name);

        return $safeName.'_심사결과_'.now()->format('Ymd_His').'.csv';
    }

    private function write(array $data): void
    {
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // Excel 한글 인식용 BOM

        $judges = $data['judges'];

        $header = ['심사번호', '순위', '평가 대상', '소속'];
        foreach ($judges as $judge) {
            $header[] = $judge['name'];
        }
        array_push($header, '합계', '평균');
        fputcsv($out, $header);

        foreach ($data['rows'] as $row) {
            $line = [$row['number'] ?? '', $row['rank'] ?? '-', $row['name'], $row['affiliation'] ?? ''];
            foreach ($judges as $judge) {
                $line[] = $row['by_judge'][$judge['judge_id']] ?? '';
            }
            $line[] = $row['sum'];
            $line[] = $row['avg'] ?? '';
            fputcsv($out, $line);
        }

        fclose($out);
    }
}
