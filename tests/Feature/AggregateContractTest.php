<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Score;
use App\Services\ScoreAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 집계 계산의 현재 동작을 고정하는 특성화 테스트.
 *
 * 절사평균·순위·선정자 판정은 이 앱에서 가장 정교한 계산인데 지금까지 테스트가 없었다.
 * 집계 로직을 서비스로 옮기기 전에 여기서 값을 못 박아 두고, 옮긴 뒤에도 같은 값이
 * 나오는지로 회귀를 잡는다. 반환 배열의 형태는 앱 API·최종집계표·대시보드 폴링이
 * 함께 쓰는 공개 계약이라 키 구성까지 검사한다.
 */
class AggregateContractTest extends TestCase
{
    use RefreshDatabase;

    /** 배점 50점짜리 말단 항목 2개 = 만점 100점 */
    private function makeEvent(array $attributes = []): Event
    {
        $event = Event::factory()->create($attributes);

        Criterion::factory()->create(['event_id' => $event->id, 'name' => '항목A', 'max_score' => 50, 'sort_order' => 1]);
        Criterion::factory()->create(['event_id' => $event->id, 'name' => '항목B', 'max_score' => 50, 'sort_order' => 2]);

        return $event;
    }

    private function candidate(Event $event, string $name, int $order): Candidate
    {
        return Candidate::factory()->create([
            'event_id' => $event->id,
            'name' => $name,
            'affiliation' => $name.' 소속',
            'sort_order' => $order,
        ]);
    }

    private function judge(Event $event, string $name): Judge
    {
        return Judge::factory()->create(['event_id' => $event->id, 'name' => $name]);
    }

    /**
     * 두 항목에 점수를 준다. 항목 하나만 주면 미완료로 집계에서 빠진다.
     *
     * @param  list<float|int>  $scores  말단 항목 순서대로
     */
    private function score(Judge $judge, Candidate $candidate, array $scores): void
    {
        $criteria = $judge->event->criteria()->get()->values();

        foreach ($scores as $i => $score) {
            Score::factory()->create([
                'judge_id' => $judge->id,
                'candidate_id' => $candidate->id,
                'criterion_id' => $criteria[$i]->id,
                'score' => $score,
            ]);
        }
    }

    private function aggregate(Event $event): array
    {
        return app(ScoreAggregator::class)->aggregate($event->fresh());
    }

    /** 집계 결과에서 한 대상의 행을 이름으로 찾는다 */
    private function row(array $data, string $name): array
    {
        foreach ($data['rows'] as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }

        $this->fail("집계 결과에 '{$name}' 행이 없다");
    }

    public function test_절사평균은_최고최저_심사위원의_점수를_통째로_제외한다(): void
    {
        $event = $this->makeEvent(['scoring_method' => 'trimmed']);
        $candidate = $this->candidate($event, '가나다', 1);

        $low = $this->judge($event, '낮게 준 심사위원');
        $mid = $this->judge($event, '중간 심사위원');
        $high = $this->judge($event, '높게 준 심사위원');

        $this->score($low, $candidate, [30, 30]);   // 60
        $this->score($mid, $candidate, [35, 35]);   // 70
        $this->score($high, $candidate, [40, 40]);  // 80

        $row = $this->row($this->aggregate($event), '가나다');

        // 60 과 80 이 빠지고 70 만 남는다
        $this->assertSame(70.0, $row['sum']);
        $this->assertSame(70.0, $row['avg']);
        $this->assertSame(3, $row['judged_count'], '제외해도 채점한 인원 수는 3명 그대로다');

        $this->assertSame(
            [$low->id => 60.0, $high->id => 80.0],
            $row['by_judge_excluded'],
            '최종집계표 취소선 표기에 쓰인다',
        );

        // 제외되어도 원점수는 그대로 내려간다
        $this->assertSame([$low->id => 60.0, $mid->id => 70.0, $high->id => 80.0], $row['by_judge']);
    }

    public function test_채점_인원이_3명_미만이면_절사하지_않는다(): void
    {
        $event = $this->makeEvent(['scoring_method' => 'trimmed']);
        $candidate = $this->candidate($event, '가나다', 1);

        $this->score($this->judge($event, '심사1'), $candidate, [30, 30]);  // 60
        $this->score($this->judge($event, '심사2'), $candidate, [40, 40]);  // 80

        $row = $this->row($this->aggregate($event), '가나다');

        $this->assertSame([], $row['by_judge_excluded'], '2명이면 제외가 없다');
        $this->assertSame(140.0, $row['sum']);
        $this->assertSame(70.0, $row['avg']);
    }

