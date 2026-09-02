<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Models\Criterion;
use App\Models\Event;
use App\Models\Judge;
use App\Models\Score;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * 체험용 샘플 행사 생성 — /demo 페이지가 안내하는 "둘러보기" 데이터.
 *
 * 실행할 때마다 기존 데모 행사를 지우고 같은 내용으로 다시 만든다(멱등).
 * 점수는 고정 시드 난수라 매번 동일한 결과·순위가 재현된다.
 */
class SeedDemoEvent extends Command
{
    protected $signature = 'judge:demo {--fresh : 기존 데모 행사를 지우고 새로 만든다 (기본 동작)}';

    protected $description = '외부 공개용 샘플 행사(데모 데이터)를 생성한다';

    /** 심사위원 접속 코드 — 데모 안내 페이지에 그대로 노출되는 고정 코드 */
    private const JUDGE_CODES = ['700101', '700102', '700103', '700104', '700105'];

    /** [이름, 소속, 설명] — 관리자 화면의 "이름, 소속" 일괄 등록 형식과 같은 순서 */
    private const CANDIDATES = [
        ['스마트 급식 잔반 알리미',     '도담초등학교', '잔반량을 무게 센서로 측정해 학급별로 안내하는 서비스'],
        ['교실 공기질 자동 환기 시스템', '한빛중학교',   '이산화탄소 농도에 따라 창문 개폐를 안내하는 IoT 장치'],
        ['점자 학습 보조 키보드',       '새싹고등학교', '시각장애 학생을 위한 촉각 피드백 학습 도구'],
        ['학교 분실물 찾기 앱',         '늘봄중학교',   'QR 스티커 기반 분실물 등록·회수 서비스'],
        ['노인 복약 알림 스피커',       '미래고등학교', '음성 안내로 복약 시간을 알려주는 저가형 스피커'],
        ['교내 재활용 분리배출 도우미',  '푸른초등학교', '카메라로 재질을 인식해 분리배출을 안내하는 키오스크'],
    ];

    private const JUDGES = ['김서연', '박준호', '이하은', '정민석', '최윤아'];

    /** [대분류, 배점, 설명, [서브항목 => 배점, ...]] */
    private const CRITERIA = [
        ['창의성',   30, '아이디어의 참신성과 독창성', ['착상의 참신성' => 15, '기존 사례와의 차별성' => 15]],
        ['실현가능성', 30, '기술적·경제적으로 구현 가능한가', ['기술적 구현 가능성' => 15, '비용·기간의 현실성' => 15]],
        ['효과성',   25, '문제 해결에 실제로 기여하는 정도', []],
        ['발표력',   15, '전달력과 질의응답 대응', []],
    ];

    public function handle(): int
    {
        DB::transaction(function () {
            Event::where('is_demo', true)->get()->each->delete();

            $event = Event::create([
                'name'             => '[체험] 제12회 학생 창업 아이디어 경진대회',
                'description'      => '온라인 심사 시스템을 둘러보기 위한 샘플 행사입니다. 실제 행사가 아니며, 이 행사에서는 아무것도 저장되지 않습니다.',
                'event_date'       => now()->toDateString(),
                'admin_password'   => Hash::make('demo-'.bin2hex(random_bytes(8))), // 로그인 화면으로는 못 들어옴 — /demo 버튼 전용
                'is_open'          => true,
                'is_demo'          => true,
                'scoring_method'   => 'trimmed',
                'pass_count'       => 3,
                'is_blind'         => false,
                'show_judge_signs' => true,
                'report_signers'   => [
                    ['role' => '기록자', 'dept' => '창의교육과', 'position' => '주무관', 'name' => '홍길동'],
                    ['role' => '확인자', 'dept' => '창의교육과', 'position' => '과장',   'name' => '성춘향'],
                ],
            ]);

            $criteria = $this->createCriteria($event);
            $candidates = $this->createCandidates($event);
            $judges = $this->createJudges($event);

            $this->createScores($criteria, $candidates, $judges);

            $this->info("데모 행사 생성 완료 — #{$event->id} {$event->name}");
            $this->line('  심사위원 코드: '.implode(', ', self::JUDGE_CODES));
        });

        return self::SUCCESS;
    }

