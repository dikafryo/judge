<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\Judge;

/**
 * 심사위원 화면에 내려보낼 데이터를 조립한다.
 *
 * 웹(Blade)과 네이티브 앱(API)이 **같은 코드**를 쓰게 하려고 컨트롤러에서 빼냈다.
 * 특히 블라인드 처리 — 이름·소속을 payload 에 아예 싣지 않는 규칙 — 이 두 곳으로
 * 갈라지면 한쪽만 고쳐져 이름이 새는 사고가 난다. 규칙은 여기 한 곳에만 있어야 한다.
 */
class JudgePayloadService
{
    /**
     * @return array{
     *     judge: array, event: array, groups: array, candidates: array,
     *     scores: array, hasSignature: bool, totalMax: int
     * }
     */
    public function build(Judge $judge, Event $event): array
    {
        return [
            'judge' => ['id' => $judge->id, 'name' => $judge->name, 'code' => $judge->code],
            'event' => [
                'name'     => $event->name,
                'is_open'  => $event->is_open,
                'is_blind' => $event->is_blind,
            ],
            'groups'       => $this->groups($event),
            'candidates'   => $this->candidates($event),
            'scores'       => $this->scores($judge),
            'hasSignature' => ! empty($judge->signature),
            'totalMax'     => (int) $event->criteria->whereNull('parent_id')->sum('max_score'),
        ];
    }

    /**
     * 이미 입력한 점수: { candidate_id: { criterion_id: score } }
     */
    public function scores(Judge $judge): array
    {
        return $judge->scores()
            ->get()
            ->groupBy('candidate_id')
            ->map(fn ($group) => $group->pluck('score', 'criterion_id'))
            ->toArray();
    }

    /**
     * 평가항목 2단계 구조 — 대분류마다 items 를 둔다.
     * 채점은 말단(items)에서만 하며, 자식이 없는 대분류는 자기 자신이 말단이 된다.
     */
    public function groups(Event $event): array
    {
        $byParent = $event->criteria->groupBy('parent_id');

        $mapItem = fn ($c) => [
            'id'          => $c->id,
            'name'        => $c->name,
            'description' => $c->description,
            'max_score'   => (int) $c->max_score,
        ];

        return $event->criteria->whereNull('parent_id')->values()
            ->map(function ($top) use ($byParent, $mapItem) {
                $children = $byParent->get($top->id, collect());

                return [
                    'id'           => $top->id,
                    'name'         => $top->name,
                    'max_score'    => (int) $top->max_score,
                    'has_children' => $children->isNotEmpty(),
                    'items'        => ($children->isEmpty() ? collect([$top]) : $children)->map($mapItem)->values()->toArray(),
                ];
            })->toArray();
    }

    /**
     * 블라인드면 심사번호만 내려간다 — 이름·소속은 payload 에 담기지 않는다.
     * 화면에서 감추는 것이 아니라 데이터 자체를 주지 않는 것이 요점이다.
     */
    public function candidates(Event $event): array
    {
        return $event->candidates->values()
            ->map(fn ($c, $i) => array_merge([
                'id'     => $c->id,
                'number' => sprintf('%02d', $i + 1),
            ], $event->is_blind ? [] : [
                'name'        => $c->name,
                'affiliation' => $c->affiliation,
            ]))
            ->values()->toArray();
    }
}