    public function test_집계_방식이_all_이면_아무도_제외하지_않는다(): void
    {
        $event = $this->makeEvent(['scoring_method' => 'all']);
        $candidate = $this->candidate($event, '가나다', 1);

        $this->score($this->judge($event, '심사1'), $candidate, [30, 30]);  // 60
        $this->score($this->judge($event, '심사2'), $candidate, [35, 35]);  // 70
        $this->score($this->judge($event, '심사3'), $candidate, [40, 40]);  // 80

        $row = $this->row($this->aggregate($event), '가나다');

        $this->assertSame([], $row['by_judge_excluded']);
        $this->assertSame(210.0, $row['sum']);
        $this->assertSame(70.0, $row['avg']);
    }

    public function test_항목을_다_채우지_않은_심사는_집계에서_빠진다(): void
    {
        $event = $this->makeEvent();
        $candidate = $this->candidate($event, '가나다', 1);

        $done = $this->judge($event, '완료한 심사위원');
        $partial = $this->judge($event, '한 항목만 준 심사위원');

        $this->score($done, $candidate, [40, 40]);  // 80, 완료
        $this->score($partial, $candidate, [50]);   // 50, 미완료

        $row = $this->row($this->aggregate($event), '가나다');

        $this->assertSame(80.0, $row['sum'], '미완료 50점은 합계에 들어가지 않는다');
        $this->assertSame(80.0, $row['avg']);
        $this->assertSame(1, $row['judged_count']);
        $this->assertSame(50.0, $row['by_judge'][$partial->id], '원점수 자체는 화면에 보인다');
    }

    public function test_동점은_같은_순위를_받고_다음_순위를_건너뛴다(): void
    {
        $event = $this->makeEvent();
        $judge = $this->judge($event, '심사1');

        $this->score($judge, $this->candidate($event, '일등', 1), [45, 45]);     // 90
        $this->score($judge, $this->candidate($event, '공동이등A', 2), [40, 40]); // 80
        $this->score($judge, $this->candidate($event, '공동이등B', 3), [40, 40]); // 80
        $this->score($judge, $this->candidate($event, '사등', 4), [35, 35]);     // 70

        $data = $this->aggregate($event);

        $this->assertSame(1, $this->row($data, '일등')['rank']);
        $this->assertSame(2, $this->row($data, '공동이등A')['rank']);
        $this->assertSame(2, $this->row($data, '공동이등B')['rank']);
        $this->assertSame(4, $this->row($data, '사등')['rank'], '3위는 건너뛴다');
    }

    public function test_채점되지_않은_대상은_순위와_선정에서_빠진다(): void
    {
        $event = $this->makeEvent(['pass_count' => 1]);
        $judge = $this->judge($event, '심사1');

        $this->score($judge, $this->candidate($event, '채점됨', 1), [40, 40]);
        $this->candidate($event, '미채점', 2);

        $row = $this->row($this->aggregate($event), '미채점');

        $this->assertNull($row['avg']);
        $this->assertNull($row['rank']);
        $this->assertNull($row['pass']);
        $this->assertSame(0, $row['judged_count']);
    }

    public function test_평가_대상이_선정자_수_이하면_전원_선정이다(): void
    {
        $event = $this->makeEvent(['pass_count' => 5]);
        $judge = $this->judge($event, '심사1');

        $this->score($judge, $this->candidate($event, '가', 1), [45, 45]);
        $this->score($judge, $this->candidate($event, '나', 2), [40, 40]);

        $data = $this->aggregate($event);

        $this->assertNull($data['pass_tie']);
        $this->assertSame('pass', $this->row($data, '가')['pass']);
        $this->assertSame('pass', $this->row($data, '나')['pass']);
    }

    public function test_커트라인_동점으로_선정자_수를_넘으면_동점_해소를_요구한다(): void
    {
        $event = $this->makeEvent(['pass_count' => 2]);
        $judge = $this->judge($event, '심사1');

        $this->score($judge, $this->candidate($event, '확정', 1), [45, 45]);   // 90
        $this->score($judge, $this->candidate($event, '동점A', 2), [40, 40]);  // 80
        $this->score($judge, $this->candidate($event, '동점B', 3), [40, 40]);  // 80

        $data = $this->aggregate($event);

        // 90 이 1자리를 확정하고, 남은 1자리를 80점 두 곳이 다툰다
        $this->assertSame(['rank' => 2, 'tied' => 2, 'slots' => 1], $data['pass_tie']);
        $this->assertSame('pass', $this->row($data, '확정')['pass']);
        $this->assertSame('tie', $this->row($data, '동점A')['pass']);
        $this->assertSame('tie', $this->row($data, '동점B')['pass']);
    }

