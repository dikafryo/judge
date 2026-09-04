<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupController extends Controller
{
    /** 기본설정 화면 — 집계 방식 / 최종집계표 서명 / 행사 삭제 */
    public function index(Event $event): View
    {
        $event->load('judges');

        return view('admin.setup', compact('event'));
    }

    /** 평가 항목 관리 화면 — 1·2레벨 항목 구성, 배점 합계 100점 필수 */
    public function criteria(Event $event): View
    {
        $event->load('criteria');

        $byParent    = $event->criteria->groupBy('parent_id');
        $topCriteria = $event->criteria->whereNull('parent_id')->values();
        $totalMax    = (int) $topCriteria->sum('max_score');

        return view('admin.criteria', compact('event', 'totalMax', 'topCriteria', 'byParent'));
    }

    /** 평가 대상 관리 화면 */
    public function candidates(Event $event): View
    {
        $event->load('candidates');

        return view('admin.candidates', compact('event'));
    }

    /** 심사위원 관리 화면 */
    public function judges(Event $event): View
    {
        $event->load('judges');

        return view('admin.judges', compact('event'));
    }

    /** 평가 대상 일괄 등록 — 한 줄에 하나, "이름, 소속" 형식. 쉼표 외에 파이프(|)·탭 구분자도 받는다. */
    public function storeCandidates(Request $request, Event $event): RedirectResponse
    {
        $request->validate(['bulk' => ['required', 'string', 'max:10000']], [], ['bulk' => '평가 대상']);

        $order = (int) $event->candidates()->max('sort_order');
        $added = 0;

        foreach (preg_split('/\r\n|\r|\n/', $request->input('bulk')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            [$name, $affiliation] = array_pad(array_map('trim', preg_split('/[,|\t]/', $line, 2)), 2, null);

            $event->candidates()->create([
                'name'        => mb_substr($name, 0, 100),
                'affiliation' => $affiliation ? mb_substr($affiliation, 0, 100) : null,
                'sort_order'  => ++$order,
            ]);
            $added++;
        }

        return back()->with('status', "평가 대상 {$added}건이 등록되었습니다.");
    }

    public function destroyCandidate(Event $event, Candidate $candidate): RedirectResponse
    {
        abort_unless($candidate->event_id === $event->id, 404);
        $candidate->delete();

        return back()->with('status', '평가 대상이 삭제되었습니다.');
    }

    /**
     * 평가 항목 등록.
     * - 1레벨(parent_id 없음): 1레벨 배점 합계 100 초과 방지
     * - 2레벨(parent_id 있음): 해당 1레벨 배점 초과 방지, 채점은 2레벨에서만 이뤄짐
     */
    public function storeCriterion(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'max_score'   => ['required', 'integer', 'min:1', 'max:100'],
            'parent_id'   => ['nullable', 'integer'],
        ], [], ['name' => '항목명', 'max_score' => '배점', 'parent_id' => '1레벨 항목']);

        if (! empty($data['parent_id'])) {
            // 2레벨: 부모는 이 행사의 1레벨 항목이어야 함
            $parent = $event->criteria()->whereNull('parent_id')->findOrFail($data['parent_id']);

            // 1레벨이 이미 말단 항목으로 채점된 상태면 구조 변경 금지 (점수 왜곡 방지)
            if ($parent->scores()->exists()) {
                return back()->withErrors([
                    'parent_id' => "'{$parent->name}' 항목에는 이미 입력된 점수가 있어 2레벨 항목을 추가할 수 없습니다. (점수 삭제 후 가능)",
                ])->withInput();
            }

            $childTotal = (int) $parent->children()->sum('max_score');

            if ($childTotal + $data['max_score'] > $parent->max_score) {
                return back()->withErrors([
                    'max_score' => "'{$parent->name}' 2레벨 배점 합계가 1레벨 배점 {$parent->max_score}점을 초과합니다. (현재 {$childTotal}점, 추가 가능 " . ($parent->max_score - $childTotal) . '점)',
                ])->withInput();
            }
        } else {
            // 1레벨: 합계 100 초과 방지
            $currentTotal = (int) $event->topCriteria()->sum('max_score');

            if ($currentTotal + $data['max_score'] > 100) {
                return back()->withErrors([
                    'max_score' => "1레벨 배점 합계가 100점을 초과합니다. (현재 {$currentTotal}점, 추가 가능 " . (100 - $currentTotal) . '점)',
                ])->withInput();
            }
        }

        $event->criteria()->create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'max_score'   => $data['max_score'],
            'parent_id'   => $data['parent_id'] ?? null,
            'sort_order'  => (int) $event->criteria()->max('sort_order') + 1,
        ]);

        return back()->with('status', empty($data['parent_id']) ? '평가 항목(1레벨)이 등록되었습니다.' : '평가 항목(2레벨)이 등록되었습니다.');
    }

    public function destroyCriterion(Event $event, Criterion $criterion): RedirectResponse
    {
        abort_unless($criterion->event_id === $event->id, 404);
        $criterion->delete();

        return back()->with('status', '평가 항목이 삭제되었습니다.');
    }

    /** 심사위원 일괄 등록 — 한 줄에 한 명, 고유 코드 자동 발급 */
    public function storeJudges(Request $request, Event $event): RedirectResponse
    {
        $request->validate(['bulk' => ['required', 'string', 'max:5000']], [], ['bulk' => '심사위원']);

        $added = 0;

        foreach (preg_split('/\r\n|\r|\n/', $request->input('bulk')) as $line) {
            $name = trim($line);
            if ($name === '') {
                continue;
            }

            $event->judges()->create([
                'name' => mb_substr($name, 0, 50),
                'code' => Judge::generateCode(),
            ]);
            $added++;
        }

        return back()->with('status', "심사위원 {$added}명이 등록되었습니다. 접속 링크를 각 심사위원에게 전달하세요.");
    }

    public function destroyJudge(Event $event, Judge $judge): RedirectResponse
    {
        abort_unless($judge->event_id === $event->id, 404);
        $judge->delete();

        return back()->with('status', '심사위원이 삭제되었습니다.');
    }

    /** 심사위원 접속 안내 출력 — QR코드 + 접속주소 + 코드 (코드가 있는 심사위원만) */
    public function printJudges(Event $event): View
    {
        $judges = $event->judges()->whereNotNull('code')->get();

        return view('admin.judges-print', compact('event', 'judges'));
    }

    /**
     * 심사 진행/마감 토글.
     * 마감 시 심사위원 접속 코드를 회수(null)해 다른 행사와 코드가 중복될 여지를 없애고,
     * 재개 시 새 코드를 발급한다 (링크 재전달 필요).
     */
    public function toggleOpen(Event $event): RedirectResponse
    {
        $event->update(['is_open' => ! $event->is_open]);

        if (! $event->is_open) {
            $event->judges()->update(['code' => null]);

            // 코드를 회수했으면 그 코드로 발급된 앱 토큰도 함께 죽여야 한다.
            // 안 그러면 앱이 마감 뒤에도 계속 채점할 수 있다.
            $event->judges()->get()->each(fn ($judge) => $judge->tokens()->delete());

            return back()->with('status', '심사가 마감되었습니다. 심사위원 접속 코드가 모두 회수되어 더 이상 접속할 수 없습니다.');
        }

        foreach ($event->judges()->whereNull('code')->get() as $judge) {
            $judge->update(['code' => Judge::generateCode()]);
        }

        return back()->with('status', '심사가 재개되었습니다. 심사위원 접속 코드가 새로 발급되었으니 링크를 다시 전달하세요.');
    }

    /** 집계 방식 변경 — all: 전체 합계·평균 / trimmed: 항목별 최고·최저 제외 + 심사위원 화면 노출 + 선정자(선정기관) 수 */
    public function updateScoringMethod(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'scoring_method' => ['required', 'in:all,trimmed'],
            'is_blind'       => ['required', 'boolean'],
            'pass_count'     => ['nullable', 'integer', 'min:1', 'max:1000'],
        ], [], ['scoring_method' => '집계 방식', 'is_blind' => '심사위원 화면', 'pass_count' => '선정자 수']);

        $event->update([
            'scoring_method' => $data['scoring_method'],
            'is_blind'       => $data['is_blind'],
            'pass_count'     => $data['pass_count'] ?? null,
        ]);

        $method = $event->scoring_method === 'trimmed'
            ? '집계 방식이 "평가대상별 최고·최저 총점 심사위원 제외"로 변경되었습니다.'
            : '집계 방식이 "전체 합계·평균"으로 변경되었습니다.';

        $blind = $event->is_blind
            ? ' 심사위원 화면: 심사번호만 표시(블라인드).'
            : ' 심사위원 화면: 평가 대상 이름 공개.';

        $pass = $event->pass_count
            ? " 선정자(선정기관) 수: {$event->pass_count}곳 — 집계 화면에 상위 {$event->pass_count}곳이 선정으로 표시됩니다."
            : ' 선정자 수는 미지정입니다.';

        return back()->with('status', $method . $blind . $pass);
    }

    /**
     * 최종집계표 서명 방식 저장 — 심사위원 서명란 포함 여부 + 결재란(기록자/검토자/확인자).
     * 심사위원 서명란을 포함하면 결재란은 선택, 생략하면 결재란(최소 기록자)이 필수.
     * 결재란은 이름을 입력한 사람만 출력물에 표시된다 (예: 기록자·확인자 2명만).
     */
    public function updateReportSigners(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'show_judge_signs'   => ['required', 'boolean'],
            'signers'            => ['nullable', 'array'],
            'signers.*.dept'     => ['nullable', 'string', 'max:50'],
            'signers.*.position' => ['nullable', 'string', 'max:50'],
            'signers.*.name'     => ['nullable', 'string', 'max:50'],
        ], [], ['show_judge_signs' => '심사위원 서명란', 'signers.*.dept' => '부서', 'signers.*.position' => '직급', 'signers.*.name' => '이름']);

        $signers = [];

        foreach (['기록자', '검토자', '확인자'] as $role) {
            $row = $data['signers'][$role] ?? [];

            if (trim($row['name'] ?? '') === '') {
                continue;
            }

            $signers[] = [
                'role'     => $role,
                'dept'     => trim($row['dept'] ?? ''),
                'position' => trim($row['position'] ?? ''),
                'name'     => trim($row['name']),
            ];
        }

        // 심사위원 서명란을 생략하면 결재란이 유일한 확인 수단 — 기록자는 반드시 있어야 한다
        if (! $data['show_judge_signs'] && ! in_array('기록자', array_column($signers, 'role'), true)) {
            return back()->withErrors([
                'signers' => '심사위원 서명란을 생략하려면 결재란이 필수입니다 — 최소한 기록자 이름을 입력하세요.',
            ])->withInput();
        }

        $event->update([
            'show_judge_signs' => $data['show_judge_signs'],
            'report_signers'   => $signers ?: null,
        ]);

        $mode = $event->show_judge_signs
            ? '최종집계표에 심사위원 서명란을 포함합니다.'
            : '최종집계표에서 심사위원 서명란을 생략하고 결재란만 표시합니다.';

        $signerNote = $signers
            ? ' 결재란: ' . implode(', ', array_column($signers, 'role'))
            : ' 결재란: 없음.';

        return back()->with('status', $mode . $signerNote);
    }

    /** 행사 삭제 — 행사명 재입력으로 확인, 대상·항목·심사위원·점수 전체 cascade 삭제 */
    public function destroyEvent(Request $request, Event $event): RedirectResponse
    {
        $request->validate([
            'confirm_name' => ['required', 'string'],
        ], [], ['confirm_name' => '행사명']);

        if (trim($request->input('confirm_name')) !== $event->name) {
            return back()->withErrors([
                'confirm_name' => '행사명이 일치하지 않아 삭제가 취소되었습니다.',
            ]);
        }

        $name = $event->name;
        $event->delete();

        $request->session()->forget('event_admin_' . $event->id);

        return redirect()->route('home')->with('status', "'{$name}' 행사와 모든 심사 데이터가 삭제되었습니다.");
    }
}
