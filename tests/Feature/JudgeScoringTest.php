<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 심사위원 웹 흐름의 현재 동작을 고정한다.
 *
 * API 계층을 새로 얹으면서 이 동작이 바뀌면 안 된다. 지금 저장소에는 기능 테스트가
 * 하나도 없어, 웹이 깨져도 알아챌 방법이 없다 — 그 공백을 메우는 것이 이 파일의 목적이다.
 */
class JudgeScoringTest extends TestCase
{
    use RefreshDatabase;

    /** 대상 2명 · 항목 2개(각 50점)짜리 행사를 만든다. */
    private function makeEvent(array $state = []): array
    {
        $event = Event::factory()->create($state);

        $candidates = Candidate::factory()->count(2)->sequence(
            ['name' => '가나다', 'sort_order' => 0],
            ['name' => '라마바', 'sort_order' => 1],
        )->for($event)->create();

        $criteria = Criterion::factory()->count(2)->sequence(
            ['name' => '기획', 'max_score' => 50, 'sort_order' => 0],
            ['name' => '실행', 'max_score' => 50, 'sort_order' => 1],
        )->for($event)->create();

        $judge = Judge::factory()->for($event)->create(['name' => '홍심사']);

        return compact('event', 'candidates', 'criteria', 'judge');
    }

    private function postScores(Judge $judge, int $candidateId, array $scores)
    {
        return $this->postJson("/judge/{$judge->code}/scores", [
            'candidate_id' => $candidateId,
            'scores'       => $scores,
        ]);
    }

    public function test_심사위원은_코드로_심사화면에_들어간다(): void
    {
        ['judge' => $judge] = $this->makeEvent();

        $this->get("/judge/{$judge->code}")->assertOk();

        $this->post('/judge/enter', ['code' => $judge->code])
            ->assertRedirect(route('judge.show', $judge));
    }

    public function test_없는_코드는_되돌려보낸다(): void
    {
        $this->makeEvent();

        $this->from('/')->post('/judge/enter', ['code' => '000000'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('code');
    }

    public function test_점수를_저장한다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();

        $this->postScores($judge, $c[0]->id, [$cr[0]->id => 40, $cr[1]->id => 35])
            ->assertOk()
            ->assertJson(['candidate_id' => $c[0]->id, 'total' => 75]);

        $this->assertDatabaseHas('scores', [
            'judge_id' => $judge->id, 'candidate_id' => $c[0]->id,
            'criterion_id' => $cr[0]->id, 'score' => 40,
        ]);
    }

    public function test_같은_요청을_다시_보내도_결과가_같다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();
        $payload = [$cr[0]->id => 40, $cr[1]->id => 35];

        $this->postScores($judge, $c[0]->id, $payload)->assertOk();
        $this->postScores($judge, $c[0]->id, $payload)->assertOk();

        // 오프라인 큐가 재전송해도 안전하다는 근거 — UNIQUE 인덱스 + updateOrCreate
        $this->assertSame(2, $judge->scores()->where('candidate_id', $c[0]->id)->count());
    }

    public function test_빈_값으로_제출하면_그_항목의_기존_점수가_지워진다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();

        $this->postScores($judge, $c[0]->id, [$cr[0]->id => 40, $cr[1]->id => 35])->assertOk();
        $this->postScores($judge, $c[0]->id, [$cr[0]->id => 40, $cr[1]->id => null])->assertOk();

        $this->assertSame(1, $judge->scores()->where('candidate_id', $c[0]->id)->count());
    }

    public function test_배점을_넘는_점수는_거절한다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();

        $this->postScores($judge, $c[0]->id, [$cr[0]->id => 51])
            ->assertStatus(422)
            ->assertJsonPath('message', "'기획' 항목은 배점 50점을 초과할 수 없습니다.");
    }

    public function test_전부_비우면_거절한다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();

        $this->postScores($judge, $c[0]->id, [$cr[0]->id => null, $cr[1]->id => null])
            ->assertStatus(422);
    }

    public function test_다른_행사의_대상은_거절한다(): void
    {
        ['criteria' => $cr, 'judge' => $judge] = $this->makeEvent();
        $outsider = Candidate::factory()->create();

        $this->postScores($judge, $outsider->id, [$cr[0]->id => 10])->assertNotFound();
    }

    public function test_마감된_행사는_423으로_거절한다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent(['is_open' => false]);

        $this->postScores($judge, $c[0]->id, [$cr[0]->id => 10])
            ->assertStatus(423);
    }

    public function test_체험용_행사는_저장을_막는다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent(['is_demo' => true]);

        $this->postScores($judge, $c[0]->id, [$cr[0]->id => 10])->assertStatus(423);
        $this->get("/judge/{$judge->code}")->assertOk();   // 조회는 열려 있다
    }

    public function test_코드가_회수되면_들어갈_수_없다(): void
    {
        // 관리자가 심사를 마감하면 코드를 NULL 로 회수한다 → 라우트 바인딩이 실패해 404.
        // 앱은 이 404 를 '코드 만료'로 번역해야 한다.
        $event = Event::factory()->closed()->create();
        Judge::factory()->for($event)->codeRevoked()->create();

        $this->get('/judge/123456')->assertNotFound();
    }

    public function test_서명을_저장한다(): void
    {
        ['judge' => $judge] = $this->makeEvent();
        $png = 'data:image/png;base64,' . base64_encode('fake-png');

        $this->postJson("/judge/{$judge->code}/signature", ['signature' => $png])->assertOk();

        $this->assertNotNull($judge->fresh()->signed_at);
    }

    public function test_png가_아닌_서명은_거절한다(): void
    {
        ['judge' => $judge] = $this->makeEvent();

        $this->postJson("/judge/{$judge->code}/signature", ['signature' => 'data:image/jpeg;base64,zzz'])
            ->assertStatus(422);
    }

    /**
     * 대상 이름은 @json($payload) 안에 들어가므로 화면에는 유니코드 이스케이프(\uac00...)로 나온다.
     * 원문으로 찾으면 언제나 어긋나므로, 인코딩된 형태로 확인한다.
     */
    private function encoded(string $text): string
    {
        return trim(json_encode($text), '"');
    }

    public function test_블라인드_행사는_대상_이름을_화면에_싣지_않는다(): void
    {
        ['judge' => $judge] = $this->makeEvent(['is_blind' => true]);

        $this->get("/judge/{$judge->code}")->assertOk()
            ->assertDontSee($this->encoded('가나다'), false);
    }

    public function test_이름공개_행사는_대상_이름을_싣는다(): void
    {
        ['judge' => $judge] = $this->makeEvent(['is_blind' => false]);

        $this->get("/judge/{$judge->code}")->assertOk()
            ->assertSee($this->encoded('가나다'), false);
    }
}
