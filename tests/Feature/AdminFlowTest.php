<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 관리자 웹 흐름의 현재 동작을 고정한다.
 *
 * 특히 '마감하면 심사위원 코드를 회수한다'는 동작은 네이티브 앱 설계의 전제다 —
 * 앱은 그 결과로 404 를 받고 '코드 만료'로 안내해야 한다.
 */
class AdminFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private function actingAsAdmin(Event $event): static
    {
        return $this->withSession(["event_admin_{$event->id}" => true]);
    }

    public function test_비밀번호로_관리자에_로그인한다(): void
    {
        $event = Event::factory()->create();

        $this->post(route('admin.login.post', $event), ['password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard', $event));

        $this->assertTrue(session()->has("event_admin_{$event->id}"));
    }

    public function test_틀린_비밀번호는_거절한다(): void
    {
        $event = Event::factory()->create();

        $this->from(route('admin.login', $event))
            ->post(route('admin.login.post', $event), ['password' => 'wrong'])
            ->assertRedirect(route('admin.login', $event))
            ->assertSessionHasErrors();

        $this->assertFalse(session()->has("event_admin_{$event->id}"));
    }

    public function test_로그인하지_않으면_관리화면에_못_들어간다(): void
    {
        $event = Event::factory()->create();

        $this->get(route('admin.dashboard', $event))->assertRedirect(route('admin.login', $event));
    }

    public function test_집계_데이터를_내려준다(): void
    {
        $event = Event::factory()->create();
        Candidate::factory()->for($event)->create();
        Criterion::factory()->for($event)->create(['max_score' => 100]);
        Judge::factory()->for($event)->create();

        $this->actingAsAdmin($event)
            ->getJson(route('admin.dashboard.data', $event))
            ->assertOk()
            ->assertJsonStructure(['event', 'judges', 'rows', 'generated_at']);
    }

    public function test_마감하면_심사위원_코드를_회수한다(): void
    {
        $event = Event::factory()->create();
        $judge = Judge::factory()->for($event)->create();

        $this->actingAsAdmin($event)->post(route('admin.toggle-open', $event));

        $this->assertFalse($event->fresh()->is_open);
        $this->assertNull($judge->fresh()->code, '마감 시 코드가 회수되어야 한다');
    }

    public function test_재개하면_코드를_새로_발급한다(): void
    {
        $event = Event::factory()->closed()->create();
        $judge = Judge::factory()->for($event)->codeRevoked()->create();

        $this->actingAsAdmin($event)->post(route('admin.toggle-open', $event));

        $this->assertTrue($event->fresh()->is_open);
        $this->assertNotNull($judge->fresh()->code, '재개 시 새 코드가 나와야 한다');
    }

    public function test_체험용_행사는_관리_변경도_막는다(): void
    {
        $event = Event::factory()->demo()->create();

        $this->actingAsAdmin($event)
            ->postJson(route('admin.toggle-open', $event))
            ->assertStatus(423);
    }

    public function test_기본설정_최종집계표_서명란을_저장한다(): void
    {
        // 웹 폼은 show_judge_signs 를 문자열 '0'/'1' 로 보낸다.
        // strict_types 환경에서 bool 타입힌트에 문자열을 넘기면 500 이 난다 — 그 경로를 고정한다.
        $event = Event::factory()->closed()->create();

        $this->actingAsAdmin($event)
            ->post(route('admin.report-signers', $event), [
                'show_judge_signs' => '0',
                'signers' => [
                    '기록자' => ['dept' => '총무과', 'position' => '주무관', 'name' => '김기록'],
                    '검토자' => ['dept' => '', 'position' => '', 'name' => ''],
                    '확인자' => ['dept' => '', 'position' => '', 'name' => ''],
                ],
            ])
            ->assertSessionHas('status');

        $event->refresh();

        $this->assertFalse($event->show_judge_signs);
        $this->assertSame('김기록', $event->report_signers[0]['name']);
        $this->assertCount(1, $event->report_signers, '이름이 비어 있는 역할은 결재란에서 빠진다');
    }

    public function test_기본설정_최종집계표_서명란을_포함으로_저장한다(): void
    {
        $event = Event::factory()->closed()->create();

        $this->actingAsAdmin($event)
            ->post(route('admin.report-signers', $event), [
                'show_judge_signs' => '1',
                'signers' => [
                    '기록자' => ['dept' => '', 'position' => '', 'name' => ''],
                    '검토자' => ['dept' => '', 'position' => '', 'name' => ''],
                    '확인자' => ['dept' => '', 'position' => '', 'name' => ''],
                ],
            ])
            ->assertSessionHas('status');

        $this->assertTrue($event->fresh()->show_judge_signs);
    }

    public function test_집계_결과를_csv_파일로_내려받는다(): void
    {
        $event = Event::factory()->create(['name' => '가을/심사']);
        $criterion = Criterion::factory()->for($event)->create(['max_score' => 100]);
        $candidate = Candidate::factory()->for($event)->create(['name' => '가나다', 'affiliation' => '어느기관']);
        $judge = Judge::factory()->for($event)->create(['name' => '김심사']);

        $judge->scores()->create([
            'candidate_id' => $candidate->id,
            'criterion_id' => $criterion->id,
            'score' => 88,
        ]);

        $response = $this->actingAsAdmin($event)->get(route('admin.export', $event))->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, '엑셀이 한글을 읽으려면 BOM 이 필요하다');
        // 공백이 든 항목은 fputcsv 가 따옴표로 감싼다
        $this->assertStringContainsString('심사번호,순위,"평가 대상",소속,김심사,합계,평균', $csv);
        $this->assertStringContainsString('01,1,가나다,어느기관,88,88,88', $csv);

        // 파일명의 경로 구분자는 _ 로 바뀐다 (헤더에는 퍼센트 인코딩되어 실린다)
        $this->assertStringContainsString(
            rawurlencode('가을_심사_심사결과_'),
            $response->headers->get('content-disposition'),
        );
    }

    public function test_결재란_역할은_정해진_셋_중_하나여야_한다(): void
    {
        // 웹은 역할을 배열 키로 보내서 규칙 문법으로 잡히지 않는다.
        // 앱에만 있던 역할 화이트리스트를 웹도 같이 받는지 고정한다.
        $event = Event::factory()->create();

        $this->actingAsAdmin($event)
            ->post(route('admin.report-signers', $event), [
                'show_judge_signs' => '1',
                'signers' => [
                    '심사위원장' => ['dept' => '', 'position' => '', 'name' => '엉뚱한 역할'],
                ],
            ])
            ->assertSessionHasErrors('signers.심사위원장.role');

        $this->assertNull($event->fresh()->report_signers);
    }
}
