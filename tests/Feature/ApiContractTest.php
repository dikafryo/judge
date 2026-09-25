<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Judge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * 앱과의 계약 중 "오류 경로" — 문구·상태코드·제한 통.
 * 앱은 message 를 그대로 사용자에게 보여 주므로 영어 키나 클래스명이 새어 나가면 안 된다.
 */
class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    private const THROTTLED = '시도가 너무 많습니다. 잠시 후 다시 시도해 주세요.';

    public function test_meta는_api_버전과_최소_빌드를_정수로_준다(): void
    {
        $this->getJson('/api/v1/meta')
            ->assertOk()
            ->assertExactJson(['api_version' => 'v1', 'min_app_build' => 16]);
    }

    public function test_검증_오류는_한국어로_나간다(): void
    {
        $this->postJson('/api/v1/judge/session', [])
            ->assertStatus(422)
            ->assertJsonPath('message', '심사위원 코드 항목은 필수입니다.')
            ->assertJsonPath('errors.code.0', '심사위원 코드 항목은 필수입니다.');
    }

    public function test_검증_오류가_여럿이면_한국어로_건수를_붙인다(): void
    {
        $message = $this->postJson('/api/v1/admin/session', [])->assertStatus(422)->json('message');

        $this->assertSame('행사 항목은 필수입니다. (외 1건)', $message);
    }

    public function test_없는_대상은_한국어_404이고_모델명을_드러내지_않는다(): void
    {
        $judge = Judge::factory()->create();
        $token = $this->postJson('/api/v1/judge/session', ['code' => $judge->code])->json('token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/judge/candidates/999999/scores', ['scores' => []])
            ->assertStatus(404)
            ->assertExactJson(['message' => '대상을 찾을 수 없습니다. 삭제되었을 수 있습니다.']);

        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }

    public function test_토큰이_없거나_폐기되면_이유를_한국어로_알린다(): void
    {
        $this->getJson('/api/v1/judge/me')
            ->assertStatus(401)
            ->assertExactJson(['message' => '접속이 만료되었습니다. 심사가 마감되었거나 코드가 바뀌었습니다.']);
    }

    public function test_공용_ip_행사장에서_심사위원_여럿이_연달아_들어간다(): void
    {
        $event = Event::factory()->create();
        $judges = Judge::factory()->count(12)->for($event)->create();

        // 예전 throttle:5,1 이면 여섯 번째부터 429 였다
        foreach ($judges as $judge) {
            $this->postJson('/api/v1/judge/session', ['code' => $judge->code])->assertOk();
        }
    }

    public function test_심사위원_입장_제한은_관리자_로그인을_막지_않는다(): void
    {
        $event = Event::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/judge/session', ['code' => '000000'])->assertStatus(422);
        }
        $this->postJson('/api/v1/judge/session', ['code' => '000000'])
            ->assertStatus(429)
            ->assertJsonPath('message', self::THROTTLED)
            ->assertHeader('Retry-After');

        // 같은 IP 라도 다른 코드는 계속 시도할 수 있고, 관리자 로그인은 제 통을 쓴다
        $this->postJson('/api/v1/judge/session', ['code' => '111111'])->assertStatus(422);
        $this->postJson('/api/v1/admin/session', ['event_id' => $event->id, 'password' => 'secret-password'])
            ->assertOk();
    }

    public function test_관리자_로그인은_행사마다_분당_10회로_제한한다(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/admin/session', ['event_id' => $event->id, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/admin/session', ['event_id' => $event->id, 'password' => 'secret-password'])
            ->assertStatus(429)
            ->assertJsonPath('message', self::THROTTLED);
        $this->postJson('/api/v1/admin/session', ['event_id' => $other->id, 'password' => 'secret-password'])
            ->assertOk();
    }

    public function test_cloudflare_를_거친_요청은_실제_방문자_ip로_센다(): void
    {
        $viaEdge = fn (string $client) => $this->withServerVariables(['REMOTE_ADDR' => '162.158.1.1'])
            ->withHeader('X-Forwarded-For', $client);

        for ($i = 0; $i < 60; $i++) {
            $viaEdge('203.0.113.10')->postJson('/api/v1/judge/session', ['code' => sprintf('9%05d', $i)])
                ->assertStatus(422);
        }
        $viaEdge('203.0.113.10')->postJson('/api/v1/judge/session', ['code' => '999999'])->assertStatus(429);

        // 같은 엣지를 거친 다른 방문자는 제 통을 쓴다
        $viaEdge('203.0.113.20')->postJson('/api/v1/judge/session', ['code' => '999999'])->assertStatus(422);
    }

    public function test_원서버에_직접_붙어_xff_를_지어내도_ip_제한을_못_피한다(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
                ->withHeader('X-Forwarded-For', "203.0.113.{$i}")
                ->postJson('/api/v1/judge/session', ['code' => sprintf('8%05d', $i)])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('X-Forwarded-For', '203.0.113.250')
            ->postJson('/api/v1/judge/session', ['code' => '888888'])
            ->assertStatus(429);
    }

    public function test_관리자_토큰은_기한이_있고_심사위원_토큰은_없다(): void
    {
        $event = Event::factory()->create();
        $judge = Judge::factory()->for($event)->create();

        $adminToken = $this->postJson('/api/v1/admin/session', ['event_id' => $event->id, 'password' => 'secret-password'])
            ->assertOk()->json('token');
        $judgeToken = $this->postJson('/api/v1/judge/session', ['code' => $judge->code])->assertOk()->json('token');

        $this->assertEqualsWithDelta(
            now()->addHours(12)->getTimestamp(),
            PersonalAccessToken::findToken($adminToken)->expires_at->getTimestamp(),
            5,
        );
        $this->assertNull(PersonalAccessToken::findToken($judgeToken)->expires_at);

        $this->travel(13)->hours();

        $this->withHeader('Authorization', "Bearer {$adminToken}")->getJson('/api/v1/admin/event')->assertStatus(401);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$judgeToken}")->getJson('/api/v1/judge/me')->assertOk();
    }

    public function test_행사를_만들며_받은_관리자_토큰에도_기한이_있다(): void
    {
        $token = $this->postJson('/api/v1/events', ['name' => '기한 확인 행사', 'admin_password' => 'secret-password'])
            ->assertCreated()->json('token');

        $this->assertNotNull(PersonalAccessToken::findToken($token)->expires_at);
    }

    public function test_만료_토큰은_매일_정리에서_지워진다(): void
    {
        $event = Event::factory()->create();
        $event->issueAdminToken();
        $judge = Judge::factory()->for($event)->create();
        $judge->createToken('judge-app', ['judge']);

        $this->travel(37)->hours();
        $this->artisan('events:prune')->assertSuccessful();

        $this->assertSame(0, PersonalAccessToken::where('name', 'judge-app-admin')->count());
        $this->assertSame(1, PersonalAccessToken::where('name', 'judge-app')->count());
    }
}