    /** 2단계 평가항목 생성 → 채점 대상인 말단 항목만 반환 */
    private function createCriteria(Event $event): array
    {
        $leaves = [];
        $order  = 0;

        foreach (self::CRITERIA as [$name, $max, $description, $children]) {
            $top = Criterion::create([
                'event_id'    => $event->id,
                'name'        => $name,
                'description' => $description,
                'max_score'   => $max,
                'sort_order'  => ++$order,
            ]);

            if (! $children) {
                $leaves[] = $top;

                continue;
            }

            $childOrder = 0;
            foreach ($children as $childName => $childMax) {
                $leaves[] = Criterion::create([
                    'event_id'   => $event->id,
                    'parent_id'  => $top->id,
                    'name'       => $childName,
                    'max_score'  => $childMax,
                    'sort_order' => ++$childOrder,
                ]);
            }
        }

        return $leaves;
    }

    /** @return Candidate[] */
    private function createCandidates(Event $event): array
    {
        $order = 0;
        $candidates = [];

        // arrow function은 $order를 값으로 캡처해 증가가 누적되지 않으므로 foreach로 만든다
        foreach (self::CANDIDATES as [$name, $affiliation, $description]) {
            $candidates[] = Candidate::create([
                'event_id'    => $event->id,
                'name'        => $name,
                'affiliation' => $affiliation,
                'description' => $description,
                'sort_order'  => ++$order,
            ]);
        }

        return $candidates;
    }

    /** @return Judge[] */
    private function createJudges(Event $event): array
    {
        return array_map(fn ($name, $i) => Judge::create([
            'event_id'  => $event->id,
            'name'      => $name,
            'code'      => self::JUDGE_CODES[$i],
            'signature' => $this->signatureImage($i),
            'signed_at' => now()->subMinutes(90 - $i * 7),
        ]), self::JUDGES, array_keys(self::JUDGES));
    }

    /**
     * 심사위원별 점수 — 대상마다 기본 실력치를 두고 심사위원별 성향(후한/박한)을 더한다.
     * 고정 시드라 실행할 때마다 같은 순위가 나온다.
     * 마지막 심사위원 1명은 일부만 채점해 "진행중" 상태를 보여준다.
     */
    private function createScores(array $criteria, array $candidates, array $judges): void
    {
        mt_srand(20260830);

        // 대상별 기본 실력치(0~1) · 심사위원별 성향 보정
        $strength = [0.86, 0.72, 0.93, 0.64, 0.78, 0.69];
        $bias     = [0.03, -0.05, 0.00, 0.06, -0.02];

        $rows = [];
        $now  = now();

        foreach ($judges as $j => $judge) {
            foreach ($candidates as $c => $candidate) {
                // 마지막 심사위원은 앞 4개 대상만 채점 (진행률 4/6)
                if ($j === count($judges) - 1 && $c >= 4) {
                    continue;
                }

                foreach ($criteria as $criterion) {
                    $ratio = $strength[$c] + $bias[$j] + mt_rand(-6, 6) / 100;
                    $ratio = max(0.5, min(1.0, $ratio));
                    $score = max(1, (int) round($criterion->max_score * $ratio));

                    $rows[] = [
                        'judge_id'     => $judge->id,
                        'candidate_id' => $candidate->id,
                        'criterion_id' => $criterion->id,
                        'score'        => $score,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Score::insert($chunk);
        }
    }

    /** 서명 이미지 — 실제 필기 서명 대신 심사위원마다 다른 곡선을 그린 PNG dataURL */
    private function signatureImage(int $seed): string
    {
        $w = 320;
        $h = 120;
        $img = imagecreatetruecolor($w, $h);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));

        $ink = imagecolorallocate($img, 24, 32, 48);
        imagesetthickness($img, 3);

        mt_srand(4200 + $seed);
        $amp   = mt_rand(18, 30);
        $waves = mt_rand(3, 5);
        $prevX = 30;
        $prevY = 70;

        for ($x = 30; $x <= $w - 30; $x += 4) {
            $t = ($x - 30) / ($w - 60);
            $y = 70 - (int) ($amp * sin($t * $waves * M_PI) * (1 - $t * 0.35))
                    - (int) (12 * sin($t * M_PI));
            imageline($img, $prevX, $prevY, $x, $y, $ink);
            $prevX = $x;
            $prevY = $y;
        }

        // 마무리 획 — 이름 밑줄처럼 한 번 긋는다
        imageline($img, 40, 96, $w - 45, 92 + mt_rand(-4, 4), $ink);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
