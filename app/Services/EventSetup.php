<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SetupRejected;
use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 행사 설정 변경 규칙.
 *
 * 웹 화면(Admin\SetupController)과 네이티브 앱(Api\AdminApiController)이 **같은 코드**를 쓴다.
 * 배점 합계 100점, 이미 채점된 항목의 구조 변경 금지, 마감 시 코드·토큰 회수 같은 규칙이
 * 두 곳으로 갈라지면 앱으로 만든 행사와 웹으로 만든 행사가 다르게 동작한다.
 */
class EventSetup
{
    /** 1레벨 항목 배점 합계 상한. 최종 점수를 100점 만점으로 읽기 위한 전제다. */
    public const TOTAL_MAX = 100;

    /** 최종집계표 결재란 역할 — 이 순서대로 출력된다 */
    public const SIGNER_ROLES = ['기록자', '검토자', '확인자'];

    /**
     * 행사 생성. 회원가입이 없어 관리 비밀번호가 유일한 열쇠다 — 반드시 해시해서 저장한다.
     *
     * @param  array{name: string, description?: string|null, event_date?: string|null, admin_password: string}  $data
     */
    public function createEvent(array $data): Event
    {
        return Event::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'event_date' => $data['event_date'] ?? null,
            'admin_password' => Hash::make($data['admin_password']),
        ]);
    }

    /**
     * 평가 대상 일괄 등록 — 한 줄에 하나, "이름, 소속" 형식.
     * 쉼표 외에 파이프(|)·탭도 구분자로 받는다.
     *
     * @return list<Candidate> 등록된 대상
     */
    public function importCandidates(Event $event, string $bulk): array
    {
        $order = (int) $event->candidates()->max('sort_order');
        $added = [];

        foreach ($this->lines($bulk) as $line) {
            [$name, $affiliation] = array_pad(array_map('trim', preg_split('/[,|\t]/', $line, 2)), 2, null);

            $added[] = $event->candidates()->create([
                'name' => mb_substr($name, 0, 100),
                'affiliation' => $affiliation !== null && $affiliation !== '' ? mb_substr($affiliation, 0, 100) : null,
                'sort_order' => ++$order,
            ]);
        }

        return $added;
    }

    /**
     * 심사위원 일괄 등록 — 한 줄에 한 명, 접속 코드 자동 발급.
     *
     * @return list<Judge>
     */
    public function importJudges(Event $event, string $bulk): array
    {
        $added = [];

        foreach ($this->lines($bulk) as $line) {
            $added[] = $event->judges()->create([
                'name' => mb_substr($line, 0, 50),
                'code' => Judge::generateCode(),
            ]);
        }

        return $added;
    }

    /**
     * 평가 항목 등록.
     *
     * - 1레벨: 배점 합계가 100점을 넘지 못한다
     * - 2레벨: 부모 배점을 넘지 못하고, **이미 채점된 1레벨 밑에는 만들 수 없다**
     *   (말단이 부모에서 자식으로 옮겨가면 이미 넣은 점수의 의미가 달라진다)
     *
     * @param  array{name: string, description?: string|null, max_score: int, parent_id?: int|null}  $data
     *
     * @throws SetupRejected
     */
    public function addCriterion(Event $event, array $data): Criterion
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId) {
            $parent = $event->criteria()->whereNull('parent_id')->findOrFail($parentId);

            if ($parent->scores()->exists()) {
                throw new SetupRejected(
                    "'{$parent->name}' 항목에는 이미 입력된 점수가 있어 2레벨 항목을 추가할 수 없습니다. (점수 삭제 후 가능)",
                    'parent_id',
                );
            }

            $used = (int) $parent->children()->sum('max_score');

            if ($used + $data['max_score'] > $parent->max_score) {
                throw new SetupRejected(
                    "'{$parent->name}' 2레벨 배점 합계가 1레벨 배점 {$parent->max_score}점을 초과합니다. "
                        ."(현재 {$used}점, 추가 가능 ".($parent->max_score - $used).'점)',
                    'max_score',
                );
            }
        } else {
            $used = (int) $event->topCriteria()->sum('max_score');

            if ($used + $data['max_score'] > self::TOTAL_MAX) {
                throw new SetupRejected(
                    '1레벨 배점 합계가 '.self::TOTAL_MAX."점을 초과합니다. (현재 {$used}점, 추가 가능 "
                        .(self::TOTAL_MAX - $used).'점)',
                    'max_score',
                );
            }
        }

        return $event->criteria()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'max_score' => $data['max_score'],
            'parent_id' => $parentId,
            'sort_order' => (int) $event->criteria()->max('sort_order') + 1,
        ]);
    }

    /**
     * 심사 진행/마감 토글.
     *
     * 마감하면 접속 코드를 회수하고 **발급된 앱 토큰도 함께 폐기한다.**
     * 토큰을 남겨 두면 앱이 마감 뒤에도 계속 채점할 수 있다.
     */
    public function toggleOpen(Event $event): string
    {
        $event->update(['is_open' => ! $event->is_open]);

        if (! $event->is_open) {
            $event->judges()->update(['code' => null]);
            $event->judges()->get()->each(fn (Judge $judge) => $judge->tokens()->delete());

            return '심사가 마감되었습니다. 심사위원 접속 코드가 모두 회수되어 더 이상 접속할 수 없습니다.';
        }

        foreach ($event->judges()->whereNull('code')->get() as $judge) {
            $judge->update(['code' => Judge::generateCode()]);
        }

        return '심사가 재개되었습니다. 심사위원 접속 코드가 새로 발급되었으니 링크를 다시 전달하세요.';
    }

    /**
     * 집계 방식·블라인드·선정자 수.
     *
     * @param  array{scoring_method: string, is_blind: bool, pass_count?: int|null, default_score_percent?: int|null}  $data
     */
    public function updateScoringMethod(Event $event, array $data): void
    {
        $event->update([
            'scoring_method' => $data['scoring_method'],
            'is_blind' => $data['is_blind'],
            'pass_count' => $data['pass_count'] ?? null,
        ]);

        // 키가 아예 없으면 건드리지 않는다. 심사 기본점수를 모르는 구버전 앱이
        // 집계 설정을 저장할 때 관리자가 정해 둔 값을 지워 버리면 안 된다.
        // 명시적인 null 은 "사용 안 함" 이므로 그대로 저장한다.
        if (array_key_exists('default_score_percent', $data)) {
            $event->update(['default_score_percent' => $data['default_score_percent']]);
        }
    }

    /**
     * 최종집계표 서명 방식과 결재란을 저장한다.
     *
     * @param  list<array{role: string, dept?: string|null, position?: string|null, name?: string|null}>  $rows
     * @return list<array{role: string, dept: string, position: string, name: string}>
     *
     * @throws SetupRejected
     */
    public function updateReportSigners(Event $event, bool $showJudgeSigns, array $rows): array
    {
        $signers = $this->normalizeReportSigners($rows);

        if (! $showJudgeSigns && ! in_array(self::SIGNER_ROLES[0], array_column($signers, 'role'), true)) {
            throw new SetupRejected(
                '심사위원 서명란을 생략하려면 결재란이 필수입니다 — 최소한 기록자 이름을 입력하세요.',
                'signers',
            );
        }

        $event->update([
            'show_judge_signs' => $showJudgeSigns,
            'report_signers' => $signers ?: null,
        ]);

        return $signers;
    }

    /**
     * @param  list<array{role: string, dept?: string|null, position?: string|null, name?: string|null}>  $rows
     * @return list<array{role: string, dept: string, position: string, name: string}>
     */
    private function normalizeReportSigners(array $rows): array
    {
        $byRole = collect($rows)->keyBy('role');
        $signers = [];

        foreach (self::SIGNER_ROLES as $role) {
            $row = $byRole->get($role, []);
            $name = trim((string) ($row['name'] ?? ''));

            if ($name !== '') {
                $signers[] = [
                    'role' => $role,
                    'dept' => trim((string) ($row['dept'] ?? '')),
                    'position' => trim((string) ($row['position'] ?? '')),
                    'name' => $name,
                ];
            }
        }

        return $signers;
    }

    /**
     * 행사와 종속 데이터 및 앱 인증 토큰을 삭제한다.
     *
     * 되돌릴 수 없으므로 행사명을 그대로 다시 입력해야 지운다.
     * 이 확인은 도메인 규칙이라 웹·앱 양쪽이 여기 한 곳을 거친다.
     *
     * @throws SetupRejected 행사명이 일치하지 않을 때
     */
    public function deleteEvent(Event $event, string $confirmName): string
    {
        $name = $event->name;

        if (trim($confirmName) !== $name) {
            throw new SetupRejected('행사명이 일치하지 않아 삭제가 취소되었습니다.', 'confirm_name');
        }

        return $this->purgeEvent($event);
    }

    /**
     * 행사명 확인 없이 지운다. 전체 관리자 전용이다.
     *
     * 행사별 비밀번호를 모르는 사람이 지우는 경로이므로, 무엇을 지웠는지 이름을 돌려준다.
     * 체험용 샘플은 /demo 가 딛고 서 있어 지우면 공개 페이지가 깨진다 — 여기서 막는다.
     *
     * @throws SetupRejected
     */
    public function purgeEvent(Event $event): string
    {
        if ($event->is_demo) {
            throw new SetupRejected('체험용 샘플 행사는 삭제할 수 없습니다.', 'event');
        }

        $name = $event->name;

        DB::transaction(function () use ($event): void {
            $event->judges()->get()->each(fn (Judge $judge) => $judge->tokens()->delete());
            $event->tokens()->delete();
            $event->delete();
        });

        return $name;
    }

    /** @return list<string> 빈 줄을 걸러낸 각 줄 */
    private function lines(string $bulk): array
    {
        return array_values(array_filter(
            array_map(trim(...), preg_split('/\r\n|\r|\n/', $bulk)),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