    public function test_커트라인_동점이_선정자_수_안에_들어가면_그대로_선정이다(): void
    {
        $event = $this->makeEvent(['pass_count' => 3]);
        $judge = $this->judge($event, '심사1');

        $this->score($judge, $this->candidate($event, '일등', 1), [45, 45]);   // 90
        $this->score($judge, $this->candidate($event, '동점A', 2), [40, 40]);  // 80
        $this->score($judge, $this->candidate($event, '동점B', 3), [40, 40]);  // 80
        $this->score($judge, $this->candidate($event, '탈락', 4), [30, 30]);   // 60

        $data = $this->aggregate($event);

        $this->assertNull($data['pass_tie'], '동점자까지 3자리에 딱 맞으면 다툴 일이 없다');
        $this->assertSame('pass', $this->row($data, '동점A')['pass']);
        $this->assertSame('pass', $this->row($data, '동점B')['pass']);
        $this->assertNull($this->row($data, '탈락')['pass']);
    }

    public function test_선정자_수를_정하지_않으면_선정_표시가_없다(): void
    {
        $event = $this->makeEvent(['pass_count' => null]);
        $judge = $this->judge($event, '심사1');

        $this->score($judge, $this->candidate($event, '가', 1), [45, 45]);

        $data = $this->aggregate($event);

        $this->assertNull($data['pass_tie']);
        $this->assertNull($this->row($data, '가')['pass']);
        $this->assertNull($data['event']['pass_count']);
    }

    public function test_심사위원_진행률은_완료한_대상_수를_센다(): void
    {
        $event = $this->makeEvent();
        $a = $this->candidate($event, '가', 1);
        $b = $this->candidate($event, '나', 2);

        $judge = $this->judge($event, '심사1');
        $this->score($judge, $a, [40, 40]);  // 완료
        $this->score($judge, $b, [40]);      // 미완료

        $progress = $this->aggregate($event)['judges'][0];

        $this->assertSame($judge->id, $progress['judge_id']);
        $this->assertSame(1, $progress['done']);
        $this->assertSame(2, $progress['total']);
        $this->assertFalse($progress['signed']);
    }

    public function test_집계_결과의_키_구성은_앱과_화면이_함께_쓰는_계약이다(): void
    {
        $event = $this->makeEvent(['pass_count' => 1]);
        $judge = $this->judge($event, '심사1');
        $this->score($judge, $this->candidate($event, '가', 1), [45, 45]);

        $data = $this->aggregate($event);

        $this->assertSame(
            ['event', 'pass_tie', 'judges', 'rows', 'generated_at'],
            array_keys($data),
        );

        $this->assertSame(
            ['name', 'is_open', 'total_max', 'scoring_method', 'scoring_note', 'pass_count'],
            array_keys($data['event']),
        );

        $this->assertSame(
            ['candidate_id', 'number', 'name', 'affiliation', 'by_judge', 'by_judge_excluded',
                'sum', 'avg', 'judged_count', 'rank', 'pass'],
            array_keys($data['rows'][0]),
        );

        $this->assertSame(
            ['judge_id', 'name', 'code', 'signed', 'done', 'total'],
            array_keys($data['judges'][0]),
        );

        $this->assertSame(100, $data['event']['total_max']);
        $this->assertSame('01', $data['rows'][0]['number'], '심사번호는 등록순 2자리다');
    }

    public function test_웹_컨트롤러는_같은_집계_서비스에_위임한다(): void
    {
        $event = $this->makeEvent(['scoring_method' => 'trimmed', 'pass_count' => 1]);
        $candidate = $this->candidate($event, '가나다', 1);

        $this->score($this->judge($event, '심사1'), $candidate, [30, 30]);
        $this->score($this->judge($event, '심사2'), $candidate, [35, 35]);
        $this->score($this->judge($event, '심사3'), $candidate, [40, 40]);

        $viaController = app(DashboardController::class)->aggregate($event->fresh());
        $viaService = $this->aggregate($event);

        unset($viaController['generated_at'], $viaService['generated_at']);

        $this->assertEquals($viaService, $viaController, '컨트롤러가 계산을 따로 하면 안 된다');
    }
}
