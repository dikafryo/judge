<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 화면이 실제로 그려지는지 본다.
 *
 * 관리 화면과 인쇄물은 그동안 렌더링 테스트가 없었다. 인쇄물은 심사 현장에서
 * 결재에 쓰이는 산출물인데, Blade 를 고치다 변수 하나를 놓치면 500 이 나고
 * 그걸 알아차릴 방법이 없었다. 뷰를 정리하기 전에 최소한의 그물을 친다.
 *
 * 세부 서식까지는 보지 않는다 — 그건 눈으로 확인할 몫이다.
 * 여기서는 "열리고, 핵심 숫자와 이름이 실려 있다" 까지만 고정한다.
 */
class ScreenRenderTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(Event $event): static
    {
        return $this->withSession([$event->adminSessionKey() => true]);
    }

    /**
     * 실제 운영에 가까운 행사 한 건.
     * 2레벨 항목 + 절사평균 + 결재란 + 서명까지 들어간, 뷰가 가장 많이 분기하는 조합이다.
     */
    private function fullEvent(): Event
    {
        $event = Event::factory()->create([
            'name' => '제1회 가을 심사',
            'scoring_method' => 'trimmed',
            'pass_count' => 1,
            'report_signers' => [
                ['role' => '기록자', 'dept' => '총무과', 'position' => '주무관', 'name' => '김기록'],
            ],
        ]);

        // 대분류 1개(서브 2개) + 서브 없는 대분류 1개 = 말단 3개, 배점 합계 100점
        $parent = Criterion::factory()->for($event)->create(['name' => '기획', 'max_score' => 60, 'sort_order' => 1]);
        $leaves = [
            Criterion::factory()->for($event)->create(['name' => '창의성', 'max_score' => 30, 'parent_id' => $parent->id, 'sort_order' => 1]),
            Criterion::factory()->for($event)->create(['name' => '완성도', 'max_score' => 30, 'parent_id' => $parent->id, 'sort_order' => 2]),
            Criterion::factory()->for($event)->create(['name' => '발표', 'max_score' => 40, 'sort_order' => 2]),
        ];

        $candidates = [
            Candidate::factory()->for($event)->create(['name' => '가나다', 'affiliation' => '어느기관', 'sort_order' => 1]),
            Candidate::factory()->for($event)->create(['name' => '라마바', 'affiliation' => '다른기관', 'sort_order' => 2]),
        ];

        // 절사평균이 실제로 동작하려면 3명 이상이 모든 항목을 채워야 한다
        foreach ([20, 25, 30] as $i => $base) {
            $judge = Judge::factory()->for($event)->create([
                'name' => "심사위원{$i}",
                'signature' => 'data:image/png;base64,'.base64_encode('fake'),
                'signed_at' => now(),
            ]);

            foreach ($candidates as $candidate) {
                foreach ($leaves as $leaf) {
                    $judge->scores()->create([
                        'candidate_id' => $candidate->id,
                        'criterion_id' => $leaf->id,
                        'score' => min($base, (int) $leaf->max_score),
                    ]);
                }
            }
        }

        return $event;
    }

    public static function adminScreens(): array
    {
        return [
            '기본설정' => ['admin.setup'],
            '평가항목' => ['admin.criteria'],
            '평가대상' => ['admin.candidates'],
            '심사위원' => ['admin.judges'],
            '집계' => ['admin.dashboard'],
            '심사위원 접속안내 인쇄' => ['admin.judges.print'],
            '최종집계표 인쇄' => ['admin.print'],
        ];
    }

    #[DataProvider('adminScreens')]
    public function test_관리_화면이_열린다(string $route): void
    {
        $event = $this->fullEvent();

        $this->actingAsAdmin($event)
            ->get(route($route, $event))
            ->assertOk()
            ->assertSee($event->name, escape: false);
    }

    public function test_기본설정은_집계_설정에_저장_버튼을_두지_않는다(): void
    {
        $event = $this->fullEvent();

        $html = $this->actingAsAdmin($event)->get(route('admin.setup', $event))->assertOk()->getContent();

        // 저장 버튼은 최종집계표 결재란 하나만 남는다 (noscript 안의 대체 버튼은 제외)
        $withoutNoscript = preg_replace('/<noscript>.*?<\/noscript>/s', '', $html);
        $this->assertSame(1, substr_count($withoutNoscript, '>저장</button>'));

        // 세 그룹 각각에 저장 상태 표시가 붙어 있다
        $this->assertSame(3, substr_count($html, 'role="status"'));
        $this->assertStringContainsString('저장되었습니다', $html);

        // JS 가 죽어도 저장할 수단은 남겨 둔다
        $this->assertStringContainsString('<noscript>', $html);
    }

    public function test_심사위원_접속안내도_화면에서_실제_용지_크기로_보인다(): void
    {
        $event = $this->fullEvent();

        $html = $this->actingAsAdmin($event)->get(route('admin.judges.print', $event))->assertOk()->getContent();

        // 다른 인쇄물과 같은 A4 껍데기를 쓴다
        $this->assertStringContainsString('width: 210mm', $html);
        $this->assertStringContainsString('min-height: 297mm', $html);

        // 실제 인쇄 여백은 이전과 같아야 한다
        $this->assertStringContainsString('@page { size: A4 portrait; margin: 12mm 14mm; }', $html);

        // 카드와 QR 은 그대로
        $this->assertStringContainsString('class="grid"', $html);
        $this->assertStringContainsString('qrcode-generator', $html);
    }

    public function test_최종집계표에_집계_결과와_결재란이_실린다(): void
    {
        $event = $this->fullEvent();

        $response = $this->actingAsAdmin($event)->get(route('admin.print', $event))->assertOk();

        $response->assertSee('가나다', escape: false);
        $response->assertSee('01', escape: false);      // 심사번호
        $response->assertSee('김기록', escape: false);  // 결재란
        $response->assertSee('총무과', escape: false);
    }

    public function test_최종집계표는_0점을_준_심사위원의_절사도_취소선으로_표시한다(): void
    {
        // 제외 여부를 값의 크기로 판단하면 0점 준 심사위원이 제외돼도 그냥 0 으로 보인다.
        $event = Event::factory()->create(['scoring_method' => 'trimmed']);
        $criterion = Criterion::factory()->for($event)->create(['max_score' => 100]);
        $candidate = Candidate::factory()->for($event)->create(['name' => '가나다']);

        foreach ([0, 50, 90] as $score) {
            $judge = Judge::factory()->for($event)->create();
            $judge->scores()->create([
                'candidate_id' => $candidate->id,
                'criterion_id' => $criterion->id,
                'score' => $score,
            ]);
        }

        $html = $this->actingAsAdmin($event)
            ->get(route('admin.print', $event))
            ->assertOk()
            ->getContent();

        // 최저(0)와 최고(90)가 모두 제외 표기되어야 한다.
        // 범례에도 같은 class 가 쓰이므로 숫자가 든 칸만 센다.
        $this->assertSame(2, preg_match_all('/class="excluded">\(\d/', $html));

        // 결재자가 검산할 수 있도록 취소선의 뜻이 같은 종이에 있어야 한다
        $this->assertStringContainsString('집계에서 제외된 점수입니다', $html);
        $this->assertStringContainsString('최고·최저 총점 제외', $html);
    }

    public function test_심사위원별_개별심사표가_열린다(): void
    {
        $event = $this->fullEvent();
        $judge = $event->judges()->first();

        $this->actingAsAdmin($event)
            ->get(route('admin.judge.sheet', [$event, $judge]))
            ->assertOk()
            ->assertSee($judge->name, escape: false)
            ->assertSee('창의성', escape: false);  // 말단 항목이 열로 나온다
    }

    public function test_심사위원_화면과_개인_심사표가_열린다(): void
    {
        $event = $this->fullEvent();
        $judge = $event->judges()->first();

        $this->get(route('judge.show', $judge))
            ->assertOk()
            ->assertSee($judge->name, escape: false);

        $this->get(route('judge.print', $judge))
            ->assertOk()
            ->assertSee('창의성', escape: false);
    }

    public function test_공개_화면이_열린다(): void
    {
        Event::factory()->create(['name' => '목록에 뜨는 행사']);

        $this->get(route('home'))->assertOk();
        $this->get(route('events.index'))->assertOk()->assertSee('목록에 뜨는 행사', escape: false);
    }
}
