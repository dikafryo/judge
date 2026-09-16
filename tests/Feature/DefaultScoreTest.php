<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Services\JudgePayloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * 심사 기본점수 — 심사위원 화면을 열었을 때 미리 채워 둘 점수(만점 대비 %).
 *
 * 값 자체는 행사 설정이고, 실제로 칸을 채우는 것은 심사 화면의 자바스크립트다.
 * 그래서 여기서는 두 가지를 고정한다 — 설정이 저장·검증되는지, 그리고 그 값이
 * 심사 화면까지 실려 내려가는지. 채워진 숫자가 맞는지는 모델 계산으로 본다.
 */
class DefaultScoreTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(Event $event): static
    {
        return $this->withSession([$event->adminSessionKey() => true]);
    }

    private function save(Event $event, mixed $percent): TestResponse
    {
        return $this->actingAsAdmin($event)->postJson(route('admin.scoring-method', $event), [
            'scoring_method' => 'all',
            'is_blind' => true,
            'default_score_percent' => $percent,
        ]);
    }

    public function test_기본점수를_저장한다(): void
    {
        $event = Event::factory()->create();

        $this->save($event, 90)
            ->assertOk()
            ->assertJsonPath('default_score_percent', 90);

        $this->assertSame(90, $event->fresh()->default_score_percent);
    }

    public function test_비우면_사용하지_않는다(): void
    {
        $event = Event::factory()->create(['default_score_percent' => 90]);

        $this->save($event, null)->assertOk()->assertJsonPath('default_score_percent', null);

        $this->assertNull($event->fresh()->default_score_percent);
    }

    /** 0 은 "0점으로 채움" 이라 "채우지 않음"(null)과 뜻이 다르다. */
    public function test_0_은_비운_것과_다르다(): void
    {
        $event = Event::factory()->create();

        $this->save($event, 0)->assertOk();

        $this->assertSame(0, $event->fresh()->default_score_percent);
    }

    public function test_0_에서_100_밖은_거부한다(): void
    {
        $event = Event::factory()->create();

        $this->save($event, 101)->assertStatus(422)->assertJsonValidationErrors('default_score_percent');
        $this->save($event, -1)->assertStatus(422)->assertJsonValidationErrors('default_score_percent');

        $this->assertNull($event->fresh()->default_score_percent);
    }

    public function test_만점의_비율로_계산한다(): void
    {
        $event = Event::factory()->make(['default_score_percent' => 90]);

        $this->assertSame(18.0, $event->defaultScoreFor(20));
        $this->assertSame(9.0, $event->defaultScoreFor(10));

        // 입력 단위가 0.5 점이라 그 단위로 맞춘다 (7 * 0.9 = 6.3 → 6.5)
        $this->assertSame(6.5, $event->defaultScoreFor(7));
    }

    public function test_미사용이면_계산하지_않는다(): void
    {
        $event = Event::factory()->make(['default_score_percent' => null]);

        $this->assertNull($event->defaultScoreFor(20));
    }

    public function test_심사위원_화면_데이터에_실린다(): void
    {
        $event = Event::factory()->create(['default_score_percent' => 50]);
        Criterion::factory()->for($event)->create(['max_score' => 40]);
        $judge = Judge::factory()->for($event)->create();

        $payload = app(JudgePayloadService::class)->build($judge, $event->load('criteria', 'candidates'));

        $this->assertSame(50, $payload['defaultScorePercent']);
    }

    /**
     * 심사 기본점수를 모르는 구버전 앱도 집계 설정을 저장한다. 그때 세 값만 보내는데,
     * 없는 키를 null 로 읽으면 관리자가 정해 둔 기본점수가 조용히 지워진다.
     */
    public function test_키가_없으면_기존_값을_건드리지_않는다(): void
    {
        $event = Event::factory()->create(['default_score_percent' => 90]);

        $this->actingAsAdmin($event)->postJson(route('admin.scoring-method', $event), [
            'scoring_method' => 'all',
            'is_blind' => true,
            'pass_count' => 3,
        ])->assertOk();

        $this->assertSame(90, $event->fresh()->default_score_percent);
    }

    /** 앱 관리 탭이 현재 값을 보여 주려면 행사 정보에 실려 있어야 한다. */
    public function test_앱_행사_정보에_실린다(): void
    {
        $event = Event::factory()->create(['default_score_percent' => 90, 'admin_password' => bcrypt('pw')]);

        $token = $event->createToken('admin')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/event')
            ->assertOk()
            ->assertJsonPath('default_score_percent', 90);
    }

    public function test_기본설정_화면에_설정_칸이_있다(): void
    {
        $event = Event::factory()->create(['default_score_percent' => 90]);

        $this->actingAsAdmin($event)->get(route('admin.setup', $event))
            ->assertOk()
            ->assertSee('심사 기본점수')
            ->assertSee('name="default_score_percent"', false);
    }
}
