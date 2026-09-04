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
}
