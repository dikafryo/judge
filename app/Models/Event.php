<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class Event extends Model implements AuthenticatableContract
{
    /** 집계 방식 — all: 전체 평균 / trimmed: 최고·최저 심사위원 제외 */
    public const SCORING_METHODS = ['all', 'trimmed'];

    // 관리자는 행사 비밀번호를 토큰으로 교환해 앱에서 쓴다
    // Sanctum 토큰의 주체가 되려면 Authenticatable 이어야 한다.
    // 없으면 인증된 요청이 throttle 미들웨어를 지날 때 getAuthIdentifier() 로 500 이 난다.
    use Authenticatable;
    use HasApiTokens;
    use HasFactory;

    protected $fillable = ['name', 'description', 'event_date', 'admin_password', 'is_open', 'is_demo', 'scoring_method', 'pass_count', 'is_blind', 'report_signers', 'show_judge_signs'];

    /**
     * 체험용 샘플을 뺀 실제 행사만.
     * 샘플은 /demo 에서만 안내하고 목록·정리 작업에서는 늘 빠진다.
     */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where('is_demo', false);
    }

    /** 집계 방식 안내문 (대시보드·최종집계표 ※ 표기용) */
    public function scoringMethodNote(): string
    {
        return $this->scoring_method === 'trimmed'
            ? '총점·평균은 평가 대상별로 최고 총점·최저 총점을 부여한 심사위원의 점수를 제외한 합계·평균입니다. (채점 3인 이상일 때 적용, 미만이면 전체 집계)'
            : '총점·평균은 전체 심사위원 점수의 합계·평균입니다.';
    }

    protected $hidden = ['admin_password'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_open' => 'boolean',
            'is_demo' => 'boolean',
            'is_blind' => 'boolean',
            'report_signers' => 'array',
            'show_judge_signs' => 'boolean',
        ];
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 심사번호 — 블라인드 심사용 등록순 2자리 번호 맵 [candidate_id => '01', ...].
     * 심사위원에게는 기관/이름 대신 이 번호만 노출한다 (최종집계표에서만 이름과 함께 표기).
     */
    public function candidateNumbers(): array
    {
        return $this->candidates->values()
            ->mapWithKeys(fn ($c, $i) => [$c->id => sprintf('%02d', $i + 1)])
            ->all();
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->orderBy('sort_order')->orderBy('id');
    }

    /** 대분류(최상위) 항목만 */
    public function topCriteria(): HasMany
    {
        return $this->hasMany(Criterion::class)->whereNull('parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 채점 대상이 되는 말단 항목 목록 (대분류 순서대로).
     * 서브항목이 있는 대분류는 서브항목들이, 없으면 대분류 자신이 말단이다.
     *
     * 관계가 아니라 계산 결과라 Collection 을 돌려준다 — `$event->leafCriteria` 로는 못 쓴다.
     *
     * @return Collection<int, Criterion>
     */
    public function leafCriteria(): Collection
    {
        // 이미 적재됐으면 다시 조회하지 않는다 (집계는 load('criteria') 직후에 부른다)
        $all = $this->relationLoaded('criteria') ? $this->criteria : $this->criteria()->get();
        $byParent = $all->groupBy('parent_id');
        $leaves = collect();

        foreach ($all->whereNull('parent_id') as $top) {
            $children = $byParent->get($top->id, collect());
            if ($children->isEmpty()) {
                $leaves->push($top);
            } else {
                $leaves = $leaves->concat($children);
            }
        }

        return $leaves->values();
    }

    public function judges(): HasMany
    {
        return $this->hasMany(Judge::class)->orderBy('id');
    }

    /**
     * 관리자 세션 키. 회원 개념이 없어 "이 행사의 관리자로 인증됨"을 세션에 행사별로 담는다.
     * 로그인·행사 생성·체험 진입·삭제 후 정리가 모두 같은 키를 써야 한다.
     */
    public function adminSessionKey(): string
    {
        return 'event_admin_'.$this->id;
    }

    /** 평가 항목 배점 합계(대분류 기준) — 100이어야 정상 운영 가능 */
    public function totalMaxScore(): int
    {
        return (int) $this->topCriteria()->sum('max_score');
    }
}
