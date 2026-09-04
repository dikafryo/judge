<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** 네이티브 앱이 쓰는 /api/v1 계층. 웹과 같은 규칙을 지키는지 확인한다. */
class JudgeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');   // 테스트 간 리밋 누수 방지
    }

    private function makeEvent(array $state = []): array
    {
        $event = Event::factory()->create($state);
        $candidates = Candidate::factory()->count(2)->for($event)->create();
        $criteria = Criterion::factory()->count(2)->sequence(
            ['name' => '기획', 'max_score' => 50],
            ['name' => '실행', 'max_score' => 50],
        )->for($event)->create();
        $judge = Judge::factory()->for($event)->create();

        return compact('event', 'candidates', 'criteria', 'judge');
    }

    private function token(Judge $judge): string
    {
        return $this->postJson('/api/v1/judge/session', ['code' => $judge->code])
            ->assertOk()->json('token');
    }

    private function asJudge(Judge $judge): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token($judge));
    }

    public function test_코드로_토큰을_받는다(): void
    {
        ['judge' => $judge] = $this->makeEvent();

        $this->postJson('/api/v1/judge/session', ['code' => $judge->code])
            ->assertOk()
            ->assertJsonStructure(['token', 'judge' => ['id', 'name'], 'event' => ['id', 'name']]);
    }

    public function test_없는_코드는_토큰을_주지_않는다(): void
    {
        $this->makeEvent();

        $this->postJson('/api/v1/judge/session', ['code' => '000000'])->assertStatus(422);
    }

    public function test_로그인_시도를_분당_5회로_제한한다(): void
    {
        $this->makeEvent();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/judge/session', ['code' => '000000'])->assertStatus(422);
        }

        // 6번째부터는 429 — 6자리 코드 대입에 대한 사실상 유일한 방어선이다
        $this->postJson('/api/v1/judge/session', ['code' => '000000'])->assertStatus(429);
    }

    public function test_토큰_없이는_아무것도_못_한다(): void
    {
        $this->getJson('/api/v1/judge/me')->assertStatus(401);
    }

    public function test_내_심사_정보를_내려받는다(): void
    {
        ['judge' => $judge] = $this->makeEvent();

        $this->asJudge($judge)->getJson('/api/v1/judge/me')
            ->assertOk()
            ->assertJsonStructure(['judge', 'event', 'groups', 'candidates', 'scores', 'hasSignature', 'totalMax']);
    }

    public function test_점수가_없어도_scores는_json_객체로_내려받는다(): void
    {
        ['judge' => $judge] = $this->makeEvent();

        $content = $this->asJudge($judge)->getJson('/api/v1/judge/me')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('"scores":{}', $content);
    }

    public function test_블라인드_행사는_대상_이름을_주지_않는다(): void
    {
        ['judge' => $judge] = $this->makeEvent(['is_blind' => true]);

        $candidates = $this->asJudge($judge)->getJson('/api/v1/judge/me')->json('candidates');

        $this->assertArrayNotHasKey('name', $candidates[0], '블라인드면 이름이 payload 에 없어야 한다');
        $this->assertArrayHasKey('number', $candidates[0]);
    }

    public function test_점수를_저장하고_다시_보내도_결과가_같다(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();
        $body = ['scores' => [$cr[0]->id => 40, $cr[1]->id => 35]];

        $first = $this->asJudge($judge)->putJson("/api/v1/judge/candidates/{$c[0]->id}/scores", $body)
            ->assertOk()->assertJson(['total' => 75])->json();

        $this->asJudge($judge)->putJson("/api/v1/judge/candidates/{$c[0]->id}/scores", $body)
            ->assertOk()->assertJson(['total' => 75]);

        $this->assertSame(2, $judge->scores()->where('candidate_id', $c[0]->id)->count());
        $this->assertArrayHasKey('updated_at', $first, '충돌 감지용 updated_at 이 있어야 한다');
    }

    public function test_배점_초과는_422(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();

        $this->asJudge($judge)->putJson("/api/v1/judge/candidates/{$c[0]->id}/scores",
            ['scores' => [$cr[0]->id => 51]])->assertStatus(422);
    }

    public function test_다른_행사의_대상은_404(): void
    {
        ['criteria' => $cr, 'judge' => $judge] = $this->makeEvent();
        $outsider = Candidate::factory()->create();

        $this->asJudge($judge)->putJson("/api/v1/judge/candidates/{$outsider->id}/scores",
            ['scores' => [$cr[0]->id => 10]])->assertStatus(404);
    }

    public function test_마감된_행사는_423(): void
    {
        ['candidates' => $c, 'criteria' => $cr, 'judge' => $judge] = $this->makeEvent();
        $token = $this->token($judge);

        $judge->event->update(['is_open' => false]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/judge/candidates/{$c[0]->id}/scores", ['scores' => [$cr[0]->id => 10]])
            ->assertStatus(423);
    }

    public function test_서명을_저장한다(): void
    {
        ['judge' => $judge] = $this->makeEvent();
        $png = 'data:image/png;base64,'.base64_encode('fake');

        $this->asJudge($judge)->putJson('/api/v1/judge/signature', ['signature' => $png])
            ->assertOk()->assertJsonStructure(['message', 'signed_at']);

        $this->assertNotNull($judge->fresh()->signed_at);
    }

    public function test_심사를_마감하면_발급된_토큰이_죽는다(): void
    {
        ['event' => $event, 'judge' => $judge] = $this->makeEvent();
        $token = $this->token($judge);

        // 관리자가 마감 → 코드 회수 + 토큰 폐기
        $this->withSession(["event_admin_{$event->id}" => true])
            ->post(route('admin.toggle-open', $event));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/judge/me')->assertStatus(401);
    }

    public function test_관리자는_비밀번호로_토큰을_받고_집계를_본다(): void
    {
        ['event' => $event] = $this->makeEvent();

        $token = $this->postJson('/api/v1/admin/session', [
            'event_id' => $event->id, 'password' => 'secret-password',
        ])->assertOk()->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure(['event', 'judges', 'rows', 'generated_at']);
    }

    public function test_심사위원_토큰으로는_관리자_API에_못_들어간다(): void
    {
        ['judge' => $judge] = $this->makeEvent();

        $this->asJudge($judge)->getJson('/api/v1/admin/dashboard')->assertStatus(403);
    }

    public function test_출력은_단기_서명_URL로_넘긴다(): void
    {
        ['event' => $event] = $this->makeEvent();
        $token = $this->postJson('/api/v1/admin/session', [
            'event_id' => $event->id, 'password' => 'secret-password',
        ])->json('token');

        $url = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/admin/print-url?kind=report')
            ->assertOk()->json('url');

        $this->assertStringContainsString('signature=', $url);
    }
}
