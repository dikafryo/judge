<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Services\EventSetup;
use App\Services\JudgePayloadService;
use App\Services\ScoreAggregator;
use App\Services\ScoreWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Redis 캐시가 묵은 값을 보여주지 않는지 확인한다.
 *
 * 캐시는 속도를 위한 것이고, 틀린 숫자를 빨리 보여주는 것은 아무 소용이 없다.
 * 설정·점수·서명·마감이 바뀌면 Event::forgetCaches() 가 비워야 하고,
 * 그 경로 하나라도 빠지면 심사위원이 지워진 대상을 보거나 관리자가 옛 집계를 본다.
 */
class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
        Cache::flush();
    }

    private function makeEvent(): array
    {
        $event = Event::factory()->create();
        $candidates = Candidate::factory()->count(2)->for($event)->create();
        $criteria = Criterion::factory()->count(2)->sequence(
            ['name' => '기획', 'max_score' => 50],
            ['name' => '실행', 'max_score' => 50],
        )->for($event)->create();
        $judge = Judge::factory()->for($event)->create();

        return compact('event', 'candidates', 'criteria', 'judge');
    }

    public function test_대상을_추가하면_심사위원_화면_구조_캐시가_갱신된다(): void
    {
        ['event' => $event] = $this->makeEvent();
        $service = app(JudgePayloadService::class);

        $this->assertCount(2, $service->structure($event)['candidates']);
        $this->assertTrue(Cache::has($event->cacheKey('judge-structure')));

        Candidate::factory()->for($event)->create();

        $this->assertFalse(Cache::has($event->cacheKey('judge-structure')), '대상 저장 훅이 캐시를 비워야 한다');
        $this->assertCount(3, $service->structure($event->fresh())['candidates']);
    }

    public function test_항목을_지우면_만점과_항목_묶음이_갱신된다(): void
    {
        ['event' => $event, 'criteria' => $criteria] = $this->makeEvent();
        $service = app(JudgePayloadService::class);

        $this->assertSame(100, $service->structure($event)['totalMax']);

        $criteria[1]->delete();

        $this->assertSame(50, $service->structure($event->fresh())['totalMax']);
    }

    public function test_점수를_저장하면_집계_캐시가_갱신된다(): void
    {
        ['event' => $event, 'candidates' => $candidates, 'criteria' => $criteria, 'judge' => $judge] = $this->makeEvent();
        $aggregator = app(ScoreAggregator::class);

        $before = $aggregator->aggregate($event);
        $this->assertNull($before['rows'][0]['avg']);
        $this->assertTrue(Cache::has($event->cacheKey('aggregate')));

        app(ScoreWriter::class)->save($judge, $candidates[0], [
            $criteria[0]->id => 40,
            $criteria[1]->id => 45,
        ]);

        $after = $aggregator->aggregate($event->fresh());
        $row = collect($after['rows'])->firstWhere('candidate_id', $candidates[0]->id);

        $this->assertSame(85.0, $row['avg'], '점수 저장 직후 집계가 새 값이어야 한다');
    }

    public function test_서명하면_집계의_서명_여부가_갱신된다(): void
    {
        ['event' => $event, 'judge' => $judge] = $this->makeEvent();
        $aggregator = app(ScoreAggregator::class);

        $this->assertFalse($aggregator->aggregate($event)['judges'][0]['signed']);

        $judge->update(['signature' => 'data:image/png;base64,AAAA', 'signed_at' => now()]);

        $this->assertTrue($aggregator->aggregate($event->fresh())['judges'][0]['signed']);
    }

    public function test_마감하면_캐시에_회수_전_코드가_남지_않는다(): void
    {
        ['event' => $event, 'judge' => $judge] = $this->makeEvent();
        $aggregator = app(ScoreAggregator::class);

        $this->assertSame($judge->code, $aggregator->aggregate($event)['judges'][0]['code']);

        app(EventSetup::class)->toggleOpen($event);

        $this->assertNull($aggregator->aggregate($event->fresh())['judges'][0]['code']);
    }

    public function test_앱_설정_응답은_캐시되고_대상_삭제_시_갱신된다(): void
    {
        ['event' => $event, 'candidates' => $candidates] = $this->makeEvent();

        $token = $this->postJson('/api/v1/admin/session', [
            'event_id' => $event->id,
            'password' => 'secret-password',
        ])->assertOk()->json('token');

        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/admin/setup', $headers)->assertOk()->assertJsonCount(2, 'candidates');
        $this->assertTrue(Cache::has($event->cacheKey('setup')));

        $this->deleteJson('/api/v1/admin/candidates/'.$candidates[0]->id, [], $headers)->assertOk();

        $this->getJson('/api/v1/admin/setup', $headers)->assertOk()->assertJsonCount(1, 'candidates');
    }

    public function test_행사_목록_캐시는_행사가_생기면_갱신된다(): void
    {
        $this->makeEvent();

        $this->getJson('/api/v1/events')->assertOk()->assertJsonCount(1, 'events');
        $this->assertTrue(Cache::has(Event::LIST_CACHE_KEY));

        Event::factory()->create();

        $this->getJson('/api/v1/events')->assertOk()->assertJsonCount(2, 'events');
    }
}
