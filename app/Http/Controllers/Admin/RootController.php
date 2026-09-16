<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\SetupRejected;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventSetup;
use App\Services\SuperAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 전체 관리자 — /root
 *
 * 행사별 비밀번호를 모르는 채로 모든 행사를 보고, 남겨진 테스트 행사를 치우는 화면.
 * 여기서 "들어가기" 를 누르면 그 행사의 관리자 세션을 받아 평소 화면으로 넘어간다.
 */
class RootController extends Controller
{
    public function __construct(
        private readonly SuperAdmin $superAdmin,
        private readonly EventSetup $setup,
    ) {}

    public function showLogin(Request $request): View|RedirectResponse
    {
        abort_unless($this->superAdmin->isEnabled(), 404);

        if ($this->superAdmin->isSignedIn($request)) {
            return redirect()->route('root.index');
        }

        return view('admin.root.login');
    }

    public function login(Request $request): RedirectResponse
    {
        abort_unless($this->superAdmin->isEnabled(), 404);

        $request->validate(['password' => ['required', 'string']], [], ['password' => '비밀번호']);

        if (! $this->superAdmin->matches($request->string('password')->value())) {
            return back()->withErrors(['password' => '비밀번호가 올바르지 않습니다.']);
        }

        $this->superAdmin->signIn($request);

        return redirect()->route('root.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->superAdmin->signOut($request);

        return redirect()->route('home');
    }

    /**
     * 모든 행사. 체험용 샘플까지 포함한다 — 무엇이 있는지 빠짐없이 보여 주는 화면이다.
     *
     * 지우고 싶은 것을 찾는 게 목적이므로 최근 만든 것부터 놓고, 비어 있는 행사(대상·항목·
     * 심사위원이 모두 0)를 따로 표시한다. 테스트로 만들다 만 행사가 대부분 그 모습이다.
     */
    public function index(): View
    {
        $events = Event::query()
            ->withCount(['candidates', 'criteria', 'judges'])
            ->latest('id')
            ->get();

        return view('admin.root.index', [
            'events' => $events,
            'emptyCount' => $events->filter($this->isEmpty(...))->count(),
        ]);
    }

    /** 행사 비밀번호 없이 그 행사의 관리 화면으로 들어간다. */
    public function enter(Request $request, Event $event): RedirectResponse
    {
        $this->superAdmin->grantEventAccess($request, $event);

        return redirect()->route('admin.dashboard', $event);
    }

    /**
     * 고른 행사를 지운다.
     *
     * 실수를 막는 장치는 "삭제" 를 손으로 치게 하는 것 하나뿐이다. 행사명을 하나하나
     * 입력하게 하면 테스트 행사 수십 개를 치우는 일이 불가능해진다 — 그러면 아무도
     * 치우지 않고, 목록은 지금처럼 다시 쌓인다.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'confirm' => ['required', 'string'],
        ], [], ['ids' => '삭제할 행사', 'confirm' => '확인 문구']);

        if (trim($request->string('confirm')->value()) !== '삭제') {
            return back()->withErrors(['confirm' => '확인 문구가 일치하지 않아 삭제가 취소되었습니다.']);
        }

        $events = Event::query()->whereIn('id', $request->input('ids'))->get();

        $deleted = [];
        $skipped = [];

        foreach ($events as $event) {
            try {
                $deleted[] = $this->setup->purgeEvent($event);
            } catch (SetupRejected) {
                $skipped[] = $event->name;
            }
        }

        return redirect()->route('root.index')->with('status', $this->summary($deleted, $skipped));
    }

    /**
     * @param  list<string>  $deleted
     * @param  list<string>  $skipped
     */
    private function summary(array $deleted, array $skipped): string
    {
        $message = $deleted === []
            ? '삭제된 행사가 없습니다.'
            : count($deleted).'개 행사를 삭제했습니다: '.implode(', ', $deleted);

        if ($skipped !== []) {
            $message .= ' (체험용 샘플은 건너뛰었습니다: '.implode(', ', $skipped).')';
        }

        return $message;
    }

    private function isEmpty(Event $event): bool
    {
        return $event->candidates_count === 0
            && $event->criteria_count === 0
            && $event->judges_count === 0;
    }
}
