<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class PruneEvents extends Command
{
    protected $signature = 'events:prune {--dry-run : 삭제하지 않고 대상만 출력}';

    protected $description = '보관 기한이 지난 행사 자동 삭제 — 미마감 행사는 행사일(없으면 등록일) 30일 후, 마감 행사는 2년 후 삭제';

    public function handle(): int
    {
        $now = now()->startOfDay();

        $targets = Event::all()->filter(function (Event $event) use ($now) {
            $basis     = $event->event_date ?? $event->created_at;
            $keepUntil = $event->is_open ? $basis->copy()->addDays(30) : $basis->copy()->addYears(2);

            return $keepUntil->lt($now);
        });

        if ($targets->isEmpty()) {
            $this->line(now()->format('Y-m-d H:i') . ' 삭제 대상 없음');

            return self::SUCCESS;
        }

        foreach ($targets as $event) {
            $label = sprintf(
                '#%d "%s" (기준일 %s, %s)',
                $event->id,
                $event->name,
                ($event->event_date ?? $event->created_at)->format('Y-m-d') . ($event->event_date ? '' : ' — 행사일 미지정, 등록일 기준'),
                $event->is_open ? '미마감 30일 경과' : '마감 2년 경과',
            );

            if ($this->option('dry-run')) {
                $this->line('[dry-run] 삭제 대상: ' . $label);

                continue;
            }

            $event->delete(); // candidates/criteria/judges/scores는 FK cascade로 함께 삭제

            $this->info(now()->format('Y-m-d H:i') . ' 삭제: ' . $label);
        }

        return self::SUCCESS;
    }
}
