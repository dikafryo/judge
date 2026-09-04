<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * 네이티브 앱의 관리자 계층.
 *
 * 여기서 지켜야 할 것은 "앱과 웹이 같은 규칙을 따른다" 하나다 —
 * 배점 합계 100점, 마감 시 변경 차단, 체험 행사 보호가 두 경로에서 같아야 한다.
 */
class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
    }

    private function token(Event $event): string
    {
        return $this->postJson('/api/v1/admin/session', [
            'event_id' => $event->id,
            'password' => 'secret-password',
        ])->assertOk()->json('token');
    }

    private function admin(Event $event): array
    {
        return ['Authorization' => 'Bearer ' . $this->token($event)];
    }

    public function test_설정_화면에_필요한_것을_한_번에_준다(): void
    {
        $event = Event::factory()->create();
        Criterion::factory()->for($event)->create(['name' => '기획', 'max_score' => 60]);
        Candidate::factory()->for($event)->create(['name' => '가나다']);
        Judge::factory()->for($event)->create(['name' => '심사위원A']);

        $response = $this->getJson('/api/v1/admin/setup', $this->admin($event))->assertOk();

        $this->assertCount(1, $response->json('criteria'));
        $this->assertCount(1, $response->json('candidates'));
        $this->assertSame(60, $response->json('total_max'));

        // 심사위원 카드를 앱 화면에서 바로 보여줄 수 있어야 인쇄 없이도 코드를 나눠 준다
        $this->assertStringContainsString('/judge/', $response->json('judges.0.entry_url'));
    }

    public function test_평가_대상을_쉼표_형식으로_일괄_등록한다(): void
    {
        $event = Event::factory()->create();

        $response = $this->postJson('/api/v1/admin/candidates', [
            'bulk' => "가나다, 가람학교\n라마바 | 나람학교\n\n사아자",
        ], $this->admin($event))->assertOk();

        $candidates = $response->json('candidates');

        $this->assertCount(3, $candidates, '빈 줄은 건너뛴다');
        $this->assertSame('가람학교', $candidates[0]['affiliation']);
        $this->assertSame('나람학교', $candidates[1]['affiliation'], '파이프도 구분자다');
        $this->assertNull($candidates[2]['affiliation']);
    }

    public function test_배점_합계가_100점을_넘으면_거절한다(): void
    {
        $event = Event::factory()->create();
        Criterion::factory()->for($event)->create(['name' => '기획', 'max_score' => 80]);

        $this->postJson('/api/v1/admin/criteria', [
            'name' => '실행', 'max_score' => 30,
        ], $this->admin($event))
            ->assertStatus(422)
            ->assertJsonPath('errors.max_score', fn ($message) => str_contains($message, '100점을 초과'));
    }

    public function test_이미_채점된_항목_밑에는_2레벨을_만들지_못한다(): void
    {
        // 말단이 부모에서 자식으로 옮겨가면 이미 넣은 점수의 의미가 달라진다.
        $event = Event::factory()->create();
        $parent = Criterion::factory()->for($event)->create(['name' => '기획', 'max_score' => 60]);
        $judge = Judge::factory()->for($event)->create();
        $candidate = Candidate::factory()->for($event)->create();

        $judge->scores()->create([
            'candidate_id' => $candidate->id,
            'criterion_id' => $parent->id,
            'score' => 40,
        ]);

        $this->postJson('/api/v1/admin/criteria', [
            'name' => '창의성', 'max_score' => 30, 'parent_id' => $parent->id,
        ], $this->admin($event))
            ->assertStatus(422)
            ->assertJsonPath('errors.parent_id', fn ($m) => str_contains($m, '이미 입력된 점수'));
    }

    public function test_심사위원을_일괄_등록하면_코드가_발급된다(): void
    {
        $event = Event::factory()->create();

        $judges = $this->postJson('/api/v1/admin/judges', [
            'bulk' => "김심사\n이심사",
        ], $this->admin($event))->assertOk()->json('judges');

        $this->assertCount(2, $judges);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $judges[0]['code']);
        $this->assertNotSame($judges[0]['code'], $judges[1]['code']);
    }

    public function test_마감하면_설정을_바꿀_수_없다(): void
    {
        $event = Event::factory()->create();
        $headers = $this->admin($event);

        $this->postJson('/api/v1/admin/toggle-open', [], $headers)
            ->assertOk()->assertJsonPath('is_open', false);

        $this->postJson('/api/v1/admin/candidates', ['bulk' => '가나다'], $headers)
            ->assertStatus(423);
    }

    public function test_마감_상태에서도_재개는_된다(): void
    {
        $event = Event::factory()->closed()->create();

        $this->postJson('/api/v1/admin/toggle-open', [], $this->admin($event))
            ->assertOk()->assertJsonPath('is_open', true);
    }

    public function test_체험용_행사는_앱에서도_바꿀_수_없다(): void
    {
        $event = Event::factory()->demo()->create();

        $this->postJson('/api/v1/admin/candidates', ['bulk' => '가나다'], $this->admin($event))
            ->assertStatus(423);

        // 마감 토글까지 막혀야 샘플이 온전히 남는다
        $this->postJson('/api/v1/admin/toggle-open', [], $this->admin($event))
            ->assertStatus(423);
    }

    public function test_다른_행사의_대상은_지우지_못한다(): void
    {
        $mine = Event::factory()->create();
        $theirs = Event::factory()->create();
        $victim = Candidate::factory()->for($theirs)->create();

        $this->deleteJson("/api/v1/admin/candidates/{$victim->id}", [], $this->admin($mine))
            ->assertStatus(404);

        $this->assertDatabaseHas('candidates', ['id' => $victim->id]);
    }

    public function test_심사위원_토큰으로는_관리_API_에_못_들어간다(): void
    {
        $event = Event::factory()->create();
        $judge = Judge::factory()->for($event)->create();

        $token = $this->postJson('/api/v1/judge/session', ['code' => $judge->code])->json('token');

        $this->getJson('/api/v1/admin/setup', ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(403);
    }

    public function test_서명된_인쇄_주소가_세션_없이도_열린다(): void
    {
        // 앱에는 세션이 없고, 열리는 시스템 브라우저에도 없다.
        // 이게 안 되면 앱에서 최종집계표를 아예 볼 수 없다.
        $event = Event::factory()->create();

        $url = $this->getJson('/api/v1/admin/print-url?kind=report', $this->admin($event))
            ->assertOk()->json('url');

        $this->get($url)->assertOk();
    }

    public function test_서명이_없거나_망가지면_인쇄_주소는_거절된다(): void
    {
        $event = Event::factory()->create();

        $url = $this->getJson('/api/v1/admin/print-url?kind=report', $this->admin($event))
            ->assertOk()->json('url');

        $this->get($url . 'X')->assertRedirect(route('admin.login', $event));
        $this->get(route('admin.print', $event))->assertRedirect(route('admin.login', $event));
    }

    public function test_서명된_주소로_설정을_바꾸지는_못한다(): void
    {
        // 서명 URL 은 열람 전용이다. 여기가 뚫리면 링크 하나로 행사가 마감된다.
        $event = Event::factory()->create();

        $signed = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.toggle-open', now()->addMinutes(10), $event,
        );

        $this->post($signed)->assertRedirect(route('admin.login', $event));
        $this->assertTrue($event->fresh()->is_open);
    }

    public function test_앱_집계가_웹_집계와_같다(): void
    {
        $event = Event::factory()->create();
        $criterion = Criterion::factory()->for($event)->create(['max_score' => 100]);
        $candidate = Candidate::factory()->for($event)->create();
        $judge = Judge::factory()->for($event)->create();

        $judge->scores()->create([
            'candidate_id' => $candidate->id,
            'criterion_id' => $criterion->id,
            'score' => 88,
        ]);

        $viaApp = $this->getJson('/api/v1/admin/dashboard', $this->admin($event))->assertOk()->json();

        $this->withSession(['event_admin_' . $event->id => true]);
        $viaWeb = $this->getJson(route('admin.dashboard.data', $event))->assertOk()->json();

        $this->assertSame($viaWeb, $viaApp, '같은 aggregate() 를 쓰므로 숫자가 어긋나면 안 된다');
    }
}
