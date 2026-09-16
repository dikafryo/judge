<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Event;
use App\Services\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 전체 관리자.
 *
 * 행사 비밀번호를 우회하는 유일한 통로다. 여기가 새면 남의 행사 점수를 마음대로
 * 고칠 수 있으므로, 꺼져 있을 때 정말 닫혀 있는지부터 못 박는다.
 */
class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function enable(string $password = 'root-secret'): void
    {
        config(['judge.super_admin_password' => $password]);
    }

    private function signedIn(): static
    {
        return $this->withSession([SuperAdmin::SESSION_KEY => true]);
    }

    public function test_비밀번호가_없으면_기능_자체가_없다(): void
    {
        config(['judge.super_admin_password' => null]);

        $this->get('/root/login')->assertNotFound();
        $this->post('/root/login', ['password' => 'x'])->assertNotFound();
        $this->signedIn()->get('/root')->assertNotFound();
    }

    public function test_로그인하지_않으면_목록을_볼_수_없다(): void
    {
        $this->enable();

        $this->get('/root')->assertRedirect(route('root.login'));
    }

    public function test_틀린_비밀번호는_들여보내지_않는다(): void
    {
        $this->enable();

        $this->post('/root/login', ['password' => '틀림'])
            ->assertSessionHasErrors('password');

        $this->assertFalse(session()->get(SuperAdmin::SESSION_KEY, false));
    }

    public function test_평문_비밀번호로_들어간다(): void
    {
        $this->enable();

        $this->post('/root/login', ['password' => 'root-secret'])
            ->assertRedirect(route('root.index'));

        $this->assertTrue(session()->get(SuperAdmin::SESSION_KEY));
    }

    /** .env 에 평문을 두지 않아도 되게 해시도 받는다. */
    public function test_해시로_넣어_둔_비밀번호도_통한다(): void
    {
        $this->enable(Hash::make('root-secret'));

        $this->post('/root/login', ['password' => 'root-secret'])
            ->assertRedirect(route('root.index'));
        $this->post('/root/login', ['password' => '틀림'])
            ->assertSessionHasErrors('password');
    }

    public function test_모든_행사가_목록에_나온다(): void
    {
        $this->enable();
        $mine = Event::factory()->create(['name' => '가을 심사']);
        $other = Event::factory()->create(['name' => '누가_만든_테스트']);

        $this->signedIn()->get('/root')
            ->assertOk()
            ->assertSee($mine->name)
            ->assertSee($other->name);
    }

    public function test_비밀번호_없이_행사_관리에_들어간다(): void
    {
        $this->enable();
        $event = Event::factory()->create();

        $this->signedIn()->post(route('root.enter', $event))
            ->assertRedirect(route('admin.dashboard', $event))
            ->assertSessionHas($event->adminSessionKey(), true);

        // 실제로 그 행사의 관리 화면이 열려야 의미가 있다
        $this->withSession([$event->adminSessionKey() => true])
            ->get(route('admin.dashboard', $event))
            ->assertOk();
    }

    public function test_고른_행사를_지운다(): void
    {
        $this->enable();
        $doomed = Event::factory()->create(['name' => '테스트1']);
        $kept = Event::factory()->create(['name' => '진짜 행사']);
        Candidate::factory()->for($doomed)->create();

        $this->signedIn()
            ->delete('/root', ['ids' => [$doomed->id], 'confirm' => '삭제'])
            ->assertRedirect(route('root.index'));

        $this->assertDatabaseMissing('events', ['id' => $doomed->id]);
        $this->assertDatabaseMissing('candidates', ['event_id' => $doomed->id]);
        $this->assertDatabaseHas('events', ['id' => $kept->id]);
    }

    public function test_확인_문구가_틀리면_아무것도_지우지_않는다(): void
    {
        $this->enable();
        $event = Event::factory()->create();

        $this->signedIn()
            ->delete('/root', ['ids' => [$event->id], 'confirm' => '지워'])
            ->assertSessionHasErrors('confirm');

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    /** 체험용 샘플을 지우면 공개 /demo 페이지가 깨진다. */
    public function test_체험용_샘플은_지워지지_않는다(): void
    {
        $this->enable();
        $demo = Event::factory()->create(['name' => '샘플', 'is_demo' => true]);

        $this->signedIn()->delete('/root', ['ids' => [$demo->id], 'confirm' => '삭제']);

        $this->assertDatabaseHas('events', ['id' => $demo->id]);
    }

    public function test_로그인하지_않으면_지울_수_없다(): void
    {
        $this->enable();
        $event = Event::factory()->create();

        $this->delete('/root', ['ids' => [$event->id], 'confirm' => '삭제'])
            ->assertRedirect(route('root.login'));

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }
}
