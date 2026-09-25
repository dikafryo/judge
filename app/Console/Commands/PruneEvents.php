<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Judge;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class PruneEvents extends Command
{
    protected $signature = 'events:prune {--dry-run : 삭제하지 않고 대상만 출력}';

    protected $description = '보관 기한이 지난 행사 자동 삭제 — 미마감 행사는 행사일(없으면 등록일) 30일 후, 마감 행사는 2년 후 삭제';

    public function handle(): int
    {
        // 매일 새벽 cron 이 이 명령만 돌리므로(스케줄러 없음) 만료된 앱 토큰 정리도 여기서 한다.
        $this->pruneExpiredTokens();

        $now = now()->startOfDay();

        // 체험용 샘플 행사(is_demo)는 상시 공개용이므로 보관 기한을 적용하지 않는다
        $targets = Event::query()->real()->get()->filter(function (Event $event) use ($now) {
            $basis = $event->event_date ?? $event->created_at;
            $keepUntil = $event->is_open ? $basis->copy()->addDays(30) : $basis->copy()->addYears(2);

            return $keepUntil->lt($now);
        });

        if ($targets->isEmpty()) {
            $this->line(now()->format('Y-m-d H:i').' 삭제 대상 없음');

            return self::SUCCESS;
        }

        foreach ($targets as $event) {
            $label = sprintf(
                '#%d "%s" (기준일 %s, %s)',
                $event->id,
                $event->name,
                ($event->event_date ?? $event->created_at)->format('Y-m-d').($event->event_date ? '' : ' — 행사일 미지정, 등록일 기준'),
                $event->is_open ? '미마감 30일 경과' : '마감 2년 경과',
            );

            if ($this->option('dry-run')) {
                $this->line('[dry-run] 삭제 대상: '.$label);

                continue;
            }

            // 토큰(personal_access_tokens)은 FK cascade 가 아니라 따로 지운다 — 남기면 지워진 행사의 토큰이 계속 쌓인다.
            $event->judges()->get()->each(fn (Judge $judge) => $judge->tokens()->delete());
            $event->tokens()->delete();
            $event->delete(); // candidates/criteria/judges/scores는 FK cascade로 함께 삭제

            $this->info(now()->format('Y-m-d H:i').' 삭제: '.$label);
        }

        return self::SUCCESS;
    }

    /**
     * 기한이 하루 넘게 지난 Sanctum 토큰 삭제. 기한은 관리자 토큰에만 있다(Event::issueAdminToken).
     * 만료된 토큰은 이미 인증에 쓰이지 않으므로 지워도 동작은 같다 — 테이블만 가벼워진다.
     */
    private function pruneExpiredTokens(): void
    {
        $query = PersonalAccessToken::query()->where('expires_at', '<', now()->subDay());

        if ($this->option('dry-run')) {
            $this->line('[dry-run] 만료 토큰 삭제 대상: '.$query->count().'건');

            return;
        }

        $deleted = $query->delete();

        if ($deleted > 0) {
            $this->info(now()->format('Y-m-d H:i').' 만료 토큰 삭제: '.$deleted.'건');
        }
    }
}
